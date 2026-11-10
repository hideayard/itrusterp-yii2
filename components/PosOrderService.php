<?php

namespace app\components;

use Yii;
use yii\db\Query;
use app\models\Partner;
use app\models\PosCashMovement;
use app\models\PosConfig;
use app\models\PosOrder;
use app\models\PosPayment;
use app\models\PosSession;

/**
 * Port sederhana dari OrderWorkflow + OrderCalculator + RefundProcessor + PosInvoicer.
 *
 * Bedanya dengan sumber:
 * - Stock posting hanya membuat `inventories_moves` + `inventories_move_lines` + update
 *   `inventories_product_quantities` (tanpa picking/route/putaway).
 * - Invoice hanya membuat satu jurnal penjualan (piutang + pendapatan per baris), tanpa COGS.
 */
class PosOrderService
{
    public function __construct(private int $userId)
    {
    }

    public function syncOrders(array $orders): array
    {
        $data = [];
        $errors = [];

        foreach (array_values($orders) as $index => $payload) {
            try {
                $data[] = $this->syncOne($payload, $errors, $index);
            } catch (ValidationException $e) {
                $errors["orders.{$index}"] = array_merge($errors["orders.{$index}"] ?? [], $this->flatten($e->errors));
            }
        }

        return ['data' => array_values(array_filter($data)), 'errors' => $errors];
    }

    private function flatten(array $errors): array
    {
        $flat = [];
        foreach ($errors as $messages) {
            foreach ((array) $messages as $message) {
                $flat[] = $message;
            }
        }
        return $flat;
    }

    private function syncOne(array $payload, array &$errors, int $index): ?PosOrder
    {
        $config = PosConfig::find()->where(['id' => (int) ($payload['config_id'] ?? 0)])->one();
        if ($config === null) {
            throw new ValidationException(['config_id' => ['The selected config_id is invalid.']]);
        }

        $sessionId = isset($payload['session_id']) ? (int) $payload['session_id'] : null;
        $session = $sessionId !== null ? PosSession::findOne(['id' => $sessionId]) : $this->liveSession($config);
        if ($session === null || $session->state === PosSession::STATE_CLOSED) {
            throw new ValidationException(['session_id' => ['No open session was found for this register.']]);
        }

        $lines = $payload['lines'] ?? null;
        if (!is_array($lines) || $lines === []) {
            throw new ValidationException(['lines' => ['The lines field is required.']]);
        }
        $payments = $payload['payments'] ?? null;
        if (!is_array($payments) || $payments === []) {
            throw new ValidationException(['payments' => ['The payments field is required.']]);
        }

        $uuid = (string) ($payload['uuid'] ?? '');
        if ($uuid === '' || !self::isUuid($uuid)) {
            throw new ValidationException(['uuid' => ['The uuid field must be a valid UUID.']]);
        }

        $companyId = (int) ($config->company_id ?? 0);
        $partnerId = $this->resolvePartner($payload, $companyId);

        $order = PosOrder::findOne(['uuid' => $uuid]);
        $isNew = $order === null;
        if ($isNew) {
            $order = new PosOrder();
            $order->uuid = $uuid;
            $order->setAttributes([
                'config_id' => $config->id,
                'session_id' => $session->id,
                'company_id' => $companyId,
                'currency_id' => $config->currency_id,
                'state' => PosOrder::STATE_DRAFT,
                'sequence_number' => 0,
                'ordered_at' => $payload['ordered_at'] ?? date('Y-m-d H:i:s'),
                'creator_id' => $this->userId,
                'user_id' => $this->userId,
                'amount_untaxed' => 0,
                'amount_tax' => 0,
                'amount_total' => 0,
                'amount_paid' => 0,
                'amount_return' => 0,
                'amount_difference' => 0,
                'total_cost' => 0,
                'margin' => 0,
                'margin_percent' => 0,
                'tip_amount' => 0,
                'amount_rounding' => 0,
            ], false);
        } elseif ($order->state !== PosOrder::STATE_DRAFT) {
            throw new ValidationException(['uuid' => ['Only draft orders can be synced.']]);
        }

        foreach ([
            'reference', 'tracking_number', 'table_id', 'shipped_at', 'price_list_id',
            'fiscal_position_id', 'is_to_invoice', 'is_takeaway', 'customer_count', 'floating_name',
            'is_booked', 'note', 'email', 'mobile', 'refunded_order_id',
        ] as $field) {
            if (array_key_exists($field, $payload)) {
                $order->setAttribute($field, $payload[$field]);
            }
        }
        if ($partnerId !== null) {
            $order->partner_id = $partnerId;
        }
        if (!$order->save(false)) {
            throw new ValidationException([], 'Unable to save the order.');
        }

        $this->syncLines($order, $lines, $companyId);
        $this->syncPayments($order, $payments, $session);

        $order->refresh();
        $order = $this->recompute($order);

        $this->refreshSessionTotals($session);

        return $order;
    }

