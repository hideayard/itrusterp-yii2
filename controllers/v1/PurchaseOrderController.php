<?php

namespace app\controllers\v1;

use Yii;
use yii\db\Query;
use app\components\ResourceController;
use app\components\PurchaseCalculator;
use app\components\PurchaseBiller;
use app\components\ValidationException;
use app\models\Product;
use app\models\PurchaseOrder;
use app\models\PurchaseOrderLine;

class PurchaseOrderController extends ResourceController
{
    protected function verbs(): array
    {
        return array_merge(parent::verbs(), [
            'confirm' => ['POST'],
            'cancel' => ['POST'],
            'draft' => ['POST'],
            'toggle-lock' => ['POST'],
            'confirm-receipt-date' => ['POST'],
            'receipts' => ['GET'],
            'bills' => ['GET'],
            'bill' => ['POST'],
        ]);
    }

    public function actionCreate()
    {
        $cfg = $this->cfg();
        $this->authorize('create', $cfg);
        $this->resolveExpand($cfg);

        [$input, $payload] = $this->parseBody($cfg);
        $this->validatePayload($cfg, $payload);
        $lines = $this->validatedLines($payload['lines'] ?? null, true);

        $order = new PurchaseOrder();
        $this->loadAttributes($order, $input);
        $this->validateModel($order, $input, false);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$order->save(false)) {
                throw new ValidationException($order->getErrors());
            }
            $this->syncLines($order, $lines);
            $order = (new PurchaseCalculator())->recompute($order);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        Yii::$app->response->setStatusCode(201);

