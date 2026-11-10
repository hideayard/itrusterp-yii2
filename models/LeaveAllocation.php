<?php

namespace app\models;

use Yii;
use app\components\BaseModel;

class LeaveAllocation extends BaseModel
{
    public static function tableName(): string
    {
        return 'time_off_leave_allocations';
    }

    public function getLeaveType()
    {
        return $this->hasOne(LeaveType::class, ['id' => 'holiday_status_id']);
    }

    public function getEmployee()
    {
        return $this->hasOne(Employee::class, ['id' => 'employee_id']);
    }

    public function getDepartment()
    {
        return $this->hasOne(Department::class, ['id' => 'department_id']);
    }

    public function getManager()
    {
        return $this->hasOne(User::class, ['id' => 'manager_id']);
    }

    public function getApprover()
    {
        return $this->hasOne(User::class, ['id' => 'approver_id']);
    }

    public function getAccrualPlan()
    {
        return $this->hasOne(AccrualPlan::class, ['id' => 'accrual_plan_id']);
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
            'id', 'holiday_status_id', 'employee_id', 'employee_company_id', 'company_id',
            'manager_id', 'approver_id', 'second_approver_id', 'department_id', 'accrual_plan_id',
            'creator_id', 'name', 'state', 'allocation_type', 'date_from', 'date_to',
            'last_executed_carryover_date', 'last_called', 'actual_last_called', 'next_call',
            'carried_over_days_expiration_date', 'notes', 'already_accrued', 'number_of_days',
            'number_of_hours_display', 'yearly_accrued_amount', 'expiring_carryover_days',
            'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'already_accrued' => 'bool',
            'number_of_days' => 'float',
            'number_of_hours_display' => 'float',
            'yearly_accrued_amount' => 'float',
            'expiring_carryover_days' => 'float',
            'date_from' => 'iso',
            'date_to' => 'iso',
            'last_executed_carryover_date' => 'iso',
            'last_called' => 'iso',
            'actual_last_called' => 'iso',
            'next_call' => 'iso',
            'carried_over_days_expiration_date' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'leave_type' => 'leaveType',
            'employee' => 'employee',
            'department' => 'department',
            'manager' => 'manager',
            'approver' => 'approver',
            'accrual_plan' => 'accrualPlan',
            'company' => 'company',
            'creator' => 'creator',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'holiday_status_id' => ['required', 'integer', 'exists:time_off_leave_types'],
            'employee_id' => ['required', 'integer', 'exists:employees_employees'],
            'date_from' => ['required', 'date'],
            'date_to' => ['date'],
            'department_id' => ['integer', 'exists:employees_departments'],
            'accrual_plan_id' => ['integer', 'exists:time_off_leave_accrual_plans'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
            'manager_id' => ['integer', 'exists:users'],
            'approver_id' => ['integer', 'exists:users'],
            'name' => ['string', 'max:255'],
            'state' => ['in:confirm,refuse,validate_one,validate_two'],
            'allocation_type' => ['in:regular,accrual'],
            'notes' => ['string'],
            'number_of_days' => ['number'],
        ];
    }
}