    private function resolvePartner(array $payload, int $companyId): ?int
    {
        if (!empty($payload['partner_id'])) {
            return (int) $payload['partner_id'];
        }
        $partner = $payload['partner'] ?? null;
        if (!is_array($partner) || empty($partner['name'])) {
            return null;
        }

        $existing = Partner::find()
            ->where(['name' => $partner['name']])
            ->andWhere(['company_id' => $companyId ?: null])
            ->one();
        if ($existing !== null) {
            return (int) $existing->id;
        }

        $model = new Partner();
        $model->setAttributes([
            'name' => $partner['name'],
            'email' => $partner['email'] ?? null,
            'phone' => $partner['phone'] ?? null,
            'mobile' => $partner['mobile'] ?? null,
            'street1' => $partner['street1'] ?? null,
            'city' => $partner['city'] ?? null,
            'zip' => $partner['zip'] ?? null,
            'account_type' => 'individual',
            'company_id' => $companyId ?: null,
            'creator_id' => $this->userId,
        ], false);
        $model->save(false);

        return (int) $model->id;
    }

    private function syncLines(PosOrder $order, array $lines, int $companyId): void
    {
        $db = Yii::$app->db;
        $seen = [];

        foreach ($lines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $product = (new Query())->from('products_products')->where(['id' => $productId])->one();
            if ($product === null) {
                throw new ValidationException(['product_id' => ['The selected product_id is invalid.']]);
            }
            $qty = (float) ($line['qty'] ?? 0);
            if ($qty == 0.0) {
                throw new ValidationException(['qty' => ['The qty field must not be zero.']]);
            }

            $lineUuid = (string) ($line['uuid'] ?? '');
            if ($lineUuid === '' || !self::isUuid($lineUuid)) {
                throw new ValidationException(['uuid' => ['The line uuid field must be a valid UUID.']]);
            }

            $priceUnit = $line['price_unit'] ?? null;
            $priceUnit = $priceUnit === null ? (float) $product['price'] : (float) $priceUnit;
            $discount = (float) ($line['discount'] ?? 0);

            $attributes = [
                'order_id' => $order->id,
                'company_id' => $companyId,
                'product_id' => $productId,
                'name' => $product['name'],
                'full_product_name' => $line['full_product_name'] ?? $product['name'],
                'uom_id' => $product['uom_id'],
                'price_list_id' => $line['price_list_id'] ?? $order->price_list_id,
                'qty' => $qty,
                'price_unit' => $priceUnit,
                'price_extra' => 0,
                'price_type' => $line['price_type'] ?? 'original',
                'discount' => $discount,
                'price_subtotal' => 0,
                'price_subtotal_incl' => 0,
                'price_tax' => 0,
                'unit_cost' => (float) ($product['cost'] ?? 0),
                'total_cost' => 0,
                'margin' => 0,
                'margin_percent' => 0,
                'refunded_qty' => 0,
                'customer_note' => $line['customer_note'] ?? null,
                'note' => $line['note'] ?? null,
                'is_cost_computed' => 0,
                'is_edited' => 0,
                'is_skipped_in_preparation' => 0,
                'creator_id' => $this->userId,
            ];
            if (!empty($line['refunded_order_line_id'])) {
                $attributes['refunded_order_line_id'] = (int) $line['refunded_order_line_id'];
            }

            $existing = (new Query())->from('pos_order_lines')
                ->where(['order_id' => $order->id, 'uuid' => $lineUuid])->one();

            if ($existing) {
                $db->createCommand()->update('pos_order_lines', $attributes, ['id' => $existing['id']])->execute();
                $lineId = (int) $existing['id'];
            } else {
                $attributes['uuid'] = $lineUuid;
                $db->createCommand()->insert('pos_order_lines', $attributes)->execute();
                $lineId = (int) $db->getLastInsertID();
            }
            $seen[] = $lineId;

            $db->createCommand()->delete('pos_order_line_taxes', ['order_line_id' => $lineId])->execute();
            foreach ((array) ($line['tax_ids'] ?? []) as $taxId) {
                $db->createCommand()->insert('pos_order_line_taxes', [
                    'order_line_id' => $lineId,
                    'tax_id' => (int) $taxId,
                ])->execute();
            }
        }

        $delete = ['order_id' => $order->id];
        if ($seen !== []) {
            $delete = ['and', $delete, ['not in', 'id', $seen]];
        }
        $db->createCommand()->delete('pos_order_lines', $delete)->execute();
    }

