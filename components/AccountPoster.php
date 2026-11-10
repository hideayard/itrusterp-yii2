<?php

namespace app\components;

use Yii;
use yii\db\Query;

/**
 * Posting jurnal sederhana (port perilaku `Account` facade: computeAccountMove + post).
 *
 * Alur:
 *  - hanya entry `draft` yang bisa diposting (dan `posted` yang bisa di-reset ke draft);
 *  - baris yang belum punya akun diresolusi (produk → journal → fallback per tipe akun);
 *  - baris yang debit/credit-nya masih 0 diisi sesuai tipe akun & arah move;
 *  - selisih (pajak, pembulatan) ditutup dengan satu baris lawan ke akun piutang/utang;
 *  - nomor dokumen diisi dari tabel `sequences` bila masih kosong;
 *  - `amount_untaxed/tax/total` dihitung ulang dari baris, `state` → `posted`.
 *
 * Belum diport: rekonsiliasi pembayaran, lock date, dan posting otomatis COGS/stok.
 */
class AccountPoster
{
    /** Akun default per tipe (dipakai bila produk/journal tidak menentukan). */
    private const TYPE_FALLBACK = [
        'income' => ['income', 'income_other'],
        'expense' => ['expense', 'expense_direct_cost', 'expense'],
        'receivable' => ['asset_receivable', 'asset_current'],
        'payable' => ['liability_payable', 'liability_current'],
    ];

