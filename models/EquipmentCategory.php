<?php

namespace app\models;

use app\components\BaseModel;

class EquipmentCategory extends BaseModel
{
    public static function tableName(): string
    {
        return 'maintenance_equipment_categories';
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getTechnician()
    {
        return $this->hasOne(User::class, ['id' => 'technician_user_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getEquipments()
    {
        return $this->hasMany(Equipment::class, ['category_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'note', 'creator_id', 'technician_user_id', 'company_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['creator' => 'creator', 'technician' => 'technician', 'company' => 'company', 'equipments' => 'equipments'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'note' => ['string'],
            'technician_user_id' => ['integer', 'exists:users'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
        ];
    }
}
