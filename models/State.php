<?php

namespace app\models;

use app\components\BaseModel;

class State extends BaseModel
{
    public static function tableName(): string
    {
        return 'states';
    }

    public function getCountry()
    {
        return $this->hasOne(Country::class, ['id' => 'country_id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'code', 'country_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['country' => 'country'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['string', 'max:50'],
            'country_id' => ['required', 'integer', 'exists:countries'],
        ];
    }
}
