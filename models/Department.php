<?php

namespace app\models;

use app\components\BaseModel;

class Department extends BaseModel
{
    public static function tableName(): string
    {
        return 'employees_departments';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public static function isCompanyScoped(): bool
    {
        return true;
    }

    public function getParent()
    {
        return $this->hasOne(self::class, ['id' => 'parent_id']);
    }

    public function getChildren()
    {
        return $this->hasMany(self::class, ['parent_id' => 'id']);
    }

    public function getManager()
    {
        return $this->hasOne(Employee::class, ['id' => 'manager_id']);
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
        return ['id', 'name', 'complete_name', 'parent_path', 'color', 'manager_id', 'company_id', 'parent_id', 'master_department_id', 'creator_id', 'created_at', 'updated_at', 'deleted_at'];
    }

    public static function apiCasts(): array
    {
        return ['created_at' => 'iso', 'updated_at' => 'iso', 'deleted_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['parent' => 'parent', 'children' => 'children', 'manager' => 'manager', 'company' => 'company', 'creator' => 'creator'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['string', 'max:32'],
            'parent_id' => ['integer', 'exists:employees_departments'],
            'manager_id' => ['integer', 'exists:employees_employees'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
        ];
    }
}
