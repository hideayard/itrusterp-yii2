<?php

namespace app\models;

use app\components\BaseModel;

class EmploymentType extends BaseModel
{
    public static function tableName(): string
    {
        return 'employees_employment_types';
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
        return ['id', 'sort', 'name', 'code', 'country_id', 'creator_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['sort' => 'int', 'created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['country' => 'country', 'creator' => 'creator'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['string', 'max:32'],
            'sort' => ['integer'],
            'country_id' => ['integer', 'exists:countries'],
        ];
    }
}
