<?php

namespace app\models;

use Yii;
use app\components\BaseModel;

class Leave extends BaseModel
{
    public static function tableName(): string
    {
        return 'time_off_leaves';
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        if ($insert && !$this->creator_id) {
            $this->creator_id = Yii::$app->access?->currentUserId();
        }

        return true;
    }

    public function getEmployee()
    {
        return $this->hasOne(Employee::class, ['id' => 'employee_id']);
    }

    public function getLeaveType()
    {
        return $this->hasOne(LeaveType::class, ['id' => 'holiday_status_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getManager()
    {
        return $this->hasOne(User::class, ['id' => 'manager_id']);
    }

    public function getDepartment()
    {
        return $this->hasOne(Department::class, ['id' => 'department_id']);
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
            'id', 'user_id', 'manager_id', 'holiday_status_id', 'employee_id', 'employee_company_id',
            'company_id', 'department_id', 'calendar_id', 'meeting_id', 'first_approver_id',
            'second_approver_id', 'creator_id', 'private_name', 'state', 'duration_display',
            'request_date_from_period', 'request_date_from', 'request_date_to', 'notes', 'attachment',
            'request_unit_half', 'request_unit_hours', 'date_from', 'date_to', 'number_of_days',
            'number_of_hours', 'request_hour_from', 'request_hour_to', 'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'request_unit_half' => 'bool',
            'request_unit_hours' => 'bool',
            'number_of_days' => 'float',
            'number_of_hours' => 'float',
            'request_hour_from' => 'float',
            'request_hour_to' => 'float',
            'request_date_from' => 'iso',
            'request_date_to' => 'iso',
            'date_from' => 'iso',
            'date_to' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'employee' => 'employee',
            'leave_type' => 'leaveType',
            'user' => 'user',
            'manager' => 'manager',
            'department' => 'department',
            'company' => 'company',
            'creator' => 'creator',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees_employees'],
            'holiday_status_id' => ['integer', 'exists:time_off_leave_types'],
            'user_id' => ['integer', 'exists:users'],
            'manager_id' => ['integer', 'exists:users'],
            'first_approver_id' => ['integer', 'exists:users'],
            'second_approver_id' => ['integer', 'exists:users'],
            'department_id' => ['integer', 'exists:employees_departments'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
            'state' => ['string', 'max:32'],
            'private_name' => ['string'],
            'notes' => ['string'],
            'attachment' => ['string', 'max:255'],
            'request_unit_half' => ['boolean'],
            'request_unit_hours' => ['boolean'],
            'date_from' => ['date'],
            'date_to' => ['date'],
            'request_date_from' => ['date'],
            'request_date_to' => ['date'],
            'number_of_days' => ['number'],
            'number_of_hours' => ['number'],
            'request_hour_from' => ['number'],
            'request_hour_to' => ['number'],
            'request_date_from_period' => ['string', 'max:16'],
            'duration_display' => ['string', 'max:255'],
        ];
    }
}