    private function syncPayments(PosOrder $order, array $payments, PosSession $session): void
    {
        $db = Yii::$app->db;
        $seen = [];

        foreach ($payments as $payment) {
            $methodId = (int) ($payment['payment_method_id'] ?? 0);
            $exists = (new Query())->from('pos_payment_methods')->where(['id' => $methodId])->exists();
            if (!$exists) {
                throw new ValidationException(['payment_method_id' => ['The selected payment_method_id is invalid.']]);
            }
            $uuid = (string) ($payment['uuid'] ?? '');
            if ($uuid === '' || !self::isUuid($uuid)) {
                throw new ValidationException(['uuid' => ['The payment uuid field must be a valid UUID.']]);
            }

            $attributes = [
                'order_id' => $order->id,
                'session_id' => $session->id,
                'payment_method_id' => $methodId,
                'company_id' => $order->company_id,
                'amount' => (float) ($payment['amount'] ?? 0),
                'currency_rate' => 1,
                'is_change' => 0,
                'paid_at' => $payment['paid_at'] ?? date('Y-m-d H:i:s'),
                'user_id' => $this->userId,
                'creator_id' => $this->userId,
            ];

            $existing = (new Query())->from('pos_payments')
                ->where(['order_id' => $order->id, 'uuid' => $uuid])->one();
            if ($existing) {
                $db->createCommand()->update('pos_payments', $attributes, ['id' => $existing['id']])->execute();
                $seen[] = (int) $existing['id'];
            } else {
                $attributes['uuid'] = $uuid;
                $db->createCommand()->insert('pos_payments', $attributes)->execute();
                $seen[] = (int) $db->getLastInsertID();
            }
        }

        $delete = ['and', ['order_id' => $order->id], ['is_change' => 0]];
        if ($seen !== []) {
            $delete = ['and', $delete, ['not in', 'id', $seen]];
        }
        $db->createCommand()->delete('pos_payments', $delete)->execute();
    }

    public function recompute(PosOrder $order): PosOrder
    {
        $db = Yii::$app->db;
        $lines = (new Query())->from('pos_order_lines')->where(['order_id' => $order->id])->all();

        $untaxed = 0.0;
        $tax = 0.0;
        $totalCost = 0.0;

        foreach ($lines as $line) {
            $taxIds = (new Query())->from('pos_order_line_taxes')
                ->select('tax_id')->where(['order_line_id' => (int) $line['id']])->column();
            $taxes = $taxIds === [] ? [] : (new Query())->from('accounts_taxes')
                ->where(['id' => array_map('intval', $taxIds)])->all();

            $unit = (float) $line['price_unit'];
            if ((float) $line['discount'] > 0) {
                $unit *= 1 - ((float) $line['discount'] / 100);
            }
            $qty = (float) $line['qty'];
            $subtotal = round($unit * $qty, 4);
            $lineTax = 0.0;
            foreach ($taxes as $taxRow) {
                if (($taxRow['amount_type'] ?? 'percent') === 'percent') {
                    $lineTax += $subtotal * ((float) $taxRow['amount'] / 100);
                } else {
                    $lineTax += (float) $taxRow['amount'] * abs($qty);
                }
            }
            $lineTax = round($lineTax, 4);

            $unitCost = (float) $line['unit_cost'];
            $lineCost = round($unitCost * $qty, 4);

            $db->createCommand()->update('pos_order_lines', [
                'price_subtotal' => $subtotal,
                'price_tax' => $lineTax,
                'price_subtotal_incl' => round($subtotal + $lineTax, 4),
                'total_cost' => $lineCost,
                'margin' => round($subtotal - $lineCost, 4),
                'margin_percent' => round($subtotal, 4) == 0 ? 0 : round(($subtotal - $lineCost) / $subtotal, 4),
                'is_cost_computed' => 1,
            ], ['id' => (int) $line['id']])->execute();

            $untaxed += $subtotal;
            $tax += $lineTax;
            $totalCost += $lineCost;
        }

        $amountUntaxed = round($untaxed, 4);
        $amountTax = round($tax, 4);
        $amountTotal = round($amountUntaxed + $amountTax, 4);

        $paid = (float) (new Query())->from('pos_payments')
            ->where(['order_id' => $order->id, 'is_change' => 0])->sum('amount');
        $paid = round($paid, 2);

        $isRefund = $amountTotal < 0;
        $covered = $isRefund ? $paid <= $amountTotal : $paid >= $amountTotal;
        $change = $isRefund ? 0.0 : round($paid - $amountTotal, 2);

        if ($covered && $change > 0) {
            $this->registerChange($order, $change);
        }

        $state = $covered ? PosOrder::STATE_PAID : PosOrder::STATE_DRAFT;
        if ($covered && !$order->name) {
            $order->name = $this->nextOrderName($order);
        }

        $settled = $covered ? $paid : $paid;
        $db->createCommand()->update('pos_orders', [
            'amount_untaxed' => $amountUntaxed,
            'amount_tax' => $amountTax,
            'amount_total' => $amountTotal,
            'amount_paid' => $settled,
            'amount_difference' => round($settled - $amountTotal, 4),
            'amount_return' => max($change, 0),
            'total_cost' => round($totalCost, 4),
            'margin' => round($amountUntaxed - $totalCost, 4),
            'margin_percent' => $amountUntaxed == 0 ? 0 : round(($amountUntaxed - $totalCost) / $amountUntaxed, 4),
            'state' => $state,
            'confirmed_at' => $covered ? date('Y-m-d H:i:s') : null,
            'name' => $order->name,
        ], ['id' => $order->id])->execute();

        $order->refresh();

        if ($covered && $order->operation_id === null) {
            $this->generateStock($order);
        }

        return $order;
    }

