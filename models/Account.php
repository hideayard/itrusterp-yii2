<?php

namespace app\models;

use app\components\BaseModel;

class Account extends BaseModel
{
    public static function tableName(): string
    {
        return 'accounts_accounts';
    }

    public function getCurrency()
    {
        return $this->hasOne(Currency::class, ['id' => 'currency_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'account_type', 'name', 'code', 'note', 'deprecated', 'reconcile', 'non_trade',
            'currency_id', 'creator_id', 'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'deprecated' => 'bool',
            'reconcile' => 'bool',
            'non_trade' => 'bool',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return ['currency' => 'currency', 'creator' => 'creator'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64'],
            'account_type' => ['required', 'in:asset_receivable,asset_cash,asset_current,asset_non_current,asset_prepayments,asset_fixed,liability_payable,liability_credit_card,liability_current,liability_non_current,equity,equity_unaffected,income,income_other,expense,expense_depreciation,expense_direct_cost,off_balance'],
            'currency_id' => ['integer', 'exists:currencies'],
            'note' => ['string'],
            'deprecated' => ['boolean'],
            'reconcile' => ['boolean'],
            'non_trade' => ['boolean'],
        ];
    }
}
