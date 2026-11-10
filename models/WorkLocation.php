<?php

namespace app\models;

use app\components\BaseModel;

class WorkLocation extends BaseModel
{
    public static function tableName(): string
    {
        return 'employees_work_locations';
    }

    public static function isSoftDelete(): bool
    {
        return true;
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
        return ['id', 'name', 'location_type', 'location_number', 'is_active', 'company_id', 'creator_id', 'created_at', 'updated_at', 'deleted_at'];
    }

    public static function apiCasts(): array
    {
        return ['is_active' => 'bool', 'created_at' => 'iso', 'updated_at' => 'iso', 'deleted_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['company' => 'company', 'creator' => 'creator'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'location_type' => ['required', 'string', 'max:32'],
            'location_number' => ['string', 'max:64'],
            'is_active' => ['boolean'],
            'company_id' => ['required', 'integer', 'exists:companies', 'company_allowed'],
        ];
    }
}