    private function registerChange(PosOrder $order, float $change): void
    {
        $config = $order->config_id !== null ? PosConfig::findOne(['id' => $order->config_id]) : null;
        if ($config === null) {
            return;
        }
        $cashMethodId = (new Query())->from('pos_payment_methods')
            ->where(['is_cash_count' => 1])
            ->andWhere(['or', ['company_id' => $config->company_id], ['company_id' => null]])
            ->select('id')->scalar();
        if (!$cashMethodId) {
            return;
        }

        $exists = (new Query())->from('pos_payments')->where([
            'order_id' => $order->id,
            'is_change' => 1,
        ])->exists();
        if ($exists) {
            return;
        }

        Yii::$app->db->createCommand()->insert('pos_payments', [
            'uuid' => self::uuid4(),
            'order_id' => $order->id,
            'session_id' => $order->session_id,
            'payment_method_id' => (int) $cashMethodId,
            'company_id' => $order->company_id,
            'amount' => -$change,
            'currency_rate' => 1,
            'is_change' => 1,
            'paid_at' => date('Y-m-d H:i:s'),
            'user_id' => $this->userId,
            'creator_id' => $this->userId,
        ])->execute();
    }

    private function nextOrderName(PosOrder $order): string
    {
        return SequenceService::next('point_of_sale.order', $order->company_id !== null ? (int) $order->company_id : null, [
            'name' => 'Point of Sale Order',
            'prefix' => 'POS/',
            'padding' => 5,
        ]);
    }

