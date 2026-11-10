<?php

namespace app\models;

use Yii;
use app\components\BaseModel;

class Candidate extends BaseModel
{
    public static function tableName(): string
    {
        return 'recruitments_candidates';
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
        $this->is_active ??= 1;
        $this->message_bounced ??= 0;
        $this->priority ??= 0;

        return true;
    }

    public function getDegree()
    {
        return $this->hasOne(RecruitmentDegree::class, ['id' => 'degree_id']);
    }

    public function getPartner()
    {
        return $this->hasOne(Partner::class, ['id' => 'partner_id']);
    }

    public function getManager()
    {
        return $this->hasOne(Employee::class, ['id' => 'manager_id']);
    }

    public function getEmployee()
    {
        return $this->hasOne(Employee::class, ['id' => 'employee_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getCategories()
    {
        return $this->hasMany(RecruitmentCategory::class, ['id' => 'category_id'])
            ->viaTable('recruitments_candidate_applicant_categories', ['candidate_id' => 'id']);
    }

    public function getSkills()
    {
        return $this->hasMany(CandidateSkill::class, ['candidate_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'message_bounced', 'company_id', 'partner_id', 'degree_id', 'manager_id',
            'employee_id', 'creator_id', 'email_cc', 'name', 'email_from', 'phone',
            'linkedin_profile', 'priority', 'availability_date', 'candidate_properties',
            'is_active', 'color', 'created_at', 'updated_at', 'deleted_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'message_bounced' => 'bool',
            'priority' => 'int',
            'candidate_properties' => 'json',
            'is_active' => 'bool',
            'availability_date' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
            'deleted_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'degree' => 'degree',
            'partner' => 'partner',
            'manager' => 'manager',
            'employee' => 'employee',
            'company' => 'company',
            'creator' => 'creator',
            'categories' => 'categories',
            'skills' => 'skills',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email_from' => ['email', 'max:255'],
            'phone' => ['string', 'max:64'],
            'linkedin_profile' => ['string', 'max:255'],
            'email_cc' => ['string', 'max:255'],
            'priority' => ['integer'],
            'availability_date' => ['date'],
            'degree_id' => ['integer', 'exists:recruitments_degrees'],
            'partner_id' => ['integer', 'exists:partners_partners'],
            'manager_id' => ['integer', 'exists:employees_employees'],
            'employee_id' => ['integer', 'exists:employees_employees'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
            'is_active' => ['boolean'],
            'color' => ['string', 'max:32'],
        ];
    }
}
