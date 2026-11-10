<?php

namespace app\components;

use Yii;
use yii\db\Query;
use app\models\SalesOrder;

/**
 * Port of Webkul\Sale\Services\Invoicer.
 *
 * Creating an invoice from an order is a UI action in the source (Filament
 * CreateInvoiceAction -> SaleManager -> Invoicer). The source REST API exposes
 * invoices read-only, so the create endpoint is defined by this project.
 *
 * Deviation: journal entries are written with their amounts (untaxed/tax/total)
 * but without account resolution, so debit/credit stay 0 until the Accounts
 * posting module is ported.
 */
class SalesInvoicer
{
    public function createInvoice(SalesOrder $order, ?string $advancePaymentMethod = 'delivered'): array
    {
        $lines = (new Query())
            ->from('sales_order_lines')
            ->where(['order_id' => $order->id])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $invoiceable = 0.0;
        foreach ($lines as $line) {
            if ($line['display_type']) {
                continue;
            }
            $invoiceable += $this->quantityFor($line);
        }

        if (abs($invoiceable) < 0.00001) {
            throw new ValidationException(['lines' => ['There are no invoiceable lines on this order.']]);
        }

        $journal = (new Query())
            ->from('accounts_journals')
            ->where(['type' => 'sale'])
            ->andWhere(['or', ['company_id' => $order->company_id], ['company_id' => null]])
            ->orderBy(['id' => SORT_ASC])
            ->one();

        if ($journal === null) {
            throw new ValidationException([], 'No sale journal is configured for this company.');
        }

        $db = Yii::$app->db;
        $now = date('Y-m-d H:i:s');
        $date = date('Y-m-d');

        $transaction = $db->beginTransaction();
        try {
            $db->createCommand()->insert('accounts_account_moves', [
                'move_type' => 'out_invoice',
                'invoice_origin' => $order->name,
                'journal_id' => $journal['id'],
                'company_id' => $order->company_id,
                'currency_id' => $order->currency_id,
                'partner_id' => $order->partner_id,
                'invoice_payment_term_id' => $order->payment_term_id,
                'fiscal_position_id' => $order->fiscal_position_id,
                'creator_id' => Yii::$app->access?->currentUserId(),
                'name' => $this->nextMoveName((int) $journal['id'], (string) $journal['code']),
                'state' => 'draft',
                'payment_state' => 'not_paid',
                'date' => $date,
                'invoice_date' => $date,
                'created_at' => $now,
                'updated_at' => $now,
            ])->execute();

            $moveId = (int) $db->getLastInsertID();

            $db->createCommand()->insert('sales_order_invoices', [
                'order_id' => $order->id,
                'move_id' => $moveId,
            ])->execute();

            $untaxed = 0.0;
            $taxTotal = 0.0;

            foreach ($lines as $line) {
                if ($line['display_type']) {
                    continue;
                }

                $qty = $this->quantityFor($line);
                $subtotal = round($qty * (float) $line['price_unit'] * (1 - (float) $line['discount'] / 100), 2);
                $taxRate = $this->taxRate((int) $line['id']);
                $lineTax = round($subtotal * $taxRate / 100, 2);

                $db->createCommand()->insert('accounts_account_move_lines', [
                    'move_id' => $moveId,
                    'journal_id' => $journal['id'],
                    'company_id' => $order->company_id,
                    'company_currency_id' => $order->currency_id,
                    'currency_id' => $order->currency_id,
                    'partner_id' => $order->partner_id,
                    'product_id' => $line['product_id'],
                    'uom_id' => $line['product_uom_id'],
                    'creator_id' => Yii::$app->access?->currentUserId(),
                    'name' => $line['name'],
                    'parent_state' => 'draft',
                    'date' => $date,
                    'quantity' => $qty,
                    'price_unit' => (float) $line['price_unit'],
                    'discount' => (float) $line['discount'],
                    'price_subtotal' => $subtotal,
                    'price_total' => $subtotal + $lineTax,
                    'amount_currency' => $subtotal + $lineTax,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->execute();

                $moveLineId = (int) $db->getLastInsertID();

                $db->createCommand()->insert('sales_order_line_invoices', [
                    'order_line_id' => (int) $line['id'],
                    'invoice_line_id' => $moveLineId,
                ])->execute();

                foreach ($this->taxIds((int) $line['id']) as $taxId) {
                    $db->createCommand()->insert('accounts_accounts_move_line_taxes', [
                        'move_line_id' => $moveLineId,
                        'tax_id' => $taxId,
                    ])->execute();
                }

                $db->createCommand()->update('sales_order_lines', [
                    'qty_invoiced' => (float) $line['qty_invoiced'] + $qty,
                    'updated_at' => $now,
                ], ['id' => (int) $line['id']])->execute();

                $untaxed += $subtotal;
                $taxTotal += $lineTax;
            }

            $db->createCommand()->update('accounts_account_moves', [
                'amount_untaxed' => $untaxed,
                'amount_tax' => $taxTotal,
                'amount_total' => $untaxed + $taxTotal,
                'amount_residual' => $untaxed + $taxTotal,
                'amount_untaxed_signed' => $untaxed,
                'amount_tax_signed' => $taxTotal,
                'amount_total_signed' => $untaxed + $taxTotal,
                'updated_at' => $now,
            ], ['id' => $moveId])->execute();

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        (new SalesCalculator())->recompute($order);

        return [
            'id' => $moveId,
            'name' => (new Query())->select(['name'])->from('accounts_account_moves')->where(['id' => $moveId])->scalar(),
            'move_type' => 'out_invoice',
            'state' => 'draft',
            'amount_untaxed' => round($untaxed, 2),
            'amount_tax' => round($taxTotal, 2),
            'amount_total' => round($untaxed + $taxTotal, 2),
        ];
    }

    private function quantityFor(array $line): float
    {
        $policy = (new Query())
            ->select(['invoice_policy'])
            ->from('products_products')
            ->where(['id' => (int) $line['product_id']])
            ->scalar();

        if ($policy === 'order') {
            return (float) $line['product_uom_qty'];
        }

        return (float) $line['qty_to_invoice'];
    }

    private function taxIds(int $lineId): array
    {
        return (new Query())
            ->select(['tax_id'])
            ->from('sales_order_line_taxes')
            ->where(['order_line_id' => $lineId])
            ->column();
    }

    private function taxRate(int $lineId): float
    {
        $ids = $this->taxIds($lineId);
        if ($ids === []) {
            return 0.0;
        }

        $rates = (new Query())
            ->select(['amount', 'amount_type'])
            ->from('accounts_taxes')
            ->where(['id' => $ids])
            ->all();

        $rate = 0.0;
        foreach ($rates as $row) {
            if ((string) $row['amount_type'] === 'percent') {
                $rate += (float) $row['amount'];
            }
        }

        return $rate;
    }

    private function nextMoveName(int $journalId, string $code): string
    {
        $count = (int) (new Query())->from('accounts_account_moves')->where(['journal_id' => $journalId])->count();

        return sprintf('%s/%s/%05d', $code, date('Y'), $count + 1);
    }
}
