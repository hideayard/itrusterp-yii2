<?php

namespace app\models;

use app\components\BaseModel;

class Currency extends BaseModel
{
    public static function tableName(): string
    {
        return 'currencies';
    }

    public function getRates()
    {
        return $this->hasMany(CurrencyRate::class, ['currency_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'symbol', 'iso_numeric', 'decimal_places', 'full_name', 'rounding', 'active', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return [
            'iso_numeric' => 'int',
            'decimal_places' => 'int',
            'active' => 'bool',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return ['rates' => 'rates'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:currencies,name'],
            'symbol' => ['required', 'string', 'max:10'],
            'iso_numeric' => ['integer'],
            'decimal_places' => ['required', 'integer'],
            'full_name' => ['string', 'max:255'],
            'rounding' => ['number', 'min_num:0'],
            'active' => ['required', 'boolean'],
        ];
    }
}
