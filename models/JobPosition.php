<?php

namespace app\models;

use app\components\BaseModel;

class JobPosition extends BaseModel
{
    public static function tableName(): string
    {
        return 'employees_job_positions';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public function getDepartment()
    {
        return $this->hasOne(Department::class, ['id' => 'department_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getManager()
    {
        return $this->hasOne(Employee::class, ['id' => 'manager_id']);
    }

    public function getEmploymentType()
    {
        return $this->hasOne(EmploymentType::class, ['id' => 'employment_type_id']);
    }

    public function getRecruiter()
    {
        return $this->hasOne(User::class, ['id' => 'recruiter_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getInterviewers()
    {
        return $this->hasMany(User::class, ['id' => 'user_id'])
            ->viaTable('recruitments_job_position_interviewers', ['job_position_id' => 'id']);
    }

    public function getStages()
    {
        return $this->hasMany(RecruitmentStage::class, ['id' => 'stage_id'])
            ->viaTable('recruitments_stages_jobs', ['job_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'sort', 'expected_employees', 'no_of_employee', 'no_of_recruitment',
            'department_id', 'company_id', 'address_id', 'manager_id', 'industry_id',
            'recruiter_id', 'no_of_hired_employee', 'date_from', 'date_to', 'creator_id',
            'employment_type_id', 'name', 'description', 'requirements', 'is_active',
            'deleted_at', 'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'sort' => 'int',
            'expected_employees' => 'int',
            'no_of_employee' => 'int',
            'no_of_recruitment' => 'int',
            'no_of_hired_employee' => 'int',
            'date_from' => 'iso',
            'date_to' => 'iso',
            'is_active' => 'bool',
            'deleted_at' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'department' => 'department',
            'company' => 'company',
            'manager' => 'manager',
            'employment_type' => 'employmentType',
            'recruiter' => 'recruiter',
            'creator' => 'creator',
            'interviewers' => 'interviewers',
            'stages' => 'stages',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['string'],
            'requirements' => ['string'],
            'expected_employees' => ['integer'],
            'no_of_recruitment' => ['integer'],
            'department_id' => ['integer', 'exists:employees_departments'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
            'manager_id' => ['integer', 'exists:employees_employees'],
            'recruiter_id' => ['integer', 'exists:users'],
            'employment_type_id' => ['integer', 'exists:employees_employment_types'],
            'date_from' => ['date'],
            'date_to' => ['date'],
            'is_active' => ['boolean'],
        ];
    }
}
