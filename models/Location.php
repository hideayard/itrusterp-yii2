<?php

namespace app\models;

use app\components\BaseModel;

class Location extends BaseModel
{
    public const TYPE_SUPPLIER = 'supplier';
    public const TYPE_VIEW = 'view';
    public const TYPE_INTERNAL = 'internal';
    public const TYPE_CUSTOMER = 'customer';
    public const TYPE_INVENTORY = 'inventory';
    public const TYPE_PRODUCTION = 'production';
    public const TYPE_TRANSIT = 'transit';

    public static function tableName(): string
    {
        return 'inventories_locations';
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

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        $parentPath = '/';
        if ($this->parent_id) {
            $parent = self::findUnscoped()->where(['id' => $this->parent_id])->one();
            if ($parent !== null) {
                $parentPath = (string) $parent->parent_path ?: '/';
            }
        }
        $expected = $parentPath . $this->id . '/';
        if ($this->parent_path !== $expected) {
            self::updateAll(['parent_path' => $expected], ['id' => $this->id]);
            $this->setAttribute('parent_path', $expected);
        }
    }

    public function getChildren()
    {
        return $this->hasMany(self::class, ['parent_id' => 'id']);
    }

    public function getWarehouse()
    {
        return $this->hasOne(Warehouse::class, ['id' => 'warehouse_id'])->withTrashed();
    }

    public function getStorageCategory()
    {
        return $this->hasOne(StorageCategory::class, ['id' => 'storage_category_id']);
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
            'id', 'name', 'full_name', 'description', 'parent_path', 'barcode', 'type',
            'position_x', 'position_y', 'position_z', 'removal_strategy', 'cyclic_inventory_frequency',
            'last_inventory_date', 'next_inventory_date', 'is_scrap', 'is_replenish', 'is_dock',
            'parent_id', 'warehouse_id', 'storage_category_id', 'company_id',
            'created_at', 'updated_at', 'deleted_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'position_x' => 'int',
            'position_y' => 'int',
            'position_z' => 'int',
            'cyclic_inventory_frequency' => 'int',
            'is_scrap' => 'bool',
            'is_replenish' => 'bool',
            'is_dock' => 'bool',
            'last_inventory_date' => 'iso',
            'next_inventory_date' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
            'deleted_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'parent' => 'parent',
            'children' => 'children',
            'warehouse' => 'warehouse',
            'storage_category' => 'storageCategory',
            'company' => 'company',
            'creator' => 'creator',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['integer', 'exists:inventories_locations'],
            'type' => ['required', 'in:supplier,view,internal,customer,inventory,production,transit'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
            'storage_category_id' => ['integer', 'exists:inventories_storage_categories'],
            'is_scrap' => ['boolean'],
            'is_dock' => ['boolean'],
            'is_replenish' => ['boolean'],
            'cyclic_inventory_frequency' => ['integer', 'min_num:0'],
            'barcode' => ['string', 'max:255'],
            'description' => ['string'],
            'removal_strategy' => ['string'],
        ];
    }
}
