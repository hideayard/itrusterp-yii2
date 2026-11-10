<?php

namespace app\models;

use app\components\BaseModel;

class Bank extends BaseModel
{
    public static function tableName(): string
    {
        return 'banks';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public function getState()
    {
        return $this->hasOne(State::class, ['id' => 'state_id']);
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
        return ['id', 'name', 'code', 'email', 'phone', 'city', 'zip', 'creator_id', 'created_at', 'updated_at', 'deleted_at'];
    }

    public static function apiCasts(): array
    {
        return ['created_at' => 'iso', 'updated_at' => 'iso', 'deleted_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['state' => 'state', 'country' => 'country', 'creator' => 'creator'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['string', 'max:50'],
            'email' => ['email', 'max:255'],
            'phone' => ['string', 'max:50'],
            'street1' => ['string', 'max:255'],
            'street2' => ['string', 'max:255'],
            'city' => ['string', 'max:100'],
            'zip' => ['string', 'max:20'],
            'state_id' => ['integer', 'exists:states'],
            'country_id' => ['integer', 'exists:countries'],
        ];
    }
}
