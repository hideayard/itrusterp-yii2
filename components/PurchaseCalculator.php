<?php

namespace app\components;

use app\models\PurchaseOrder;
use app\models\PurchaseOrderLine;
use yii\db\Expression;
use yii\db\Query;

/**
 * Port dari OrderCalculator plugin Purchases.
 * inventories & accounts belum diport → receipt_status = "no", qty_received dari
 * stock move = 0 (perilaku sumber saat plugin inventories tidak terpasang).
 */
class PurchaseCalculator
{
    public function recompute(PurchaseOrder $order): PurchaseOrder
    {
        $untaxed = 0.0;
        $tax = 0.0;
        $total = 0.0;
        $totalCc = 0.0;

        $order->invoice_count = 0;

        foreach ($order->lines as $line) {
            $line->state = $order->state;
            $this->recomputeLine($line);

            $untaxed += (float) $line->price_subtotal;
            $tax += (float) $line->price_tax;
            $total += (float) $line->price_total;
            $totalCc += (float) $order->currency_rate > 0
                ? (float) $line->price_total / (float) $order->currency_rate
                : (float) $line->price_total;
        }

        $order->untaxed_amount = round($untaxed, 4);
        $order->tax_amount = round($tax, 4);
        $order->total_amount = round($total, 4);
        $order->total_cc_amount = round($totalCc, 4);

        // Modul inventories tidak diport.
        $order->receipt_status = 'no';

        $this->refreshInvoiceStatus($order);

        $order->save(false);
        $order->refresh();

        return $order;
    }

    public function recomputeLine(PurchaseOrderLine $line): PurchaseOrderLine
    {
        $line->qty_invoiced = $this->invoicedQuantity($line);

        if ($line->qty_received_method === null) {
            $line->qty_received_method = $line->product?->type === 'service' ? 'manual' : 'stock_move';
        }

        if ($line->qty_received_method === 'manual') {
            $line->qty_received = $line->qty_received ?? 0;
            $line->qty_received_manual = $line->qty_received;
        } else {
            $line->qty_received = 0;
        }

        $line->qty_to_invoice = (float) $line->qty_received - (float) $line->qty_invoiced;

        $this->applyLineTotals($line);

        $line->save(false);

        return $line;
    }

    public function refreshInvoiceStatus(PurchaseOrder $order): void
    {
        if (!in_array($order->state, ['purchase', 'done'], true)) {
            $order->invoice_status = 'no';
            return;
        }

        $settled = fn ($line) => abs((float) $line->qty_to_invoice) < 0.0001;

        $anyUnsettled = false;
        $allSettled = true;
        foreach ($order->lines as $line) {
            if ($settled($line)) {
                continue;
            }
            $anyUnsettled = true;
            $allSettled = false;
        }

        $order->invoice_status = match (true) {
            $anyUnsettled => 'to_invoiced',
            $allSettled && $this->hasBills($order) => 'invoiced',
            default => 'no',
        };
    }

    private function hasBills(PurchaseOrder $order): bool
    {
        return (new \yii\db\Query())
            ->from('purchases_order_account_moves')
            ->where(['order_id' => $order->id])
            ->exists();
    }

    private function invoicedQuantity(PurchaseOrderLine $line): float
    {
        $quantity = (new Query())
            ->from(['ml' => 'accounts_account_move_lines'])
            ->innerJoin('accounts_account_moves m', 'm.id = ml.move_id')
            ->where(['ml.purchase_order_line_id' => $line->id])
            ->andWhere(['not in', 'm.state', ['cancel']])
            ->select([new Expression('COALESCE(SUM(ml.quantity), 0)')])
            ->scalar();

        return round((float) $quantity, 4);
    }

    private function applyLineTotals(PurchaseOrderLine $line): void
    {
        $unit = (float) $line->price_unit;
        if ((float) $line->discount > 0) {
            $unit = $unit * (1 - ((float) $line->discount / 100));
        }

        $qty = (float) $line->product_qty;
        $subtotal = round($unit * $qty, 4);
        $taxTotal = 0.0;

        foreach ($line->taxes as $tax) {
            $amount = (float) $tax->amount;
            if (($tax->amount_type ?? 'percent') === 'percent') {
                $taxTotal += $subtotal * ($amount / 100);
            } else {
                $taxTotal += $amount * $qty;
            }
        }

        $line->price_subtotal = $subtotal;
        $line->price_tax = round($taxTotal, 4);
        $line->price_total = round($subtotal + $taxTotal, 4);
    }
}
