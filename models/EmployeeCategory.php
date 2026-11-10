<?php

namespace app\models;

use app\components\BaseModel;

class EmployeeCategory extends BaseModel
{
    public static function tableName(): string
    {
        return 'employees_categories';
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getEmployees()
    {
        return $this->hasMany(Employee::class, ['id' => 'employee_id'])
            ->viaTable('employees_employee_categories', ['category_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'color', 'creator_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['creator' => 'creator', 'employees' => 'employees'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['string', 'max:32'],
        ];
    }
}