        return [
            'data' => $this->serialize($order, $cfg, true),
            'message' => 'Purchase order created successfully.',
        ];
    }

    public function actionUpdate($id)
    {
        $cfg = $this->cfg();
        $this->authorize('update', $cfg);
        $this->resolveExpand($cfg);
        $order = $this->findModel($cfg, $id);

        [$input, $payload] = $this->parseBody($cfg);
        $this->validatePayload($cfg, $payload);
        $lines = array_key_exists('lines', $payload)
            ? $this->validatedLines($payload['lines'], false)
            : null;

        $this->loadAttributes($order, $input);
        $this->validateModel($order, $input, true);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$order->save(false)) {
                throw new ValidationException($order->getErrors());
            }
            if ($lines !== null) {
                $this->syncLines($order, $lines);
            }
            $order = (new PurchaseCalculator())->recompute($order);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return [
            'data' => $this->serialize($order, $cfg, true),
            'message' => 'Purchase order updated successfully.',
        ];
    }

    public function actionConfirm($id)
    {
        return $this->transition($id, 'purchase', 'Purchase order confirmed successfully.');
    }

    public function actionCancel($id)
    {
        return $this->transition($id, 'canceled', 'Purchase order canceled successfully.');
    }

    public function actionDraft($id)
    {
        return $this->transition($id, 'draft', 'Purchase order set to draft successfully.');
    }

    public function actionToggleLock($id)
    {
        $cfg = $this->cfg();
        $this->authorize('update', $cfg);
        $order = $this->findModel($cfg, $id);

        if (!in_array($order->state, [PurchaseOrder::STATE_PURCHASE, PurchaseOrder::STATE_DONE], true)) {
            throw new ValidationException([], 'Only purchase or done orders can toggle lock state.');
        }

        $order->state = $order->state === PurchaseOrder::STATE_DONE
            ? PurchaseOrder::STATE_PURCHASE
            : PurchaseOrder::STATE_DONE;
        $order->save(false);
        $order = (new PurchaseCalculator())->recompute($order);

        return [
            'data' => $this->serialize($order, $cfg, true),
            'message' => 'Purchase order lock state updated successfully.',
        ];
    }

    public function actionConfirmReceiptDate($id)
    {
        $cfg = $this->cfg();
        $this->authorize('update', $cfg);
        $order = $this->findModel($cfg, $id);

        if ($order->mail_reminder_confirmed
            || !in_array($order->state, [PurchaseOrder::STATE_PURCHASE, PurchaseOrder::STATE_DONE], true)
        ) {
            throw new ValidationException([], 'Only unconfirmed purchase or done orders can confirm receipt date.');
        }

        $order->mail_reminder_confirmed = 1;
        $order->save(false);
        $order = (new PurchaseCalculator())->recompute($order);

        return [
            'data' => $this->serialize($order, $cfg, true),
            'message' => 'Receipt date confirmed successfully.',
        ];
    }

    public function actionReceipts($order_id)
    {
        $cfg = $this->cfg();
        $this->authorize('view', $cfg);
        $order = $this->findModel($cfg, $order_id);

        return $this->rawPaginated(
            (new Query())
                ->from(['o' => 'inventories_operations'])
                ->innerJoin('purchases_order_operations poo', 'poo.inventory_operation_id = o.id')
                ->where(['poo.purchase_order_id' => $order->id]),
            ['o.id', 'o.name', 'o.origin', 'o.move_type', 'o.state', 'o.scheduled_at', 'o.closed_at', 'o.partner_id', 'o.company_id', 'o.created_at'],
            'o.id'
        );
    }

    public function actionBill($id)
    {
        $cfg = $this->cfg();
        $this->authorize('update', $cfg);
        $order = $this->findModel($cfg, $id);

        $bill = (new PurchaseBiller())->createBill($order);

        return [
            'data' => $this->serialize($order, $cfg, true),
            'bill' => $bill,
            'message' => 'Bill created successfully.',
        ];
    }

    public function actionBills($order_id)
    {
        $cfg = $this->cfg();
        $this->authorize('view', $cfg);
        $order = $this->findModel($cfg, $order_id);

        return $this->rawPaginated(
            (new Query())
                ->from(['m' => 'accounts_account_moves'])
                ->innerJoin('purchases_order_account_moves poam', 'poam.move_id = m.id')
                ->where(['poam.order_id' => $order->id, 'm.move_type' => 'in_invoice']),
            ['m.id', 'm.name', 'm.state', 'm.payment_state', 'm.move_type', 'm.date', 'm.invoice_date_due', 'm.amount_untaxed', 'm.amount_tax', 'm.amount_total', 'm.partner_id', 'm.currency_id'],
            'm.id'
        );
    }

    private function rawPaginated(Query $query, array $select, string $orderColumn): array
    {
        $page = max(1, (int) Yii::$app->request->get('page', 1) ?: 1);
        $perPage = min(100, max(1, (int) Yii::$app->request->get('per_page', 15) ?: 15));

        $total = (int) (clone $query)->count();
        $rows = (clone $query)
            ->select($select)
            ->orderBy([$orderColumn => SORT_DESC])
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->all();

        $data = array_map(function ($row) {
            $out = [];
            foreach ($row as $key => $value) {
                if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                    $out[$key] = PurchaseOrder::toIso($value);
                } elseif (is_numeric($value) && in_array($key, ['id', 'partner_id', 'company_id', 'currency_id'], true)) {
                    $out[$key] = $value === null ? null : (int) $value;
                } elseif (is_numeric($value) && in_array($key, ['amount_untaxed', 'amount_tax', 'amount_total'], true)) {
                    $out[$key] = (float) $value;
                } else {
                    $out[$key] = $value;
                }
            }
            return $out;
        }, $rows);

        return $this->paginatedEnvelope($data, $page, $perPage, $total);
    }

    private function transition($id, string $state, string $message): array
    {
        $cfg = $this->cfg();
        $this->authorize('update', $cfg);
        $order = $this->findModel($cfg, $id);

        $order->state = $state;
        $order->save(false);
        $order = (new PurchaseCalculator())->recompute($order);

        return [
            'data' => $this->serialize($order, $cfg, true),
            'message' => $message,
        ];
    }

    private function validatedLines(?array $lines, bool $required): array
    {
        if ($lines === null) {
            if ($required) {
                throw new ValidationException(['lines' => ['The lines field is required.']]);
            }
            return [];
        }
        if (!is_array($lines) || $lines === []) {
            throw new ValidationException(['lines' => [$required ? 'The lines field is required.' : 'The lines field must be an array.']]);
        }

        $errors = [];
        foreach ($lines as $i => $line) {
            if (!is_array($line)) {
                $errors["lines.{$i}"][] = "The lines.{$i} field must be an array.";
                continue;
            }

            $key = "lines.{$i}";
            $productId = $line['product_id'] ?? null;
            if ($productId === null || !is_numeric($productId)) {
                $errors["{$key}.product_id"][] = "The {$key}.product_id field is required.";
            } elseif (!(new Query())->from('products_products')->where(['id' => (int) $productId])->exists()) {
                $errors["{$key}.product_id"][] = "The selected {$key}.product_id is invalid.";
            }

            if (empty($line['planned_at']) || strtotime((string) $line['planned_at']) === false) {
                $errors["{$key}.planned_at"][] = "The {$key}.planned_at field is required.";
            }

            $qty = $line['product_qty'] ?? null;
            if ($qty === null || !is_numeric($qty)) {
                $errors["{$key}.product_qty"][] = "The {$key}.product_qty field is required.";
            }

            $price = $line['price_unit'] ?? null;
            if ($price === null || !is_numeric($price)) {
                $errors["{$key}.price_unit"][] = "The {$key}.price_unit field is required.";
            }

            $discount = $line['discount'] ?? null;
            if ($discount !== null && is_numeric($discount) && ((float) $discount < 0 || (float) $discount > 100)) {
                $errors["{$key}.discount"][] = "The {$key}.discount field must be between 0 and 100.";
            }

            $taxes = $line['taxes'] ?? null;
            if ($taxes !== null) {
                if (!is_array($taxes)) {
                    $errors["{$key}.taxes"][] = "The {$key}.taxes field must be an array.";
                } else {
                    foreach ($taxes as $j => $taxId) {
                        if (!is_numeric($taxId) || !(new Query())->from('accounts_taxes')->where(['id' => (int) $taxId])->exists()) {
                            $errors["{$key}.taxes.{$j}"][] = "The selected {$key}.taxes.{$j} is invalid.";
                        }
                    }
                }
            }

            $lineId = $line['id'] ?? null;
            if ($lineId !== null && (!is_numeric($lineId) || !(new Query())->from('purchases_order_lines')->where(['id' => (int) $lineId])->exists())) {
                $errors["{$key}.id"][] = "The selected {$key}.id is invalid.";
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $lines;
    }

    private function syncLines(PurchaseOrder $order, array $linesData): void
    {
        $submittedIds = [];
        foreach ($linesData as $lineData) {
            if (!empty($lineData['id'])) {
                $submittedIds[] = (int) $lineData['id'];
            }
        }

        $db = Yii::$app->db;
        if ($submittedIds === []) {
            $db->createCommand()->delete('purchases_order_lines', ['order_id' => $order->id])->execute();
        } else {
            $db->createCommand()->delete('purchases_order_lines', [
                'and',
                ['order_id' => $order->id],
                ['not in', 'id', $submittedIds],
            ])->execute();
        }

        $allowed = [
            'product_id', 'planned_at', 'product_qty', 'uom_id', 'product_packaging_qty',
            'product_packaging_id', 'price_unit', 'discount', 'qty_received', 'qty_received_manual',
        ];

        foreach ($linesData as $lineData) {
            $taxes = $lineData['taxes'] ?? null;
            $lineId = $lineData['id'] ?? null;
            unset($lineData['taxes'], $lineData['id']);
            $lineData = array_intersect_key($lineData, array_flip($allowed));

            $product = isset($lineData['product_id'])
                ? Product::findUnscoped()->withTrashed()->where(['id' => $lineData['product_id']])->one()
                : null;

            $payload = array_merge([
                'order_id' => $order->id,
                'name' => $product?->name ?? 'Line Item',
                'state' => $order->state,
                'uom_id' => $lineData['uom_id'] ?? $product?->uom_id,
                'partner_id' => $order->partner_id,
                'currency_id' => $order->currency_id,
                'company_id' => $order->company_id,
                'product_qty' => $lineData['product_qty'] ?? 0,
                'product_uom_qty' => $lineData['product_qty'] ?? 0,
                'price_unit' => $lineData['price_unit'] ?? ($product ? (float) $product->price : 0),
                'discount' => $lineData['discount'] ?? 0,
                'qty_received' => $lineData['qty_received'] ?? 0,
                'qty_invoiced' => 0,
                'qty_to_invoice' => 0,
                'price_total_cc' => 0,
            ], $lineData);

            if ($lineId) {
                $line = PurchaseOrderLine::findUnscoped()->where(['id' => $lineId, 'order_id' => $order->id])->one();
                if ($line === null) {
                    continue;
                }
                foreach ($payload as $key => $value) {
                    $line->setAttribute($key, $value);
                }
                $line->save(false);
            } else {
                $line = new PurchaseOrderLine();
                $line->setAttributes($payload, false);
                $line->save(false);
            }

            if ($taxes !== null) {
                $db->createCommand()->delete('purchases_order_line_taxes', ['order_line_id' => $line->id])->execute();
                foreach ((array) $taxes as $taxId) {
                    $db->createCommand()->insert('purchases_order_line_taxes', [
                        'order_line_id' => $line->id,
                        'tax_id' => (int) $taxId,
                    ])->execute();
                }
            }
        }
    }
}
