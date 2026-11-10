<?php

namespace app\components;

use Yii;
use yii\db\Query;
use app\models\PurchaseOrder;

/**
 * Port of Webkul\Purchase\Services\Biller (used by the Filament
 * CreateBillAction -> PurchaseOrder facade). The source REST API exposes bills
 * read-only, so the create endpoint is defined by this project.
 *
 * Deviation: the account move stores its amounts but debit/credit are left at 0
 * because account resolution belongs to the (not yet ported) Accounts posting.
 */
class PurchaseBiller
{
    public function createBill(PurchaseOrder $order): array
    {
        $lines = (new Query())
            ->from('purchases_order_lines')
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
            ->where(['type' => 'purchase'])
            ->andWhere(['or', ['company_id' => $order->company_id], ['company_id' => null]])
            ->orderBy(['id' => SORT_ASC])
            ->one();

        if ($journal === null) {
            throw new ValidationException([], 'No purchase journal is configured for this company.');
        }

        $db = Yii::$app->db;
        $now = date('Y-m-d H:i:s');
        $date = date('Y-m-d');
        $moveType = $invoiceable >= 0 ? 'in_invoice' : 'in_refund';

        $transaction = $db->beginTransaction();
        try {
            $db->createCommand()->insert('accounts_account_moves', [
                'move_type' => $moveType,
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

            $db->createCommand()->insert('purchases_order_account_moves', [
                'order_id' => $order->id,
                'move_id' => $moveId,
            ])->execute();

            $untaxed = 0.0;
            $taxTotal = 0.0;

            foreach ($lines as $line) {
                if ($line['display_type']) {
                    continue;
                }

                $qty = abs($this->quantityFor($line));
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
                    'uom_id' => $line['uom_id'],
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
                    'purchase_order_line_id' => (int) $line['id'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->execute();

                $moveLineId = (int) $db->getLastInsertID();

                foreach ($this->taxIds((int) $line['id']) as $taxId) {
                    $db->createCommand()->insert('accounts_accounts_move_line_taxes', [
                        'move_line_id' => $moveLineId,
                        'tax_id' => $taxId,
                    ])->execute();
                }

                $db->createCommand()->update('purchases_order_lines', [
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

        (new PurchaseCalculator())->recompute($order);

        return [
            'id' => $moveId,
            'name' => (new Query())->select(['name'])->from('accounts_account_moves')->where(['id' => $moveId])->scalar(),
            'move_type' => $moveType,
            'state' => 'draft',
            'amount_untaxed' => round($untaxed, 2),
            'amount_tax' => round($taxTotal, 2),
            'amount_total' => round($untaxed + $taxTotal, 2),
        ];
    }

    private function quantityFor(array $line): float
    {
        // Receipts/stock moves belum diport, jadi tagihan memakai kuantitas dipesan
        // dikurangi yang sudah ditagih (sumber memakai qty_received).
        return (float) $line['product_qty'] - (float) $line['qty_invoiced'];
    }

    private function taxIds(int $lineId): array
    {
        return (new Query())
            ->select(['tax_id'])
            ->from('purchases_order_line_taxes')
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