    private function generateStock(PosOrder $order): void
    {
        $config = $order->config_id !== null ? PosConfig::findOne(['id' => $order->config_id]) : null;
        if ($config === null || $config->warehouse_id === null || $order->operation_id !== null) {
            return;
        }
        $session = $order->session_id !== null ? PosSession::findOne(['id' => $order->session_id]) : null;
        if ($session !== null && $session->stock_update_mode === 'at_closing') {
            return;
        }

        $isRefund = $order->refunded_order_id !== null;
        $operationTypeId = $isRefund
            ? ($config->return_operation_type_id ?? $config->operation_type_id)
            : $config->operation_type_id;
        if ($operationTypeId === null) {
            return;
        }

        $operationType = (new Query())->from('inventories_operation_types')
            ->where(['id' => (int) $operationTypeId])->one();
        if ($operationType === null) {
            return;
        }

        $warehouse = (new Query())->from('inventories_warehouses')
            ->where(['id' => (int) $config->warehouse_id])->one();
        $stockLocationId = $warehouse['lot_stock_location_id'] ?? null;

        $sourceId = $operationType['source_location_id'] ?? null;
        $destinationId = $operationType['destination_location_id'] ?? null;
        if ($sourceId === null && $destinationId === null) {
            if (($operationType['type'] ?? '') === 'outgoing') {
                $sourceId = $stockLocationId;
            } else {
                $destinationId = $stockLocationId;
            }
        }

        $lines = (new Query())->from('pos_order_lines')->where(['order_id' => $order->id])->all();
        $db = Yii::$app->db;

        $operationId = null;
        foreach ($lines as $line) {
            $product = (new Query())->from('products_products')->where(['id' => (int) $line['product_id']])->one();
            if ($product === null || !(int) $product['is_storable']) {
                continue;
            }
            $qty = abs((float) $line['qty']);
            if ($qty == 0.0) {
                continue;
            }

            if ($operationId === null) {
                $db->createCommand()->insert('inventories_operations', [
                    'name' => $order->name ?? ('POS/' . $order->id),
                    'origin' => $order->name ?? $order->reference,
                    'move_type' => $isRefund ? 'incoming' : 'outgoing',
                    'state' => 'done',
                    'is_favorite' => 0,
                    'has_deadline_issue' => 0,
                    'is_printed' => 0,
                    'is_locked' => 0,
                    'scheduled_at' => date('Y-m-d H:i:s'),
                    'closed_at' => date('Y-m-d H:i:s'),
                    'user_id' => $this->userId,
                    'owner_id' => $this->userId,
                    'operation_type_id' => $operationTypeId,
                    'source_location_id' => $sourceId,
                    'destination_location_id' => $destinationId,
                    'partner_id' => $order->partner_id,
                    'company_id' => $order->company_id,
                    'creator_id' => $this->userId,
                ])->execute();
                $operationId = (int) $db->getLastInsertID();
                $db->createCommand()->update('pos_orders', ['operation_id' => $operationId], ['id' => $order->id])->execute();
                $order->operation_id = $operationId;
            }

            $moveName = ($order->name ?? 'POS') . ' - ' . $product['name'];
            $db->createCommand()->insert('inventories_moves', [
                'name' => $moveName,
                'state' => 'done',
                'origin' => $order->name ?? $order->reference,
                'reference' => $order->name,
                'product_id' => $product['id'],
                'uom_id' => $line['uom_id'] ?? $product['uom_id'],
                'product_qty' => $qty,
                'product_uom_qty' => $qty,
                'quantity' => $qty,
                'is_picked' => 0,
                'is_scraped' => 0,
                'is_inventory' => 0,
                'source_location_id' => $sourceId,
                'destination_location_id' => $destinationId,
                'warehouse_id' => $config->warehouse_id,
                'operation_type_id' => $operationTypeId,
                'operation_id' => $operationId,
                'company_id' => $order->company_id,
                'partner_id' => $order->partner_id,
                'creator_id' => $this->userId,
                'is_refund' => $isRefund ? 1 : 0,
                'scheduled_at' => date('Y-m-d H:i:s'),
            ])->execute();
            $moveId = (int) $db->getLastInsertID();

            $db->createCommand()->insert('inventories_move_lines', [
                'move_id' => $moveId,
                'operation_id' => $operationId,
                'product_id' => $product['id'],
                'uom_id' => $line['uom_id'] ?? $product['uom_id'],
                'qty' => $qty,
                'uom_qty' => $qty,
                'state' => 'done',
                'is_picked' => 0,
                'source_location_id' => $sourceId,
                'destination_location_id' => $destinationId,
                'company_id' => $order->company_id,
                'partner_id' => $order->partner_id,
                'creator_id' => $this->userId,
                'scheduled_at' => date('Y-m-d H:i:s'),
            ])->execute();

            if ($stockLocationId !== null) {
                $delta = $isRefund ? $qty : -$qty;
                $this->adjustQuantity((int) $product['id'], (int) $stockLocationId, $delta, (int) $order->company_id);
            }
        }
    }

    private function adjustQuantity(int $productId, int $locationId, float $delta, int $companyId): void
    {
        $db = Yii::$app->db;
        $row = (new Query())->from('inventories_product_quantities')->where([
            'product_id' => $productId,
            'location_id' => $locationId,
        ])->one();

        if ($row === null) {
            $db->createCommand()->insert('inventories_product_quantities', [
                'product_id' => $productId,
                'location_id' => $locationId,
                'company_id' => $companyId ?: null,
                'quantity' => $delta,
                'reserved_quantity' => 0,
                'creator_id' => $this->userId,
            ])->execute();
            return;
        }

        $db->createCommand()->update('inventories_product_quantities', [
            'quantity' => round((float) $row['quantity'] + $delta, 4),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => (int) $row['id']])->execute();
    }

    public function refundableLines(PosOrder $order): array
    {
        if ($order->refunded_order_id !== null) {
            return [];
        }

        $rows = (new Query())->from('pos_order_lines')->where(['order_id' => $order->id])->all();
        $result = [];
        foreach ($rows as $row) {
            $refundable = round((float) $row['qty'] - (float) $row['refunded_qty'], 4);
            if ($refundable > 0) {
                $result[(int) $row['id']] = $refundable;
            }
        }
        return $result;
    }

