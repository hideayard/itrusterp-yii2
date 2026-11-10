<?php

namespace app\models;

use app\components\BaseModel;

class Tax extends BaseModel
{
    public static function tableName(): string
    {
        return 'accounts_taxes';
    }

    public static function isCompanyScoped(): bool
    {
        return true;
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getTaxGroup()
    {
        return $this->hasOne(TaxGroup::class, ['id' => 'tax_group_id']);
    }

    public function getCashBasisTransitionAccount()
    {
        return $this->hasOne(Account::class, ['id' => 'cash_basis_transition_account_id']);
    }

    public function getCountry()
    {
        return $this->hasOne(Country::class, ['id' => 'country_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'type_tax_use', 'tax_scope', 'amount_type', 'formula', 'price_include_override',
            'tax_exigibility', 'name', 'description', 'invoice_label', 'invoice_legal_notes',
            'amount', 'is_active', 'include_base_amount', 'is_base_affected', 'analytic', 'sort',
            'company_id', 'tax_group_id', 'cash_basis_transition_account_id', 'country_id',
            'creator_id', 'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'amount' => 'float',
            'is_active' => 'bool',
            'include_base_amount' => 'bool',
            'is_base_affected' => 'bool',
            'analytic' => 'bool',
            'sort' => 'int',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'company' => 'company',
            'tax_group' => 'taxGroup',
            'cash_basis_transition_account' => 'cashBasisTransitionAccount',
            'country' => 'country',
            'creator' => 'creator',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type_tax_use' => ['required', 'in:sale,purchase,none'],
            'amount_type' => ['required', 'in:fixed,group,percent,division,code'],
            'amount' => ['number', 'min_num:0'],
            'formula' => ['string'],
            'tax_group_id' => ['required', 'integer', 'exists:accounts_tax_groups'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
            'country_id' => ['integer', 'exists:countries'],
            'tax_scope' => ['string'],
            'price_include_override' => ['string'],
            'cash_basis_transition_account_id' => ['integer', 'exists:accounts_accounts'],
            'invoice_label' => ['string', 'max:255'],
            'invoice_legal_notes' => ['string'],
            'tax_exigibility' => ['string', 'max:255'],
            'is_active' => ['boolean'],
            'include_base_amount' => ['boolean'],
            'is_base_affected' => ['boolean'],
            'analytic' => ['boolean'],
            'sort' => ['integer'],
        ];
    }
}
