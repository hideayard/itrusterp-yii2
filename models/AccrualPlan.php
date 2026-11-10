<?php

namespace app\models;

use app\components\BaseModel;

class AccrualPlan extends BaseModel
{
    public static function tableName(): string
    {
        return 'time_off_leave_accrual_plans';
    }

    public function getLeaveType()
    {
        return $this->hasOne(LeaveType::class, ['id' => 'time_off_type_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getLevels()
    {
        return $this->hasMany(AccrualLevel::class, ['accrual_plan_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'time_off_type_id', 'company_id', 'carryover_day', 'creator_id', 'name',
            'transition_mode', 'accrued_gain_time', 'carryover_date', 'carryover_month',
            'added_value_type', 'is_active', 'is_based_on_worked_time', 'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'carryover_day' => 'int',
            'is_active' => 'bool',
            'is_based_on_worked_time' => 'bool',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return ['leave_type' => 'leaveType', 'company' => 'company', 'creator' => 'creator', 'levels' => 'levels'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'time_off_type_id' => ['integer', 'exists:time_off_leave_types'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
            'carryover_day' => ['integer'],
            'transition_mode' => ['in:immediately,end_of_accrual'],
            'accrued_gain_time' => ['in:start,end'],
            'carryover_date' => ['in:year_start,allocation,other'],
            'carryover_month' => ['in:jan,feb,mar,apr,may,jun,jul,aug,sep,oct,nov,dec'],
            'added_value_type' => ['string', 'max:32'],
            'is_active' => ['boolean'],
            'is_based_on_worked_time' => ['boolean'],
        ];
    }
}
