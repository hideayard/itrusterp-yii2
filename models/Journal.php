<?php

namespace app\models;

use app\components\BaseModel;

class Journal extends BaseModel
{
    public static function tableName(): string
    {
        return 'accounts_journals';
    }

    public static function isCompanyScoped(): bool
    {
        return true;
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        if ($insert) {
            $this->invoice_reference_type = $this->invoice_reference_type ?: 'none';
            $this->invoice_reference_model = $this->invoice_reference_model ?: 'odoo';
        }
        return true;
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getCurrency()
    {
        return $this->hasOne(Currency::class, ['id' => 'currency_id']);
    }

    public function getDefaultAccount()
    {
        return $this->hasOne(Account::class, ['id' => 'default_account_id']);
    }

    public function getSuspenseAccount()
    {
        return $this->hasOne(Account::class, ['id' => 'suspense_account_id']);
    }

    public function getProfitAccount()
    {
        return $this->hasOne(Account::class, ['id' => 'profit_account_id']);
    }

    public function getLossAccount()
    {
        return $this->hasOne(Account::class, ['id' => 'loss_account_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'name', 'code', 'type', 'color', 'sort', 'show_on_dashboard', 'refund_order',
            'payment_order', 'company_id', 'currency_id', 'default_account_id', 'suspense_account_id',
            'profit_account_id', 'loss_account_id', 'bank_account_id', 'creator_id',
            'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'sort' => 'int',
            'show_on_dashboard' => 'bool',
            'refund_order' => 'bool',
            'payment_order' => 'bool',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'company' => 'company',
            'currency' => 'currency',
            'default_account' => 'defaultAccount',
            'suspense_account' => 'suspenseAccount',
            'profit_account' => 'profitAccount',
            'loss_account' => 'lossAccount',
            'creator' => 'creator',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:5'],
            'type' => ['required', 'in:sale,purchase,cash,bank,credit,general'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
            'currency_id' => ['integer', 'exists:currencies'],
            'default_account_id' => ['integer', 'exists:accounts_accounts'],
            'suspense_account_id' => ['integer', 'exists:accounts_accounts'],
            'profit_account_id' => ['integer', 'exists:accounts_accounts'],
            'loss_account_id' => ['integer', 'exists:accounts_accounts'],
            'bank_account_id' => ['integer', 'exists:partners_bank_accounts'],
            'color' => ['string', 'max:7'],
            'show_on_dashboard' => ['boolean'],
            'refund_order' => ['boolean'],
            'payment_order' => ['boolean'],
            'sort' => ['integer'],
        ];
    }
}
