<?php

namespace app\models;

use app\components\BaseModel;

class CashRounding extends BaseModel
{
    public static function tableName(): string
    {
        return 'accounts_cash_roundings';
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getProfitAccount()
    {
        return $this->hasOne(Account::class, ['id' => 'profit_account_id']);
    }

    public function getLossAccount()
    {
        return $this->hasOne(Account::class, ['id' => 'loss_account_id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'name', 'strategy', 'rounding_method', 'rounding',
            'profit_account_id', 'loss_account_id', 'creator_id', 'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return ['rounding' => 'float', 'created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return [
            'creator' => 'creator',
            'profit_account' => 'profitAccount',
            'loss_account' => 'lossAccount',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'strategy' => ['required', 'in:biggest_tax,add_invoice_line'],
            'rounding_method' => ['required', 'in:UP,DOWN,HALF-UP'],
            'rounding' => ['required', 'number', 'min_num:0'],
            'profit_account_id' => ['integer', 'exists:accounts_accounts'],
            'loss_account_id' => ['integer', 'exists:accounts_accounts'],
        ];
    }
}
