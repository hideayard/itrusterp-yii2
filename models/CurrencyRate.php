<?php

namespace app\models;

use app\components\BaseModel;

class CurrencyRate extends BaseModel
{
    public static function tableName(): string
    {
        return 'currency_rates';
    }

    public function getCurrency()
    {
        return $this->hasOne(Currency::class, ['id' => 'currency_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'rate', 'inverse_rate', 'currency_id', 'company_id', 'creator_id', 'created_at', 'updated_at'];
    }

    public static function apiComputed(): array
    {
        return [
            'inverse_rate' => fn ($m) => $m->rate == 0 ? null : (float) (1 / (float) $m->rate),
        ];
    }

    public static function apiCasts(): array
    {
        return ['name' => 'iso', 'created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['currency' => 'currency', 'company' => 'company', 'creator' => 'creator'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'date'],
            'rate' => ['required', 'number', 'gt:0'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
        ];
    }
}
