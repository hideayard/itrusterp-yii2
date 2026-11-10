<?php

namespace app\models;

use app\components\BaseModel;

class Country extends BaseModel
{
    public static function tableName(): string
    {
        return 'countries';
    }

    public function getCurrency()
    {
        return $this->hasOne(Currency::class, ['id' => 'currency_id']);
    }

    public function getStates()
    {
        return $this->hasMany(State::class, ['country_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'code', 'phone_code', 'state_required', 'zip_required', 'currency_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return [
            'state_required' => 'bool',
            'zip_required' => 'bool',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return ['currency' => 'currency', 'states' => 'states'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:2'],
            'phone_code' => ['string', 'max:10'],
            'currency_id' => ['integer', 'exists:currencies'],
            'state_required' => ['boolean'],
            'zip_required' => ['boolean'],
        ];
    }
}
