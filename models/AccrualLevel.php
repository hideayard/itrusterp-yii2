<?php

namespace app\models;

use app\components\BaseModel;

class AccrualLevel extends BaseModel
{
    public static function tableName(): string
    {
        return 'time_off_leave_accrual_levels';
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $this->sort ??= 0;

        return true;
    }

    public function getAccrualPlan()
    {
        return $this->hasOne(AccrualPlan::class, ['id' => 'accrual_plan_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'sort', 'accrual_plan_id', 'start_count', 'first_day', 'second_day',
            'first_month_day', 'second_month_day', 'yearly_day', 'postpone_max_days',
            'accrual_validity_count', 'creator_id', 'start_type', 'added_value_type', 'frequency',
            'week_day', 'first_month', 'second_month', 'yearly_month', 'action_with_unused_accruals',
            'accrual_validity_type', 'added_value', 'maximum_leave', 'maximum_leave_yearly',
            'cap_accrued_time', 'cap_accrued_time_yearly', 'accrual_validity', 'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'sort' => 'int',
            'start_count' => 'int',
            'first_day' => 'int',
            'second_day' => 'int',
            'postpone_max_days' => 'int',
            'accrual_validity_count' => 'int',
            'added_value' => 'int',
            'maximum_leave' => 'int',
            'maximum_leave_yearly' => 'int',
            'cap_accrued_time' => 'bool',
            'cap_accrued_time_yearly' => 'bool',
            'accrual_validity' => 'bool',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return ['accrual_plan' => 'accrualPlan', 'creator' => 'creator'];
    }

    public static function apiRules(): array
    {
        return [
            'accrual_plan_id' => ['required', 'integer', 'exists:time_off_leave_accrual_plans'],
            'added_value' => ['required', 'integer'],
            'first_month' => ['string', 'max:16'],
            'second_month' => ['string', 'max:16'],
            'yearly_month' => ['string', 'max:16'],
            'sort' => ['integer'],
            'start_count' => ['integer'],
            'start_type' => ['in:months,days,years'],
            'added_value_type' => ['in:days,hours'],
            'frequency' => ['in:hourly,daily,weekly,bimonthly,monthly,biyearly,yearly'],
            'week_day' => ['in:sunday,monday,tuesday,wednesday,thursday,friday,saturday'],
            'action_with_unused_accruals' => ['in:lost,all,maximum'],
            'accrual_validity_type' => ['in:days,months'],
            'maximum_leave' => ['integer'],
            'maximum_leave_yearly' => ['integer'],
            'cap_accrued_time' => ['boolean'],
            'cap_accrued_time_yearly' => ['boolean'],
            'accrual_validity' => ['boolean'],
        ];
    }
}
