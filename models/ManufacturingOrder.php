<?php

namespace app\models;

use app\components\BaseModel;
use app\components\SequenceService;

class ManufacturingOrder extends BaseModel
{
    public const STATE_DRAFT = 'draft';

    public static function tableName(): string
    {
        return 'manufacturing_orders';
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
        $this->state ??= self::STATE_DRAFT;
        $this->priority ??= '0';
        $this->consumption ??= 'flexible';
        $this->quantity ??= 0;
        $this->quantity_producing ??= 0;
        $this->product_uom_qty ??= 0;
        $this->is_planned ??= 0;
        $this->is_locked ??= 0;
        $this->started_at ??= date('Y-m-d H:i:s');

        if ($insert && !$this->name) {
            $this->name = SequenceService::next('manufacturing.order', $this->company_id !== null ? (int) $this->company_id : null, [
                'name' => 'Manufacturing Order',
                'prefix' => 'MO/',
                'padding' => 5,
            ]);
        }

        return true;
    }

    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    public function getUom()
    {
        return $this->hasOne(UOM::class, ['id' => 'uom_id']);
    }

    public function getBillOfMaterial()
    {
        return $this->hasOne(BillOfMaterial::class, ['id' => 'bill_of_material_id']);
    }

    public function getOperationType()
    {
        return $this->hasOne(InventoryOperationType::class, ['id' => 'operation_type_id']);
    }

    public function getAssignedUser()
    {
        return $this->hasOne(User::class, ['id' => 'assigned_user_id']);
    }

    public function getSourceLocation()
    {
        return $this->hasOne(Location::class, ['id' => 'source_location_id']);
    }

    public function getDestinationLocation()
    {
        return $this->hasOne(Location::class, ['id' => 'destination_location_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getWorkOrders()
    {
        return $this->hasMany(WorkOrder::class, ['manufacturing_order_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'name', 'reference', 'priority', 'origin', 'state', 'reservation_state',
            'consumption', 'quantity', 'quantity_producing', 'product_uom_qty', 'deadline_at',
            'started_at', 'finished_at', 'is_planned', 'is_locked', 'product_id', 'uom_id',
            'producing_lot_id', 'operation_type_id', 'source_location_id', 'destination_location_id',
            'final_location_id', 'production_location_id', 'procurement_group_id', 'bill_of_material_id',
            'assigned_user_id', 'company_id', 'order_point_id', 'creator_id', 'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'quantity' => 'float',
            'quantity_producing' => 'float',
            'product_uom_qty' => 'float',
            'is_planned' => 'bool',
            'is_locked' => 'bool',
            'deadline_at' => 'iso',
            'started_at' => 'iso',
            'finished_at' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'product' => 'product',
            'uom' => 'uom',
            'bill_of_material' => 'billOfMaterial',
            'operation_type' => 'operationType',
            'assigned_user' => 'assignedUser',
            'source_location' => 'sourceLocation',
            'destination_location' => 'destinationLocation',
            'company' => 'company',
            'creator' => 'creator',
            'work_orders' => 'workOrders',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'reference' => ['string', 'max:255'],
            'priority' => ['in:0,1'],
            'origin' => ['string', 'max:255'],
            'state' => ['in:draft,confirmed,progress,to_close,done,cancel'],
            'reservation_state' => ['in:confirmed,assigned,waiting'],
            'consumption' => ['in:flexible,warning,strict'],
            'quantity' => ['number', 'min_num:0'],
            'product_uom_qty' => ['number', 'min_num:0'],
            'deadline_at' => ['date'],
            'started_at' => ['date'],
            'finished_at' => ['date'],
            'is_planned' => ['boolean'],
            'is_locked' => ['boolean'],
            'product_id' => ['required', 'integer', 'exists:products_products'],
            'uom_id' => ['integer', 'exists:unit_of_measures'],
            'bill_of_material_id' => ['integer', 'exists:manufacturing_bills_of_materials'],
            'operation_type_id' => ['required', 'integer', 'exists:inventories_operation_types'],
            'source_location_id' => ['required', 'integer', 'exists:inventories_locations'],
            'destination_location_id' => ['required', 'integer', 'exists:inventories_locations'],
            'assigned_user_id' => ['integer', 'exists:users'],
            'company_id' => ['required', 'integer', 'exists:companies', 'company_allowed'],
        ];
    }
}
