<?php

namespace app\models;

use app\components\BaseModel;

class ProductQuantity extends BaseModel
{
    public static function tableName(): string
    {
        return 'inventories_product_quantities';
    }

    public static function isCompanyScoped(): bool
    {
        return true;
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($insert) {
            $this->quantity ??= 0;
            $this->reserved_quantity ??= 0;
            $this->inventory_quantity_set ??= 0;
            $this->incoming_at ??= gmdate('Y-m-d H:i:s');
        }

        if ($this->counted_quantity !== null) {
            $difference = (float) $this->counted_quantity - (float) $this->quantity;
            $this->difference_quantity = $difference;
            $this->inventory_diff_quantity = $difference;
            $this->inventory_quantity_set = 1;
        }

        return true;
    }

    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    public function getLocation()
    {
        return $this->hasOne(Location::class, ['id' => 'location_id']);
    }

    public function getStorageCategory()
    {
        return $this->hasOne(StorageCategory::class, ['id' => 'storage_category_id']);
    }

    public function getLot()
    {
        return $this->hasOne(InventoryLot::class, ['id' => 'lot_id']);
    }

    public function getPackage()
    {
        return $this->hasOne(InventoryPackage::class, ['id' => 'package_id']);
    }

    public function getPartner()
    {
        return $this->hasOne(Partner::class, ['id' => 'partner_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
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
            'id', 'quantity', 'reserved_quantity', 'counted_quantity', 'difference_quantity',
            'inventory_diff_quantity', 'inventory_quantity_set', 'scheduled_at', 'incoming_at',
            'product_id', 'location_id', 'storage_category_id', 'lot_id', 'package_id',
            'partner_id', 'user_id', 'company_id', 'creator_id', 'created_at', 'updated_at',
        ];
    }

    public static function apiComputed(): array
    {
        return [
            'available_quantity' => fn ($m) => (float) $m->quantity - (float) $m->reserved_quantity,
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'quantity' => 'float',
            'reserved_quantity' => 'float',
            'counted_quantity' => 'float',
            'difference_quantity' => 'float',
            'inventory_diff_quantity' => 'float',
            'inventory_quantity_set' => 'bool',
            'scheduled_at' => 'iso',
            'incoming_at' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'product' => 'product',
            'location' => 'location',
            'storage_category' => 'storageCategory',
            'lot' => 'lot',
            'package' => 'package',
            'partner' => 'partner',
            'user' => 'user',
            'company' => 'company',
            'creator' => 'creator',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'location_id' => ['required', 'integer', 'exists:inventories_locations'],
            'product_id' => ['required', 'integer', 'exists:products_products'],
            'storage_category_id' => ['integer', 'exists:inventories_storage_categories'],
            'lot_id' => ['integer', 'exists:inventories_lots'],
            'package_id' => ['integer', 'exists:inventories_packages'],
            'partner_id' => ['integer', 'exists:partners_partners'],
            'user_id' => ['integer', 'exists:users'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
            'counted_quantity' => ['required', 'number', 'min_num:0', 'max_num:99999999999'],
            'scheduled_at' => ['date'],
            'quantity' => ['number'],
            'reserved_quantity' => ['number', 'min_num:0'],
        ];
    }
}
