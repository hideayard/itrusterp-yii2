<?php

namespace app\models;

use app\components\BaseModel;

class DepartureReason extends BaseModel
{
    public static function tableName(): string
    {
        return 'employees_departure_reasons';
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getEmployees()
    {
        return $this->hasMany(Employee::class, ['departure_reason_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'sort', 'reason_code', 'name', 'creator_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['sort' => 'int', 'created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['creator' => 'creator', 'employees' => 'employees'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'reason_code' => ['string', 'max:32'],
            'sort' => ['integer'],
        ];
    }
}
