<?php

namespace app\models;

use app\components\BaseModel;

class Employee extends BaseModel
{
    public static function tableName(): string
    {
        return 'employees_employees';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public static function isCompanyScoped(): bool
    {
        return true;
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getDepartment()
    {
        return $this->hasOne(Department::class, ['id' => 'department_id']);
    }

    public function getJob()
    {
        return $this->hasOne(JobPosition::class, ['id' => 'job_id']);
    }

    public function getPartner()
    {
        return $this->hasOne(Partner::class, ['id' => 'partner_id']);
    }

    public function getWorkLocation()
    {
        return $this->hasOne(WorkLocation::class, ['id' => 'work_location_id']);
    }

    public function getParent()
    {
        return $this->hasOne(self::class, ['id' => 'parent_id']);
    }

    public function getCoach()
    {
        return $this->hasOne(self::class, ['id' => 'coach_id']);
    }

    public function getCountry()
    {
        return $this->hasOne(Country::class, ['id' => 'country_id']);
    }

    public function getState()
    {
        return $this->hasOne(State::class, ['id' => 'state_id']);
    }

    public function getPrivateState()
    {
        return $this->hasOne(State::class, ['id' => 'private_state_id']);
    }

    public function getPrivateCountry()
    {
        return $this->hasOne(Country::class, ['id' => 'private_country_id']);
    }

    public function getAttendanceManager()
    {
        return $this->hasOne(User::class, ['id' => 'attendance_manager_id']);
    }

    public function getLeaveManager()
    {
        return $this->hasOne(User::class, ['id' => 'leave_manager_id']);
    }

    public function getDepartureReason()
    {
        return $this->hasOne(DepartureReason::class, ['id' => 'departure_reason_id']);
    }

    public function getCategories()
    {
        return $this->hasMany(EmployeeCategory::class, ['id' => 'category_id'])
            ->viaTable('employees_employee_categories', ['employee_id' => 'id']);
    }

    public function getSkills()
    {
        return $this->hasMany(EmployeeSkill::class, ['employee_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'name', 'job_title', 'work_phone', 'mobile_phone', 'color', 'work_email',
            'children', 'distance_home_work', 'km_home_work', 'distance_home_work_unit',
            'private_phone', 'private_email', 'private_street1', 'private_street2', 'private_city',
            'private_zip', 'private_car_plate', 'lang', 'gender', 'birthday', 'marital',
            'spouse_complete_name', 'spouse_birthdate', 'place_of_birth', 'ssnid', 'sinid',
            'identification_id', 'passport_id', 'permit_no', 'visa_no', 'certificate', 'study_field',
            'study_school', 'emergency_contact', 'emergency_phone', 'employee_type', 'barcode', 'pin',
            'time_zone', 'work_permit', 'visa_expire', 'work_permit_expiration_date', 'departure_date',
            'departure_description', 'additional_note', 'notes', 'is_active', 'is_flexible',
            'is_fully_flexible', 'work_permit_scheduled_activity', 'company_id', 'user_id',
            'creator_id', 'calendar_id', 'department_id', 'job_id', 'attendance_manager_id',
            'partner_id', 'work_location_id', 'parent_id', 'coach_id', 'country_id', 'state_id',
            'country_of_birth', 'bank_account_id', 'departure_reason_id', 'private_state_id',
            'private_country_id', 'address_id', 'leave_manager_id', 'created_at', 'updated_at', 'deleted_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'is_active' => 'bool',
            'is_flexible' => 'bool',
            'is_fully_flexible' => 'bool',
            'work_permit_scheduled_activity' => 'bool',
            'children' => 'int',
            'distance_home_work' => 'int',
            'km_home_work' => 'float',
            'birthday' => 'iso',
            'spouse_birthdate' => 'iso',
            'visa_expire' => 'iso',
            'work_permit_expiration_date' => 'iso',
            'departure_date' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
            'deleted_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'company' => 'company',
            'user' => 'user',
            'creator' => 'creator',
            'department' => 'department',
            'job' => 'job',
            'partner' => 'partner',
            'work_location' => 'workLocation',
            'parent' => 'parent',
            'coach' => 'coach',
            'country' => 'country',
            'state' => 'state',
            'private_state' => 'privateState',
            'private_country' => 'privateCountry',
            'attendance_manager' => 'attendanceManager',
            'leave_manager' => 'leaveManager',
            'departure_reason' => 'departureReason',
            'categories' => 'categories',
            'skills' => 'skills',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'job_title' => ['string', 'max:255'],
            'work_phone' => ['string', 'max:255'],
            'mobile_phone' => ['string', 'max:255'],
            'work_email' => ['string', 'max:255'],
            'private_email' => ['string', 'max:255'],
            'private_phone' => ['string', 'max:255'],
            'gender' => ['in:male,female,other'],
            'marital' => ['string', 'max:255'],
            'employee_type' => ['string', 'max:255'],
            'lang' => ['string', 'max:16'],
            'time_zone' => ['string', 'max:64'],
            'barcode' => ['string', 'max:255'],
            'pin' => ['string', 'max:32'],
            'study_field' => ['string', 'max:255'],
            'study_school' => ['string', 'max:255'],
            'children' => ['integer'],
            'distance_home_work' => ['integer'],
            'km_home_work' => ['number'],
            'birthday' => ['date'],
            'spouse_birthdate' => ['date'],
            'visa_expire' => ['date'],
            'work_permit_expiration_date' => ['date'],
            'departure_date' => ['date'],
            'is_active' => ['boolean'],
            'is_flexible' => ['boolean'],
            'is_fully_flexible' => ['boolean'],
            'work_permit_scheduled_activity' => ['boolean'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
            'user_id' => ['integer', 'exists:users'],
            'department_id' => ['integer', 'exists:employees_departments'],
            'job_id' => ['integer', 'exists:employees_job_positions'],
            'partner_id' => ['integer', 'exists:partners_partners'],
            'work_location_id' => ['integer', 'exists:employees_work_locations'],
            'parent_id' => ['integer', 'exists:employees_employees'],
            'coach_id' => ['integer', 'exists:employees_employees'],
            'country_id' => ['integer', 'exists:countries'],
            'state_id' => ['integer', 'exists:states'],
            'private_state_id' => ['integer', 'exists:states'],
            'private_country_id' => ['integer', 'exists:countries'],
            'departure_reason_id' => ['integer', 'exists:employees_departure_reasons'],
            'attendance_manager_id' => ['integer', 'exists:users'],
            'leave_manager_id' => ['integer', 'exists:users'],
            'notes' => ['string'],
        ];
    }
}
