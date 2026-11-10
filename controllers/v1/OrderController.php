<?php

namespace app\controllers\v1;

use Yii;
use yii\db\Query;
use app\components\ResourceController;
use app\components\SalesCalculator;
use app\components\SalesInvoicer;
use app\components\ValidationException;
use app\models\Product;
use app\models\SalesOrder;
use app\models\SalesOrderLine;

class OrderController extends ResourceController
{
    protected function verbs(): array
    {
        return array_merge(parent::verbs(), [
            'confirm' => ['POST'],
            'cancel' => ['POST'],
            'set-as-quotation' => ['POST'],
            'toggle-lock' => ['POST'],
            'invoices' => ['GET'],
            'invoice' => ['POST'],
        ]);
    }

    public function actionCreate()
    {
        $cfg = $this->cfg();
        $this->authorize('create', $cfg);
        $this->resolveExpand($cfg);

        [$input, $payload] = $this->parseBody($cfg);
        $this->validatePayload($cfg, $payload);

        $lines = $this->validatedLines($payload['lines'] ?? null, true, $input['price_list_id'] ?? null);

        $order = new SalesOrder();
        $this->loadAttributes($order, $input);
        $this->validateModel($order, $input, false);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$order->save(false)) {
                throw new ValidationException($order->getErrors());
            }
            $this->syncTags($order, $payload['sales_order_tags'] ?? null);
            $this->syncLines($order, $lines);
            $order = (new SalesCalculator())->recompute($order);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        Yii::$app->response->setStatusCode(201);

