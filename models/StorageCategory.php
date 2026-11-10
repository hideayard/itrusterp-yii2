<?php

namespace app\models;

use app\components\BaseModel;

class StorageCategory extends BaseModel
{
    public static function tableName(): string
    {
        return 'inventories_storage_categories';
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getLocations()
    {
        return $this->hasMany(Location::class, ['storage_category_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'sort', 'allow_new_products', 'parent_path', 'max_weight', 'company_id', 'creator_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['sort' => 'int', 'max_weight' => 'float', 'created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['company' => 'company', 'creator' => 'creator', 'locations' => 'locations'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'max_weight' => ['number', 'min_num:0', 'max_num:99999999'],
            'allow_new_products' => ['required', 'in:empty,same,mixed'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
            'sort' => ['integer'],
        ];
    }
}