    public function pendingRefund(PosOrder $order, array $quantities): array
    {
        if ($order->refunded_order_id !== null || !in_array($order->state, [PosOrder::STATE_PAID, PosOrder::STATE_DONE, PosOrder::STATE_INVOICED], true)) {
            throw new ValidationException(['order' => ['This order is not refundable.']]);
        }

        $lines = (new Query())->from('pos_order_lines')->where(['order_id' => $order->id])->all();
        $byId = [];
        foreach ($lines as $line) {
            $byId[(int) $line['id']] = $line;
        }

        $resultLines = [];
        $products = [];

        foreach ($quantities as $lineId => $quantity) {
            $lineId = (int) $lineId;
            $quantity = abs((float) $quantity);
            if ($quantity == 0.0 || !isset($byId[$lineId])) {
                continue;
            }
            $line = $byId[$lineId];
            $refundable = round((float) $line['qty'] - (float) $line['refunded_qty'], 4);
            if ($quantity > $refundable) {
                throw new ValidationException(['quantities' => ['The refund quantity exceeds the sold quantity.']]);
            }

            $taxIds = (new Query())->from('pos_order_line_taxes')
                ->select('tax_id')->where(['order_line_id' => $lineId])->column();

            $resultLines[] = [
                'product_id' => (int) $line['product_id'],
                'qty' => -$quantity,
                'price_unit' => (float) $line['price_unit'],
                'discount' => (float) $line['discount'],
                'note' => $line['customer_note'],
                'tax_ids' => array_map('intval', $taxIds),
                'lots' => [],
                'refunded_order_line_id' => $lineId,
            ];

            $products[(int) $line['product_id']] = [
                'id' => (int) $line['product_id'],
                'name' => $line['full_product_name'] ?? $line['name'],
                'price' => (float) $line['price_unit'],
                'uom_id' => $line['uom_id'] !== null ? (int) $line['uom_id'] : null,
                'tax_ids' => array_map('intval', $taxIds),
            ];
        }

        if ($resultLines === []) {
            throw new ValidationException(['quantities' => ['There is nothing to refund.']]);
        }

        return [
            'refunded_order_id' => (int) $order->id,
            'partner_id' => $order->partner_id !== null ? (int) $order->partner_id : null,
            'price_list_id' => $order->price_list_id !== null ? (int) $order->price_list_id : null,
            'fiscal_position_id' => $order->fiscal_position_id !== null ? (int) $order->fiscal_position_id : null,
            'lines' => $resultLines,
            'products' => array_values($products),
        ];
    }

    public function cancelDraftOrders(PosSession $session): int
    {
        $ids = (new Query())->from('pos_orders')
            ->select('id')
            ->where(['session_id' => $session->id, 'state' => PosOrder::STATE_DRAFT])
            ->column();

        if ($ids === []) {
            return 0;
        }

        Yii::$app->db->createCommand()->update('pos_orders', [
            'state' => PosOrder::STATE_CANCELED,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => array_map('intval', $ids)])->execute();

        return count($ids);
    }

    public function invoice(PosOrder $order): PosOrder
    {
        $config = $order->config_id !== null ? PosConfig::findOne(['id' => $order->config_id]) : null;
        if ($config === null || $config->invoice_journal_id === null || $config->receivable_account_id === null) {
            throw new ValidationException(['order' => ['Invoice journal and receivable account are not configured for this register.']]);
        }
        if ($order->account_move_id !== null) {
            return $order;
        }
        if ($order->partner_id === null) {
            throw new ValidationException(['partner_id' => ['A customer is required to invoice this order.']]);
        }

        $db = Yii::$app->db;
        $lines = (new Query())->from('pos_order_lines')->where(['order_id' => $order->id])->all();
        if ($lines === []) {
            throw new ValidationException(['order' => ['This order has no lines to invoice.']]);
        }

        $incomeAccountId = (int) (new Query())->from('products_products')
            ->select('property_account_income_id')
            ->where(['id' => (int) $lines[0]['product_id']])->scalar();
        if (!$incomeAccountId) {
            $incomeAccountId = (int) (new Query())->from('accounts_accounts')
                ->where(['account_type' => 'income'])
                ->select('id')->scalar();
        }
        if (!$incomeAccountId) {
            throw new ValidationException(['order' => ['No income account is available to build the invoice.']]);
        }

        $moveName = SequenceService::next('account.move', $order->company_id !== null ? (int) $order->company_id : null, [
            'name' => 'Account Move',
            'prefix' => 'INV/',
            'padding' => 5,
        ]);

        $total = (float) $order->amount_total;

        $db->createCommand()->insert('accounts_account_moves', [
            'journal_id' => $config->invoice_journal_id,
            'company_id' => $order->company_id,
            'partner_id' => $order->partner_id,
            'currency_id' => $order->currency_id,
            'creator_id' => $this->userId,
            'name' => $moveName,
            'reference' => $order->name ?? $order->reference,
            'state' => 'draft',
            'move_type' => 'out_invoice',
            'date' => date('Y-m-d'),
            'invoice_date' => date('Y-m-d'),
            'invoice_date_due' => date('Y-m-d'),
            'invoice_origin' => $order->name,
            'amount_untaxed' => $order->amount_untaxed,
            'amount_tax' => $order->amount_tax,
            'amount_total' => $total,
            'amount_residual' => $total,
        ])->execute();
        $moveId = (int) $db->getLastInsertID();

        $lines[] = null;

        $db->createCommand()->insert('accounts_account_move_lines', [
            'move_id' => $moveId,
            'journal_id' => $config->invoice_journal_id,
            'company_id' => $order->company_id,
            'account_id' => $config->receivable_account_id,
            'partner_id' => $order->partner_id,
            'currency_id' => $order->currency_id,
            'product_id' => null,
            'name' => $order->name ?? 'POS Invoice',
            'display_type' => 'payment_term',
            'date' => date('Y-m-d'),
            'debit' => $total,
            'credit' => 0,
            'balance' => $total,
            'amount_currency' => $total,
            'price_unit' => $total,
            'price_subtotal' => $total,
            'price_total' => $total,
            'quantity' => 1,
            'reconciled' => 0,
            'creator_id' => $this->userId,
        ])->execute();

        foreach ($lines as $line) {
            if ($line === null) {
                continue;
            }
            $subtotal = (float) $line['price_subtotal'];
            $db->createCommand()->insert('accounts_account_move_lines', [
                'move_id' => $moveId,
                'journal_id' => $config->invoice_journal_id,
                'company_id' => $order->company_id,
                'account_id' => $incomeAccountId,
                'partner_id' => $order->partner_id,
                'currency_id' => $order->currency_id,
                'product_id' => $line['product_id'],
                'uom_id' => $line['uom_id'],
                'name' => $line['full_product_name'] ?? $line['name'],
                'display_type' => 'product',
                'date' => date('Y-m-d'),
                'debit' => 0,
                'credit' => $subtotal,
                'balance' => -$subtotal,
                'amount_currency' => -$subtotal,
                'price_unit' => (float) $line['price_unit'],
                'price_subtotal' => $subtotal,
                'price_total' => (float) $line['price_subtotal_incl'],
                'discount' => (float) $line['discount'],
                'quantity' => (float) $line['qty'],
                'reconciled' => 0,
                'creator_id' => $this->userId,
            ])->execute();
        }

        $db->createCommand()->update('pos_orders', [
            'account_move_id' => $moveId,
            'is_invoiced' => 1,
            'is_to_invoice' => 0,
            'state' => PosOrder::STATE_INVOICED,
        ], ['id' => $order->id])->execute();

        $order->refresh();

        return $order;
    }

