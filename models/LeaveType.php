<?php

namespace app\models;

use Yii;
use app\components\BaseModel;

class LeaveType extends BaseModel
{
    public static function tableName(): string
    {
        return 'time_off_leave_types';
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
        $this->sort ??= 0;
        if ($insert && !$this->creator_id) {
            $this->creator_id = Yii::$app->access?->currentUserId();
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

    public function getAllocations()
    {
        return $this->hasMany(LeaveAllocation::class, ['holiday_status_id' => 'id']);
    }

    public function getLeaves()
    {
        return $this->hasMany(Leave::class, ['holiday_status_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'sort', 'color', 'company_id', 'max_allowed_negative', 'creator_id',
            'leave_validation_type', 'requires_allocation', 'employee_requests',
            'allocation_validation_type', 'time_type', 'request_unit', 'name',
            'create_calendar_meeting', 'is_active', 'show_on_dashboard', 'unpaid',
            'include_public_holidays_in_duration', 'support_document', 'allows_negative',
            'created_at', 'updated_at', 'deleted_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'sort' => 'int',
            'max_allowed_negative' => 'int',
            'create_calendar_meeting' => 'bool',
            'is_active' => 'bool',
            'show_on_dashboard' => 'bool',
            'unpaid' => 'bool',
            'include_public_holidays_in_duration' => 'bool',
            'support_document' => 'bool',
            'allows_negative' => 'bool',
            'created_at' => 'iso',
            'updated_at' => 'iso',
            'deleted_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return ['company' => 'company', 'creator' => 'creator', 'allocations' => 'allocations', 'leaves' => 'leaves'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['string', 'max:32'],
            'sort' => ['integer'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
            'max_allowed_negative' => ['integer'],
            'leave_validation_type' => ['in:no_validation,hr,manager,both'],
            'requires_allocation' => ['in:yes,no'],
            'employee_requests' => ['in:yes,no'],
            'allocation_validation_type' => ['in:no_validation,hr,manager,both'],
            'time_type' => ['in:leave,other'],
            'request_unit' => ['in:day,half_day,hour'],
            'is_active' => ['boolean'],
            'show_on_dashboard' => ['boolean'],
            'unpaid' => ['boolean'],
            'include_public_holidays_in_duration' => ['boolean'],
            'support_document' => ['boolean'],
            'allows_negative' => ['boolean'],
            'create_calendar_meeting' => ['boolean'],
        ];
    }
}
