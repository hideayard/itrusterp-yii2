<?php

namespace app\components;

use app\models\SalesOrder;
use app\models\SalesOrderLine;
use yii\db\Expression;
use yii\db\Query;

/**
 * Port dari OrderCalculator Laravel.
 *
 * Modul inventories & accounts belum diport, jadi:
 * - qty_invoiced / qty_delivered mengikuti perilaku sumber saat plugin tersebut
 *   tidak terpasang (delivery_status = "no", qty dari stock move = 0).
 * - Perhitungan pajak memakai tarif persen dari tabel accounts_taxes.
 */
class SalesCalculator
{
    public function recompute(SalesOrder $order): SalesOrder
    {
        $untaxed = 0.0;
        $tax = 0.0;
        $total = 0.0;

        foreach ($order->lines as $line) {
            $line->state = $order->state;
            $line->salesman_id = $order->user_id;
            $line->order_partner_id = $order->partner_id;
            $line->invoice_status = $order->invoice_status;

            $this->recomputeLine($line);

            $untaxed += (float) $line->price_subtotal;
            $tax += (float) $line->price_tax;
            $total += (float) $line->price_total;
        }

        $order->amount_untaxed = round($untaxed, 4);
        $order->amount_tax = round($tax, 4);
        $order->amount_total = round($total, 4);

        // Modul inventories tidak diport → sama seperti sumber saat plugin tidak terpasang.
        $order->delivery_status = 'no';

        $this->refreshInvoiceStatus($order);

        $order->save(false);
        $order->refresh();

        return $order;
    }

    public function recomputeLine(SalesOrderLine $line): SalesOrderLine
    {
        $line->qty_invoiced = $this->invoicedQuantity($line);

        if ($line->qty_delivered === null || $line->qty_delivered_method === 'manual') {
            $line->qty_delivered ??= 0;
        }
        if ($line->qty_delivered_method === null) {
            $line->qty_delivered_method = $line->is_expense ? 'analytic' : 'stock_move';
        }

        $policy = $this->invoicePolicy($line);
        $line->qty_to_invoice = ($line->state !== 'sale' || $line->display_type)
            ? 0.0
            : ($policy === 'order'
                ? (float) $line->product_uom_qty - (float) $line->qty_invoiced
                : (float) $line->qty_delivered - (float) $line->qty_invoiced);

        $this->applyLineTotals($line);

        if ($line->sort === null) {
            $line->sort = ((int) SalesOrderLine::findUnscoped()->max('sort')) + 1;
        }

        $line->technical_price_unit = $line->price_unit;
        $line->price_reduce_taxexcl = (float) $line->product_uom_qty
            ? round((float) $line->price_subtotal / (float) $line->product_uom_qty, 4)
            : 0.0;
        $line->price_reduce_taxinc = (float) $line->product_uom_qty
            ? round((float) $line->price_total / (float) $line->product_uom_qty, 4)
            : 0.0;

        if ($line->state !== 'sale') {
            $line->invoice_status = 'no';
            $line->untaxed_amount_to_invoice = 0;
            $line->untaxed_amount_invoiced = 0;
        } else {
            $line->invoice_status = match (true) {
                (float) $line->qty_invoiced >= (float) $line->product_uom_qty => 'invoiced',
                $policy === 'delivery' && (float) $line->qty_delivered > (float) $line->product_uom_qty => 'up_selling',
                default => 'to_invoice',
            };

            $quantity = $policy === 'delivery' ? (float) $line->qty_delivered : (float) $line->product_uom_qty;
            $priceReduce = (float) $line->price_unit * (1 - ((float) ($line->discount ?? 0) / 100));
            $line->untaxed_amount_to_invoice = $priceReduce * $quantity;
            $line->untaxed_amount_invoiced = 0;
        }

        $line->save(false);

        return $line;
    }

    public function refreshInvoiceStatus(SalesOrder $order): void
    {
        if ($order->state !== 'sale') {
            $order->invoice_status = 'no';
            return;
        }

        $statuses = array_map(fn ($line) => (string) $line->invoice_status, $order->lines);

        $order->invoice_status = match (true) {
            in_array('to_invoice', $statuses, true) => 'to_invoice',
            in_array('invoiced', $statuses, true) => 'invoiced',
            in_array('up_selling', $statuses, true) => 'up_selling',
            default => 'no',
        };
    }

    private function invoicePolicy(SalesOrderLine $line): string
    {
        $policy = $line->product?->invoice_policy;

        return in_array($policy, ['order', 'delivery'], true) ? $policy : 'order';
    }

    private function invoicedQuantity(SalesOrderLine $line): float
    {
        $quantity = (new Query())
            ->from(['li' => 'sales_order_line_invoices'])
            ->innerJoin('accounts_account_move_lines ml', 'ml.id = li.invoice_line_id')
            ->innerJoin('accounts_account_moves m', 'm.id = ml.move_id')
            ->where(['li.order_line_id' => $line->id])
            ->andWhere(['not in', 'm.state', ['cancel']])
            ->select([new Expression('COALESCE(SUM(ml.quantity), 0)')])
            ->scalar();

        return round((float) $quantity, 4);
    }

    private function applyLineTotals(SalesOrderLine $line): void
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