        return [
            'data' => $this->serialize($order, $cfg, true),
            'message' => 'Order created successfully.',
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
            ? $this->validatedLines($payload['lines'], false, $input['price_list_id'] ?? $order->price_list_id)
            : null;

        $this->loadAttributes($order, $input);
        $this->validateModel($order, $input, true);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$order->save(false)) {
                throw new ValidationException($order->getErrors());
            }
            if (array_key_exists('sales_order_tags', $payload)) {
                $this->syncTags($order, $payload['sales_order_tags']);
            }
            if ($lines !== null) {
                $this->syncLines($order, $lines);
            }
            $order = (new SalesCalculator())->recompute($order);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return [
            'data' => $this->serialize($order, $cfg, true),
            'message' => 'Order updated successfully.',
        ];
    }

    public function actionConfirm($id)
    {
        $cfg = $this->cfg();
        $this->authorize('update', $cfg);
        $order = $this->findModel($cfg, $id);

        if (!in_array($order->state, [SalesOrder::STATE_DRAFT, SalesOrder::STATE_SENT], true)) {
            throw new ValidationException([], 'Only draft or sent orders can be confirmed.');
        }

        $order->state = SalesOrder::STATE_SALE;
        $order->invoice_status = 'to_invoice';
        $order->save(false);
        $order = (new SalesCalculator())->recompute($order);

        return [
            'data' => $this->serialize($order, $cfg, true),
            'message' => 'Order confirmed successfully.',
        ];
    }

    public function actionCancel($id)
    {
        $cfg = $this->cfg();
        $this->authorize('update', $cfg);
        $order = $this->findModel($cfg, $id);

        if (!in_array($order->state, [SalesOrder::STATE_DRAFT, SalesOrder::STATE_SENT, SalesOrder::STATE_SALE], true)) {
            throw new ValidationException([], 'Only draft, sent, or sale orders can be canceled.');
        }

        $order->state = SalesOrder::STATE_CANCEL;
        $order->invoice_status = 'no';
        $order->save(false);
        $order = (new SalesCalculator())->recompute($order);

        return [
            'data' => $this->serialize($order, $cfg, true),
            'message' => 'Order canceled successfully.',
        ];
    }

    public function actionSetAsQuotation($id)
    {
        $cfg = $this->cfg();
        $this->authorize('update', $cfg);
        $order = $this->findModel($cfg, $id);

        if ($order->state !== SalesOrder::STATE_CANCEL) {
            throw new ValidationException([], 'Only canceled orders can be set as quotation.');
        }

        $order->state = SalesOrder::STATE_DRAFT;
        $order->invoice_status = 'no';
        $order->save(false);
        $order = (new SalesCalculator())->recompute($order);

        return [
            'data' => $this->serialize($order, $cfg, true),
            'message' => 'Order set as quotation successfully.',
        ];
    }

    public function actionToggleLock($id)
    {
        $cfg = $this->cfg();
        $this->authorize('update', $cfg);
        $order = $this->findModel($cfg, $id);

        if ($order->state !== SalesOrder::STATE_SALE) {
            throw new ValidationException([], 'Only sale orders can toggle lock state.');
        }

        $order->locked = !$order->locked;
        $order->save(false);
        $order = (new SalesCalculator())->recompute($order);

        return [
            'data' => $this->serialize($order, $cfg, true),
            'message' => 'Order lock state updated successfully.',
        ];
    }

    public function actionInvoice($id)
    {
        $cfg = $this->cfg();
        $this->authorize('update', $cfg);
        $order = $this->findModel($cfg, $id);

        $payload = Yii::$app->request->getBodyParams();
        $advancePaymentMethod = is_array($payload) ? ($payload['advance_payment_method'] ?? 'delivered') : 'delivered';

        if ($advancePaymentMethod !== 'delivered') {
            throw new ValidationException(['advance_payment_method' => ['The selected advance_payment_method is invalid.']]);
        }

        $invoice = (new SalesInvoicer())->createInvoice($order, $advancePaymentMethod);

        return [
            'data' => $this->serialize($order, $cfg, true),
            'invoice' => $invoice,
            'message' => 'Invoice created successfully.',
        ];
    }

    public function actionInvoices($order_id)
    {
        $cfg = $this->cfg();
        $this->authorize('view', $cfg);
        $order = $this->findModel($cfg, $order_id);

        $page = max(1, (int) Yii::$app->request->get('page', 1) ?: 1);
        $perPage = min(100, max(1, (int) Yii::$app->request->get('per_page', 15) ?: 15));

        $base = (new Query())
            ->from(['m' => 'accounts_account_moves'])
            ->innerJoin('sales_order_invoices soi', 'soi.move_id = m.id')
            ->where(['soi.order_id' => $order->id, 'm.move_type' => 'out_invoice']);

        $total = (int) (clone $base)->count();
        $rows = (clone $base)
            ->select([
                'm.id', 'm.name', 'm.state', 'm.payment_state', 'm.move_type', 'm.date',
                'm.invoice_date_due', 'm.amount_untaxed', 'm.amount_tax', 'm.amount_total',
                'm.partner_id', 'm.currency_id',
            ])
            ->orderBy(['m.id' => SORT_DESC])
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->all();

        $data = array_map(fn ($row) => [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'state' => $row['state'],
            'payment_state' => $row['payment_state'],
            'move_type' => $row['move_type'],
            'date' => SalesOrder::toIso($row['date']),
            'invoice_date_due' => SalesOrder::toIso($row['invoice_date_due']),
            'amount_untaxed' => (float) $row['amount_untaxed'],
            'amount_tax' => (float) $row['amount_tax'],
            'amount_total' => (float) $row['amount_total'],
            'partner_id' => $row['partner_id'] !== null ? (int) $row['partner_id'] : null,
            'currency_id' => $row['currency_id'] !== null ? (int) $row['currency_id'] : null,
        ], $rows);

        return $this->envelope($data, $page, $perPage, $total);
    }

    private function envelope(array $data, int $page, int $perPage, int $total): array
    {
        $lastPage = max(1, (int) ceil($total / $perPage));
        $request = Yii::$app->request;
        $base = strtok($request->getAbsoluteUrl(), '?');
        $queryParams = $request->getQueryParams();
        unset($queryParams['page'], $queryParams['resource'], $queryParams['order_id']);
        $make = function (int $p) use ($base, $queryParams): string {
            $params = $queryParams;
            $params['page'] = $p;
            return $base . '?' . http_build_query($params);
        };

        return [
            'data' => $data,
            'links' => [
                'first' => $make(1),
                'last' => $make($lastPage),
                'prev' => $page > 1 ? $make($page - 1) : null,
                'next' => $page < $lastPage ? $make($page + 1) : null,
            ],
            'meta' => [
                'current_page' => $page,
                'from' => $total === 0 ? null : ($page - 1) * $perPage + 1,
                'last_page' => $lastPage,
                'path' => $base,
                'per_page' => $perPage,
                'to' => $total === 0 ? null : min($total, $page * $perPage),
                'total' => $total,
            ],
        ];
    }

    private function validatedLines(?array $lines, bool $required, $priceListId): array
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
            $productId = $line['product_id'] ?? null;
            if ($productId === null || !is_numeric($productId)) {
                $errors["lines.{$i}.product_id"][] = "The lines.{$i}.product_id field is required.";
            } elseif (!(new Query())->from('products_products')->where(['id' => (int) $productId])->exists()) {
                $errors["lines.{$i}.product_id"][] = "The selected lines.{$i}.product_id is invalid.";
            }

            $qty = $line['product_qty'] ?? null;
            if ($qty === null || !is_numeric($qty)) {
                $errors["lines.{$i}.product_qty"][] = "The lines.{$i}.product_qty field is required.";
            }

            $priceUnit = $line['price_unit'] ?? null;
            if (($priceUnit === null || $priceUnit === '') && $priceListId === null) {
                $errors["lines.{$i}.price_unit"][] = "The lines.{$i}.price_unit field is required.";
            } elseif ($priceUnit !== null && $priceUnit !== '' && !is_numeric($priceUnit)) {
                $errors["lines.{$i}.price_unit"][] = "The lines.{$i}.price_unit field must be a number.";
            }

            $discount = $line['discount'] ?? null;
            if ($discount !== null && is_numeric($discount) && ((float) $discount < 0 || (float) $discount > 100)) {
                $errors["lines.{$i}.discount"][] = "The lines.{$i}.discount field must be between 0 and 100.";
            }

            $taxes = $line['taxes'] ?? null;
            if ($taxes !== null) {
                if (!is_array($taxes)) {
                    $errors["lines.{$i}.taxes"][] = "The lines.{$i}.taxes field must be an array.";
                } else {
                    foreach ($taxes as $j => $taxId) {
                        if (!is_numeric($taxId) || !(new Query())->from('accounts_taxes')->where(['id' => (int) $taxId])->exists()) {
                            $errors["lines.{$i}.taxes.{$j}"][] = "The selected lines.{$i}.taxes.{$j} is invalid.";
                        }
                    }
                }
            }

            $lineId = $line['id'] ?? null;
            if ($lineId !== null && (!is_numeric($lineId) || !(new Query())->from('sales_order_lines')->where(['id' => (int) $lineId])->exists())) {
                $errors["lines.{$i}.id"][] = "The selected lines.{$i}.id is invalid.";
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $lines;
    }

    private function syncTags(SalesOrder $order, $tagIds): void
    {
        if ($tagIds === null) {
            return;
        }
        $db = Yii::$app->db;
        $db->createCommand()->delete('sales_order_tags', ['order_id' => $order->id])->execute();
        $ids = array_values(array_unique(array_map('intval', (array) $tagIds)));
        foreach ($ids as $tagId) {
            $db->createCommand()->insert('sales_order_tags', [
                'order_id' => $order->id,
                'tag_id' => $tagId,
            ])->execute();
        }
    }

    private function syncLines(SalesOrder $order, array $linesData): void
    {
        $submittedIds = [];
        foreach ($linesData as $lineData) {
            if (!empty($lineData['id'])) {
                $submittedIds[] = (int) $lineData['id'];
            }
        }

        $db = Yii::$app->db;
        if ($submittedIds === []) {
            $db->createCommand()->delete('sales_order_lines', ['order_id' => $order->id])->execute();
        } else {
            $db->createCommand()->delete('sales_order_lines', [
                'and',
                ['order_id' => $order->id],
                ['not in', 'id', $submittedIds],
            ])->execute();
        }

        $allowed = [
            'product_id', 'product_qty', 'qty_delivered', 'product_uom_id', 'customer_lead',
            'product_packaging_qty', 'product_packaging_id', 'price_unit', 'discount', 'warehouse_id',
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
                'product_uom_id' => $lineData['product_uom_id'] ?? $product?->uom_id,
                'company_id' => $order->company_id,
                'currency_id' => $order->currency_id,
                'order_partner_id' => $order->partner_id,
                'salesman_id' => $order->user_id,
                'product_qty' => $lineData['product_qty'] ?? 0,
                'product_uom_qty' => $lineData['product_qty'] ?? 0,
                'price_unit' => $lineData['price_unit'] ?? $this->resolveLinePrice($order, $product, $lineData),
                'discount' => $lineData['discount'] ?? 0,
                'customer_lead' => $lineData['customer_lead'] ?? 0,
            ], $lineData);

            if ($lineId) {
                $line = SalesOrderLine::findUnscoped()->where(['id' => $lineId, 'order_id' => $order->id])->one();
                if ($line === null) {
                    continue;
                }
                foreach ($payload as $key => $value) {
                    $line->setAttribute($key, $value);
                }
                $line->save(false);
            } else {
                $line = new SalesOrderLine();
                $line->setAttributes($payload, false);
                $line->save(false);
            }

            if ($taxes !== null) {
                $db->createCommand()->delete('sales_order_line_taxes', ['order_line_id' => $line->id])->execute();
                foreach ((array) $taxes as $taxId) {
                    $db->createCommand()->insert('sales_order_line_taxes', [
                        'order_line_id' => $line->id,
                        'tax_id' => (int) $taxId,
                    ])->execute();
                }
            }
        }
    }

    private function resolveLinePrice(SalesOrder $order, ?Product $product, array $lineData): float
    {
        if ($product === null) {
            return 0.0;
        }

        return round((float) $product->price, 2);
    }
}
