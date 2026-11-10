<?php

namespace app\models;

use app\components\BaseModel;

class WorkCenter extends BaseModel
{
    public static function tableName(): string
    {
        return 'manufacturing_work_centers';
    }

    public static function isSoftDelete(): bool
    {
        return true;
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
        if ($this->sort === null) {
            $this->sort = 0;
        }
        if ($this->working_state === null) {
            $this->working_state = 'normal';
        }

        return true;
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getOperations()
    {
        return $this->hasMany(ManufacturingOperation::class, ['work_center_id' => 'id']);
    }

    public function getCapacities()
    {
        return $this->hasMany(WorkCenterCapacity::class, ['work_center_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'sort', 'color', 'name', 'code', 'working_state', 'note', 'time_efficiency',
            'default_capacity', 'costs_per_hour', 'setup_time', 'cleanup_time', 'oee_target',
            'company_id', 'calendar_id', 'creator_id', 'created_at', 'updated_at', 'deleted_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'sort' => 'int',
            'time_efficiency' => 'float',
            'default_capacity' => 'int',
            'costs_per_hour' => 'float',
            'setup_time' => 'float',
            'cleanup_time' => 'float',
            'oee_target' => 'float',
            'created_at' => 'iso',
            'updated_at' => 'iso',
            'deleted_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'company' => 'company',
            'creator' => 'creator',
            'operations' => 'operations',
            'capacities' => 'capacities',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['string', 'max:255'],
            'color' => ['string', 'max:32'],
            'working_state' => ['in:normal,blocked,done'],
            'note' => ['string'],
            'time_efficiency' => ['number'],
            'default_capacity' => ['integer'],
            'costs_per_hour' => ['number'],
            'setup_time' => ['number'],
            'cleanup_time' => ['number'],
            'oee_target' => ['number'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
            'sort' => ['integer'],
        ];
    }
}
