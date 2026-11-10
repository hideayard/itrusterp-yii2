<?php

namespace app\models;

use app\components\BaseModel;

class InventoryOperationType extends BaseModel
{
    public static function tableName(): string
    {
        return 'inventories_operation_types';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        if ($insert) {
            $this->reservation_method = $this->reservation_method ?: 'at_confirm';
            $this->create_backorder = $this->create_backorder ?: 'ask';
        }
        return true;
    }

    public function getReturnOperationType()
    {
        return $this->hasOne(self::class, ['id' => 'return_operation_type_id']);
    }

    public function getSourceLocation()
    {
        return $this->hasOne(Location::class, ['id' => 'source_location_id']);
    }

    public function getDestinationLocation()
    {
        return $this->hasOne(Location::class, ['id' => 'destination_location_id']);
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
            'id', 'name', 'type', 'sort', 'sequence_code', 'reservation_method', 'reservation_days_before',
            'reservation_days_before_priority', 'product_label_format', 'lot_label_format',
            'package_label_to_print', 'barcode', 'create_backorder', 'move_type', 'show_entire_packs',
            'use_create_lots', 'use_existing_lots', 'print_label', 'show_operations',
            'auto_show_reception_report', 'auto_print_delivery_slip', 'auto_print_return_slip',
            'auto_print_product_labels', 'auto_print_lot_labels', 'auto_print_reception_report',
            'auto_print_reception_report_labels', 'auto_print_packages', 'auto_print_package_label',
            'return_operation_type_id', 'source_location_id', 'destination_location_id',
            'warehouse_id', 'company_id', 'created_at', 'updated_at', 'deleted_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'sort' => 'int',
            'reservation_days_before' => 'int',
            'reservation_days_before_priority' => 'int',
            'show_entire_packs' => 'bool',
            'use_create_lots' => 'bool',
            'use_existing_lots' => 'bool',
            'print_label' => 'bool',
            'show_operations' => 'bool',
            'auto_show_reception_report' => 'bool',
            'auto_print_delivery_slip' => 'bool',
            'auto_print_return_slip' => 'bool',
            'auto_print_product_labels' => 'bool',
            'auto_print_lot_labels' => 'bool',
            'auto_print_reception_report' => 'bool',
            'auto_print_reception_report_labels' => 'bool',
            'auto_print_packages' => 'bool',
            'auto_print_package_label' => 'bool',
            'created_at' => 'iso',
            'updated_at' => 'iso',
            'deleted_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'return_operation_type' => 'returnOperationType',
            'source_location' => 'sourceLocation',
            'destination_location' => 'destinationLocation',
            'warehouse' => 'warehouse',
            'company' => 'company',
            'creator' => 'creator',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:incoming,outgoing,internal,dropship,manufacture'],
            'sequence_code' => ['required', 'string', 'max:255'],
            'reservation_method' => ['in:at_confirm,manual'],
            'warehouse_id' => ['integer', 'exists:inventories_warehouses'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
            'return_operation_type_id' => ['integer', 'exists:inventories_operation_types'],
            'create_backorder' => ['required', 'in:ask,always,never'],
            'move_type' => ['in:direct,one'],
            'use_create_lots' => ['boolean'],
            'use_existing_lots' => ['boolean'],
            'print_label' => ['boolean'],
            'source_location_id' => ['required', 'integer', 'exists:inventories_locations'],
            'destination_location_id' => ['required', 'integer', 'exists:inventories_locations'],
        ];
    }
}
