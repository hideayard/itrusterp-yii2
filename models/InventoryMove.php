<?php

namespace app\models;

use app\components\BaseModel;

class InventoryMove extends BaseModel
{
    public static function tableName(): string
    {
        return 'inventories_moves';
    }

    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    public function getUom()
    {
        return $this->hasOne(Uom::class, ['id' => 'uom_id']);
    }

    public function getSourceLocation()
    {
        return $this->hasOne(Location::class, ['id' => 'source_location_id']);
    }

    public function getDestinationLocation()
    {
        return $this->hasOne(Location::class, ['id' => 'destination_location_id']);
    }

    public function getPartner()
    {
        return $this->hasOne(Partner::class, ['id' => 'partner_id']);
    }

    public function getOperation()
    {
        return $this->hasOne(InventoryOperation::class, ['id' => 'operation_id']);
    }

    public function getOperationType()
    {
        return $this->hasOne(InventoryOperationType::class, ['id' => 'operation_type_id']);
    }

    public function getWarehouse()
    {
        return $this->hasOne(Warehouse::class, ['id' => 'warehouse_id']);
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
            'id', 'name', 'state', 'origin', 'procure_method', 'reference', 'description_picking',
            'next_serial', 'next_serial_count', 'is_favorite', 'product_qty', 'product_uom_qty',
            'quantity', 'is_picked', 'is_scraped', 'is_inventory', 'is_refund', 'deadline',
            'reservation_date', 'scheduled_at', 'product_id', 'uom_id', 'source_location_id',
            'destination_location_id', 'final_location_id', 'partner_id', 'operation_id', 'rule_id',
            'operation_type_id', 'origin_returned_move_id', 'restrict_partner_id', 'warehouse_id',
            'product_packaging_id', 'scrap_id', 'company_id', 'creator_id', 'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'next_serial_count' => 'int',
            'is_favorite' => 'bool',
            'product_qty' => 'float',
            'product_uom_qty' => 'float',
            'quantity' => 'float',
            'is_picked' => 'bool',
            'is_scraped' => 'bool',
            'is_inventory' => 'bool',
            'is_refund' => 'bool',
            'deadline' => 'iso',
            'reservation_date' => 'iso',
            'scheduled_at' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'product' => 'product',
            'uom' => 'uom',
            'source_location' => 'sourceLocation',
            'destination_location' => 'destinationLocation',
            'partner' => 'partner',
            'operation' => 'operation',
            'operation_type' => 'operationType',
            'warehouse' => 'warehouse',
            'company' => 'company',
            'creator' => 'creator',
        ];
    }

    public static function apiRules(): array
    {
        return ['name' => ['string']];
    }
}
