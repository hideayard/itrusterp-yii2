<?php

namespace app\models;

use app\components\BaseModel;

class PackageType extends BaseModel
{
    public static function tableName(): string
    {
        return 'inventories_package_types';
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
        return [
            'id', 'name', 'sort', 'barcode', 'height', 'width', 'length', 'base_weight', 'max_weight',
            'shipper_package_code', 'package_carrier_type', 'company_id', 'creator_id', 'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'sort' => 'int',
            'height' => 'float',
            'width' => 'float',
            'length' => 'float',
            'base_weight' => 'float',
            'max_weight' => 'float',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return ['company' => 'company', 'creator' => 'creator'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'length' => ['required', 'number', 'min_num:0', 'max_num:99999999999'],
            'width' => ['required', 'number', 'min_num:0', 'max_num:99999999999'],
            'height' => ['required', 'number', 'min_num:0', 'max_num:99999999999'],
            'base_weight' => ['required', 'number', 'min_num:0', 'max_num:99999999999'],
            'max_weight' => ['required', 'number', 'min_num:0', 'max_num:99999999999'],
            'barcode' => ['string', 'max:255'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
            'sort' => ['integer'],
        ];
    }
}