    public function invoiceHtml(PosOrder $order): string
    {
        $config = $order->config_id !== null ? PosConfig::findOne(['id' => (int) $order->config_id]) : null;
        $partner = $order->partner_id !== null ? Partner::findOne(['id' => $order->partner_id]) : null;
        $lines = (new Query())->from('pos_order_lines')->where(['order_id' => $order->id])->all();
        $currency = $order->currency?->name;

        $rows = '';
        foreach ($lines as $line) {
            $rows .= '<tr><td>' . htmlspecialchars((string) ($line['full_product_name'] ?? $line['name'])) . '</td>'
                . '<td style="text-align:right">' . (float) $line['qty'] . '</td>'
                . '<td style="text-align:right">' . number_format((float) $line['price_unit'], 2) . '</td>'
                . '<td style="text-align:right">' . number_format((float) $line['price_subtotal_incl'], 2) . '</td></tr>';
        }

        return '<!doctype html><html><head><meta charset="utf-8"><title>Invoice '
            . htmlspecialchars((string) ($order->name ?? $order->id)) . '</title>'
            . '<style>body{font-family:system-ui,sans-serif;padding:32px;color:#111}h1{font-size:20px}table{width:100%;border-collapse:collapse;margin-top:24px}th,td{padding:8px;border-bottom:1px solid #e5e7eb;font-size:13px}th{text-align:left;background:#f9fafb}.total{margin-top:16px;text-align:right;font-size:15px}</style>'
            . '</head><body><h1>Invoice ' . htmlspecialchars((string) ($order->name ?? $order->id)) . '</h1>'
            . '<p>' . htmlspecialchars($partner?->name ?? '—') . '<br>' . htmlspecialchars((string) ($order->email ?? '')) . '</p>'
            . '<p>Register: ' . htmlspecialchars((string) ($config?->name ?? '—')) . ' · Tanggal: ' . htmlspecialchars(date('d M Y')) . '</p>'
            . '<table><thead><tr><th>Produk</th><th style="text-align:right">Qty</th><th style="text-align:right">Harga</th><th style="text-align:right">Total</th></tr></thead><tbody>'
            . $rows . '</tbody></table>'
            . '<div class="total">Untaxed: ' . number_format((float) $order->amount_untaxed, 2, ',', '.') . ' ' . htmlspecialchars((string) $currency)
            . '<br>Tax: ' . number_format((float) $order->amount_tax, 2, ',', '.') . ' ' . htmlspecialchars((string) $currency)
            . '<br><strong>Total: ' . number_format((float) $order->amount_total, 2, ',', '.') . ' ' . htmlspecialchars((string) $currency) . '</strong></div>'
            . '</body></html>';
    }

