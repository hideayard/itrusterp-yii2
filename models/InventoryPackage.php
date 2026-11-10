<?php

namespace app\models;

use app\components\BaseModel;

class InventoryPackage extends BaseModel
{
    public static function tableName(): string
    {
        return 'inventories_packages';
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        if ($insert && !$this->package_use) {
            $this->package_use = 'reusable';
        }
        return true;
    }

    public function getPackageType()
    {
        return $this->hasOne(PackageType::class, ['id' => 'package_type_id']);
    }

    public function getLocation()
    {
        return $this->hasOne(Location::class, ['id' => 'location_id']);
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
        return ['id', 'name', 'package_use', 'pack_date', 'package_type_id', 'location_id', 'company_id', 'creator_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['pack_date' => 'iso', 'created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return [
            'package_type' => 'packageType',
            'location' => 'location',
            'company' => 'company',
            'creator' => 'creator',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'package_type_id' => ['integer', 'exists:inventories_package_types'],
            'pack_date' => ['date'],
            'location_id' => ['integer', 'exists:inventories_locations'],
            'package_use' => ['string'],
        ];
    }
}
