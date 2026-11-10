<?php

namespace app\models;

use app\components\BaseModel;

class ManufacturingOperation extends BaseModel
{
    public static function tableName(): string
    {
        return 'manufacturing_operations';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public function getWorkCenter()
    {
        return $this->hasOne(WorkCenter::class, ['id' => 'work_center_id']);
    }

    public function getBillOfMaterial()
    {
        return $this->hasOne(BillOfMaterial::class, ['id' => 'bill_of_material_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'sort', 'time_mode_batch', 'name', 'worksheet_type', 'worksheet',
            'worksheet_google_slide_url', 'time_mode', 'note', 'manual_cycle_time',
            'work_center_id', 'bill_of_material_id', 'creator_id', 'created_at', 'updated_at', 'deleted_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'sort' => 'int',
            'time_mode_batch' => 'int',
            'manual_cycle_time' => 'float',
            'created_at' => 'iso',
            'updated_at' => 'iso',
            'deleted_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'work_center' => 'workCenter',
            'bill_of_material' => 'billOfMaterial',
            'creator' => 'creator',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'work_center_id' => ['required', 'integer', 'exists:manufacturing_work_centers'],
            'bill_of_material_id' => ['required', 'integer', 'exists:manufacturing_bills_of_materials'],
            'worksheet_type' => ['in:pdf,google_slide,text'],
            'worksheet' => ['string'],
            'worksheet_google_slide_url' => ['string', 'max:255'],
            'time_mode' => ['in:auto,manual'],
            'time_mode_batch' => ['integer'],
            'manual_cycle_time' => ['number'],
            'note' => ['string'],
            'sort' => ['integer'],
        ];
    }
}