    public function post(int $moveId): array
    {
        $db = Yii::$app->db;
        $move = $this->move($moveId);

        if ((string) $move['state'] !== 'draft') {
            throw new ValidationException([], 'Only draft entries can be posted.');
        }

        $lines = $this->lines($moveId);
        if ($lines === []) {
            throw new ValidationException([], 'This entry has no lines to post.');
        }

        $isOutgoing = in_array($move['move_type'], ['out_invoice', 'out_refund'], true);
        $isRefund = in_array($move['move_type'], ['out_refund', 'in_refund'], true);
        $counterAccount = $this->counterAccount($move, $isOutgoing);

        $transaction = $db->beginTransaction();
        try {
            foreach ($lines as $line) {
                $accountId = $line['account_id'] !== null
                    ? (int) $line['account_id']
                    : $this->lineAccount($move, $line, $isOutgoing);

                $amount = $this->lineAmount($line);
                $side = $this->sideFor($move, $accountId, $isOutgoing, $isRefund);

                $update = ['account_id' => $accountId, 'parent_state' => 'posted'];

                if ((float) $line['debit'] == 0.0 && (float) $line['credit'] == 0.0) {
                    $update['debit'] = $side === 'debit' ? $amount : 0;
                    $update['credit'] = $side === 'credit' ? $amount : 0;
                    $update['balance'] = $side === 'debit' ? $amount : -$amount;
                    $update['amount_currency'] = $update['balance'];
                }

                $db->createCommand()->update('accounts_account_move_lines', $update, ['id' => (int) $line['id']])->execute();
            }

            $totals = $this->totals($moveId);
            $difference = round($totals['debit'] - $totals['credit'], 4);

            if (abs($difference) > 0.0001) {
                if ($counterAccount === null) {
                    throw new ValidationException([], 'No receivable/payable account is available to balance this entry.');
                }

                $now = date('Y-m-d H:i:s');

                // Pakai ulang baris lawan yang sudah ada (mis. setelah reset-to-draft),
                // supaya posting berulang tidak menumpuk baris piutang/utang.
                $existing = (new Query())->from('accounts_account_move_lines')
                    ->where(['move_id' => $moveId, 'display_type' => 'payment_term'])
                    ->select('id')
                    ->orderBy(['id' => SORT_ASC])
                    ->scalar();

                $counterData = [
                    'journal_id' => $move['journal_id'],
                    'company_id' => $move['company_id'],
                    'company_currency_id' => $move['currency_id'],
                    'currency_id' => $move['currency_id'],
                    'partner_id' => $move['partner_id'],
                    'account_id' => $counterAccount,
                    'name' => $move['invoice_origin'] ?? 'Balancing entry',
                    'parent_state' => 'posted',
                    'display_type' => 'payment_term',
                    'date' => $move['date'],
                    'debit' => $difference < 0 ? abs($difference) : 0,
                    'credit' => $difference > 0 ? $difference : 0,
                    'balance' => $difference < 0 ? abs($difference) : -$difference,
                    'amount_currency' => $difference < 0 ? abs($difference) : -$difference,
                    'price_unit' => abs($difference),
                    'price_subtotal' => abs($difference),
                    'price_total' => abs($difference),
                    'quantity' => 1,
                    'updated_at' => $now,
                ];

                if ($existing) {
                    $db->createCommand()->update('accounts_account_move_lines', $counterData, ['id' => (int) $existing])->execute();
                } else {
                    $counterData['move_id'] = $moveId;
                    $counterData['creator_id'] = Yii::$app->access?->currentUserId();
                    $counterData['created_at'] = $now;
                    $db->createCommand()->insert('accounts_account_move_lines', $counterData)->execute();
                }
            }

            $totals = $this->totals($moveId);
            $name = (string) ($move['name'] ?? '');
            if ($name === '') {
                $name = SequenceService::next('account.move', $move['company_id'] !== null ? (int) $move['company_id'] : null, [
                    'name' => 'Account Move',
                    'prefix' => 'MOVE/',
                    'padding' => 5,
                ]);
            }

            $db->createCommand()->update('accounts_account_moves', [
                'state' => 'posted',
                'name' => $name,
                'amount_untaxed' => $totals['untaxed'],
                'amount_tax' => $totals['tax'],
                'amount_total' => $totals['total'],
                'amount_residual' => $totals['total'],
                'amount_untaxed_signed' => $totals['untaxed'],
                'amount_tax_signed' => $totals['tax'],
                'amount_total_signed' => $totals['total'],
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $moveId])->execute();

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return [
            'id' => $moveId,
            'name' => $name,
            'state' => 'posted',
            'debit' => $totals['debit'],
            'credit' => $totals['credit'],
            'amount_untaxed' => $totals['untaxed'],
            'amount_tax' => $totals['tax'],
            'amount_total' => $totals['total'],
            'lines' => count($this->lines($moveId)),
        ];
    }

    public function resetToDraft(int $moveId): array
    {
        $db = Yii::$app->db;
        $move = $this->move($moveId);

        if ((string) $move['state'] !== 'posted') {
            throw new ValidationException([], 'Only posted entries can be reset to draft.');
        }

        $db->createCommand()->update('accounts_account_move_lines', ['parent_state' => 'draft'], ['move_id' => $moveId])->execute();
        $db->createCommand()->update('accounts_account_moves', [
            'state' => 'draft',
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $moveId])->execute();

        return ['id' => $moveId, 'state' => 'draft'];
    }

    private function move(int $moveId): array
    {
        $move = (new Query())->from('accounts_account_moves')->where(['id' => $moveId])->one();
        if (!$move) {
            throw new \yii\web\NotFoundHttpException('Resource not found.');
        }

        return $move;
    }

    private function lines(int $moveId): array
    {
        return (new Query())->from('accounts_account_move_lines')->where(['move_id' => $moveId])->orderBy(['id' => SORT_ASC])->all();
    }

    private function lineAmount(array $line): float
    {
        $subtotal = (float) $line['price_subtotal'];
        if ($subtotal != 0.0) {
            return round(abs($subtotal) + abs((float) $line['price_total'] - (float) $line['price_subtotal']), 4);
        }

        $qty = (float) $line['quantity'];
        $unit = (float) $line['price_unit'] * (1 - ((float) $line['discount'] / 100));

        return round(abs($qty * $unit), 4);
    }

    private function sideFor(array $move, int $accountId, bool $isOutgoing, bool $isRefund): string
    {
        $type = (string) (new Query())->from('accounts_accounts')->select('account_type')->where(['id' => $accountId])->scalar();

        if (in_array($type, self::TYPE_FALLBACK['receivable'], true)) {
            return $isOutgoing ? ($isRefund ? 'credit' : 'debit') : 'credit';
        }
        if (in_array($type, self::TYPE_FALLBACK['payable'], true)) {
            return $isOutgoing ? 'debit' : ($isRefund ? 'debit' : 'credit');
        }
        if (in_array($type, self::TYPE_FALLBACK['income'], true)) {
            return $isOutgoing ? ($isRefund ? 'debit' : 'credit') : 'credit';
        }
        if (in_array($type, self::TYPE_FALLBACK['expense'], true)) {
            return $isOutgoing ? 'debit' : ($isRefund ? 'credit' : 'debit');
        }

        // Akun lain (kas/bank/aset): sisi ditentukan arah dokumen.
        return $isOutgoing ? ($isRefund ? 'credit' : 'debit') : ($isRefund ? 'debit' : 'credit');
    }

    /** Akun baris: produk (income/expense) → akun default jurnal → fallback per tipe akun. */
    private function lineAccount(array $move, array $line, bool $isOutgoing): ?int
    {
        if ($line['product_id'] !== null) {
            $column = $isOutgoing ? 'property_account_income_id' : 'property_account_expense_id';
            $accountId = (new Query())->from('products_products')->select($column)->where(['id' => (int) $line['product_id']])->scalar();
            if ($accountId) {
                return (int) $accountId;
            }
        }

        if ($move['journal_id'] !== null) {
            $accountId = (new Query())->from('accounts_journals')->select('default_account_id')->where(['id' => (int) $move['journal_id']])->scalar();
            if ($accountId) {
                return (int) $accountId;
            }
        }

        $types = $isOutgoing ? array_merge(self::TYPE_FALLBACK['income'], self::TYPE_FALLBACK['receivable']) : array_merge(self::TYPE_FALLBACK['expense'], self::TYPE_FALLBACK['payable']);
        $accountId = (new Query())->from('accounts_accounts')->where(['in', 'account_type', $types])->select('id')->orderBy(['id' => SORT_ASC])->scalar();

        return $accountId ? (int) $accountId : null;
    }

    /** Akun lawan (piutang untuk penjualan, utang untuk pembelian). */
    private function counterAccount(array $move, bool $isOutgoing): ?int
    {
        $types = $isOutgoing ? self::TYPE_FALLBACK['receivable'] : self::TYPE_FALLBACK['payable'];
        $accountId = (new Query())->from('accounts_accounts')->where(['in', 'account_type', $types])->select('id')->orderBy(['id' => SORT_ASC])->scalar();

        return $accountId ? (int) $accountId : null;
    }

    private function totals(int $moveId): array
    {
        $debit = 0.0;
        $credit = 0.0;
        $untaxed = 0.0;
        $tax = 0.0;
        $total = 0.0;

        foreach ($this->lines($moveId) as $line) {
            $debit += (float) $line['debit'];
            $credit += (float) $line['credit'];

            // Baris lawan (piutang/utang) tidak dihitung ke untaxed/tax/total.
            if ((string) $line['display_type'] === 'payment_term') {
                continue;
            }

            $untaxed += (float) $line['price_subtotal'];
            $tax += (float) $line['price_total'] - (float) $line['price_subtotal'];
            $total += (float) $line['price_total'];
        }

        return [
            'debit' => round($debit, 4),
            'credit' => round($credit, 4),
            'untaxed' => round($untaxed, 4),
            'tax' => round($tax, 4),
            'total' => round($total, 4),
        ];
    }
}
