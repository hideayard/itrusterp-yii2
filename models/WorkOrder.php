<?php

namespace app\models;

use app\components\BaseModel;

class WorkOrder extends BaseModel
{
    public const STATE_PENDING = 'pending';

    public static function tableName(): string
    {
        return 'manufacturing_work_orders';
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $this->state ??= self::STATE_PENDING;
        $this->sort ??= 0;

        if ($insert && !$this->name) {
            $operation = $this->operation_id !== null
                ? ManufacturingOperation::findUnscoped()->where(['id' => $this->operation_id])->one()
                : null;
            $this->name = $operation?->name ?? 'Work Order';
        }

        return true;
    }

    public function getWorkCenter()
    {
        return $this->hasOne(WorkCenter::class, ['id' => 'work_center_id']);
    }

    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    public function getUom()
    {
        return $this->hasOne(UOM::class, ['id' => 'uom_id']);
    }

    public function getManufacturingOrder()
    {
        return $this->hasOne(ManufacturingOrder::class, ['id' => 'manufacturing_order_id']);
    }

    public function getOperation()
    {
        return $this->hasOne(ManufacturingOperation::class, ['id' => 'operation_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'name', 'sort', 'barcode', 'production_availability', 'state', 'quantity_produced',
            'expected_duration', 'started_at', 'finished_at', 'duration', 'duration_per_unit',
            'duration_percent', 'costs_per_hour', 'work_center_id', 'product_id', 'uom_id',
            'manufacturing_order_id', 'calendar_leave_id', 'operation_id', 'creator_id', 'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'sort' => 'int',
            'quantity_produced' => 'float',
            'expected_duration' => 'float',
            'duration' => 'float',
            'duration_per_unit' => 'float',
            'duration_percent' => 'int',
            'costs_per_hour' => 'float',
            'started_at' => 'iso',
            'finished_at' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'work_center' => 'workCenter',
            'product' => 'product',
            'uom' => 'uom',
            'manufacturing_order' => 'manufacturingOrder',
            'operation' => 'operation',
            'creator' => 'creator',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['string', 'max:255'],
            'barcode' => ['string', 'max:255'],
            'production_availability' => ['in:confirmed,assigned,waiting'],
            'state' => ['in:pending,waiting,ready,progress,done,cancel'],
            'quantity_produced' => ['number', 'min_num:0'],
            'expected_duration' => ['number'],
            'duration' => ['number'],
            'duration_per_unit' => ['number'],
            'duration_percent' => ['integer'],
            'costs_per_hour' => ['number'],
            'work_center_id' => ['required', 'integer', 'exists:manufacturing_work_centers'],
            'product_id' => ['integer', 'exists:products_products'],
            'uom_id' => ['required', 'integer', 'exists:unit_of_measures'],
            'manufacturing_order_id' => ['required', 'integer', 'exists:manufacturing_orders'],
            'operation_id' => ['integer', 'exists:manufacturing_operations'],
            'sort' => ['integer'],
        ];
    }
}
