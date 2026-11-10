<?php

namespace app\models;

use app\components\BaseModel;

class InventoryLot extends BaseModel
{
    public static function tableName(): string
    {
        return 'inventories_lots';
    }

    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    public function getUom()
    {
        return $this->hasOne(Uom::class, ['id' => 'uom_id']);
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
        return [
            'id', 'name', 'description', 'reference', 'properties', 'expiry_reminded', 'expiration_date',
            'use_date', 'removal_date', 'alert_date', 'product_id', 'uom_id', 'location_id',
            'company_id', 'creator_id', 'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'properties' => 'json',
            'expiry_reminded' => 'bool',
            'expiration_date' => 'iso',
            'use_date' => 'iso',
            'removal_date' => 'iso',
            'alert_date' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'product' => 'product',
            'uom' => 'uom',
            'location' => 'location',
            'company' => 'company',
            'creator' => 'creator',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'product_id' => ['required', 'integer', 'exists:products_products'],
            'reference' => ['string', 'max:255'],
            'description' => ['string'],
            'expiration_date' => ['date'],
            'use_date' => ['date'],
            'removal_date' => ['date'],
            'alert_date' => ['date'],
            'uom_id' => ['integer', 'exists:unit_of_measures'],
            'location_id' => ['integer', 'exists:inventories_locations'],
        ];
    }
}