    public function receipt(PosOrder $order): array
    {
        $config = $order->config_id !== null ? PosConfig::findOne(['id' => $order->config_id]) : null;
        $lines = (new Query())->from('pos_order_lines')->where(['order_id' => $order->id])->all();
        $payments = (new Query())->from(['p' => 'pos_payments'])
            ->leftJoin('pos_payment_methods pm', 'pm.id = p.payment_method_id')
            ->where(['p.order_id' => $order->id])
            ->select(['p.amount', 'p.is_change', 'method' => 'pm.name'])
            ->all();

        $taxBreakdown = [];
        foreach ($lines as $line) {
            $taxIds = (new Query())->from('pos_order_line_taxes')
                ->select('tax_id')->where(['order_line_id' => (int) $line['id']])->column();
            foreach ($taxIds as $taxId) {
                $tax = (new Query())->from('accounts_taxes')->where(['id' => (int) $taxId])->one();
                if ($tax === null) {
                    continue;
                }
                $key = (int) $tax['id'];
                $taxBreakdown[$key] ??= ['id' => $key, 'name' => $tax['name'], 'amount' => 0.0, 'base' => 0.0];
                $taxBreakdown[$key]['amount'] += (float) $line['price_tax'];
                $taxBreakdown[$key]['base'] += (float) $line['price_subtotal'];
            }
        }

        return [
            'order' => [
                'id' => (int) $order->id,
                'uuid' => $order->uuid,
                'name' => $order->name,
                'reference' => $order->reference,
                'receipt_code' => $order->receipt_code,
                'state' => $order->state,
                'ordered_at' => $order->ordered_at,
                'is_invoiced' => (bool) $order->is_invoiced,
                'is_to_invoice' => (bool) $order->is_to_invoice,
                'account_move_id' => $order->account_move_id !== null ? (int) $order->account_move_id : null,
            ],
            'register' => [
                'name' => $config?->name,
                'header' => $config?->receipt_header,
                'footer' => $config?->receipt_footer,
            ],
            'currency' => $order->currency?->name,
            'cashier' => $order->user?->name,
            'partner' => $order->partner?->name,
            'lines' => array_map(fn ($line) => [
                'name' => $line['full_product_name'] ?? $line['name'],
                'qty' => (float) $line['qty'],
                'price_unit' => (float) $line['price_unit'],
                'discount' => (float) $line['discount'],
                'price_subtotal' => (float) $line['price_subtotal'],
                'price_tax' => (float) $line['price_tax'],
                'price_total' => (float) $line['price_subtotal_incl'],
            ], $lines),
            'payments' => array_map(fn ($payment) => [
                'method' => $payment['method'],
                'amount' => (float) $payment['amount'],
                'is_change' => (bool) $payment['is_change'],
            ], $payments),
            'taxes' => array_values($taxBreakdown),
            'totals' => [
                'amount_untaxed' => (float) $order->amount_untaxed,
                'amount_tax' => (float) $order->amount_tax,
                'amount_total' => (float) $order->amount_total,
                'amount_paid' => (float) $order->amount_paid,
                'amount_return' => (float) $order->amount_return,
            ],
        ];
    }

    public function refreshSessionTotals(PosSession $session): void
    {
        $orders = (new Query())->from('pos_orders')
            ->where(['session_id' => $session->id])
            ->andWhere(['in', 'state', [PosOrder::STATE_PAID, PosOrder::STATE_DONE, PosOrder::STATE_INVOICED]])
            ->all();
        $orderIds = array_map(fn ($row) => (int) $row['id'], $orders);

        $payments = $orderIds === [] ? [] : (new Query())->from('pos_payments')
            ->where(['session_id' => $session->id, 'is_change' => 0])
            ->andWhere(['in', 'order_id', $orderIds])
            ->all();

        $cashMethodIds = (new Query())->from('pos_payment_methods')
            ->select('id')->where(['is_cash_count' => 1])->column();
        $cashMethodIds = array_map('intval', $cashMethodIds);

        $totalPayments = 0.0;
        $cashTotal = 0.0;
        foreach ($payments as $payment) {
            $totalPayments += (float) $payment['amount'];
            if (in_array((int) $payment['payment_method_id'], $cashMethodIds, true)) {
                $cashTotal += (float) $payment['amount'];
            }
        }

        Yii::$app->db->createCommand()->update('pos_sessions', [
            'order_count' => count($orderIds),
            'total_payments_amount' => round($totalPayments, 4),
            'cash_transaction_total' => round($cashTotal, 4),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $session->id])->execute();

        $session->refresh();
    }

    private function liveSession(PosConfig $config): ?PosSession
    {
        return PosSession::find()
            ->where(['config_id' => $config->id])
            ->andWhere(['not in', 'state', [PosSession::STATE_CLOSED]])
            ->orderBy(['id' => SORT_DESC])
            ->one();
    }

    public static function isUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }

    public static function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
