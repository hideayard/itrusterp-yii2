<?php

namespace app\models;

use app\components\BaseModel;

class WorkCenterCapacity extends BaseModel
{
    public static function tableName(): string
    {
        return 'manufacturing_work_center_capacities';
    }

    public function getWorkCenter()
    {
        return $this->hasOne(WorkCenter::class, ['id' => 'work_center_id']);
    }

    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'capacity', 'time_start', 'time_stop', 'work_center_id', 'product_id', 'creator_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return [
            'capacity' => 'float',
            'time_start' => 'float',
            'time_stop' => 'float',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return ['work_center' => 'workCenter', 'product' => 'product', 'creator' => 'creator'];
    }

    public static function apiRules(): array
    {
        return [
            'capacity' => ['number'],
            'time_start' => ['number'],
            'time_stop' => ['number'],
            'work_center_id' => ['required', 'integer', 'exists:manufacturing_work_centers'],
            'product_id' => ['integer', 'exists:products_products'],
        ];
    }
}
