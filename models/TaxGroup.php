<?php

namespace app\models;

use app\components\BaseModel;

class TaxGroup extends BaseModel
{
    public static function tableName(): string
    {
        return 'accounts_tax_groups';
    }

    public static function isCompanyScoped(): bool
    {
        return true;
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
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
        return ['id', 'name', 'preceding_subtotal', 'sort', 'company_id', 'country_id', 'creator_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['sort' => 'int', 'created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['company' => 'company', 'country' => 'country', 'creator' => 'creator'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
            'country_id' => ['integer', 'exists:countries'],
            'preceding_subtotal' => ['string', 'max:255'],
            'sort' => ['integer'],
        ];
    }
}
