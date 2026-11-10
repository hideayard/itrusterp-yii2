<?php

namespace app\models;

use Yii;
use app\components\BaseModel;

class Applicant extends BaseModel
{
    public static function tableName(): string
    {
        return 'recruitments_applicants';
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
        if ($insert) {
            $this->create_date ??= date('Y-m-d H:i:s');
            $this->date_opened ??= $this->create_date;
        }

        return true;
    }

    public function getCandidate()
    {
        return $this->hasOne(Candidate::class, ['id' => 'candidate_id']);
    }

    public function getStage()
    {
        return $this->hasOne(RecruitmentStage::class, ['id' => 'stage_id']);
    }

    public function getLastStage()
    {
        return $this->hasOne(RecruitmentStage::class, ['id' => 'last_stage_id']);
    }

    public function getJob()
    {
        return $this->hasOne(JobPosition::class, ['id' => 'job_id']);
    }

    public function getDepartment()
    {
        return $this->hasOne(Department::class, ['id' => 'department_id']);
    }

    public function getRecruiter()
    {
        return $this->hasOne(User::class, ['id' => 'recruiter_id']);
    }

    public function getRefuseReason()
    {
        return $this->hasOne(RefuseReason::class, ['id' => 'refuse_reason_id']);
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
            ->viaTable('recruitments_applicant_applicant_categories', ['applicant_id' => 'id']);
    }

    public function getInterviewers()
    {
        return $this->hasMany(User::class, ['id' => 'interviewer_id'])
            ->viaTable('recruitments_applicant_interviewers', ['applicant_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'source_id', 'medium_id', 'candidate_id', 'stage_id', 'last_stage_id',
            'company_id', 'recruiter_id', 'job_id', 'department_id', 'refuse_reason_id',
            'creator_id', 'email_cc', 'priority', 'salary_proposed_extra', 'salary_expected_extra',
            'applicant_properties', 'applicant_notes', 'is_active', 'state', 'create_date',
            'date_closed', 'date_opened', 'date_last_stage_updated', 'refuse_date', 'probability',
            'salary_proposed', 'salary_expected', 'delay_close', 'created_at', 'updated_at', 'deleted_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'applicant_properties' => 'json',
            'is_active' => 'bool',
            'probability' => 'float',
            'salary_proposed' => 'float',
            'salary_expected' => 'float',
            'delay_close' => 'float',
            'create_date' => 'iso',
            'date_closed' => 'iso',
            'date_opened' => 'iso',
            'date_last_stage_updated' => 'iso',
            'refuse_date' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
            'deleted_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'candidate' => 'candidate',
            'stage' => 'stage',
            'last_stage' => 'lastStage',
            'job' => 'job',
            'department' => 'department',
            'recruiter' => 'recruiter',
            'refuse_reason' => 'refuseReason',
            'company' => 'company',
            'creator' => 'creator',
            'categories' => 'categories',
            'interviewers' => 'interviewers',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'candidate_id' => ['required', 'integer', 'exists:recruitments_candidates'],
            'stage_id' => ['integer', 'exists:recruitments_stages'],
            'last_stage_id' => ['integer', 'exists:recruitments_stages'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
            'recruiter_id' => ['integer', 'exists:users'],
            'job_id' => ['integer', 'exists:employees_job_positions'],
            'department_id' => ['integer', 'exists:employees_departments'],
            'refuse_reason_id' => ['integer', 'exists:recruitments_refuse_reasons'],
            'email_cc' => ['string', 'max:255'],
            'priority' => ['string', 'max:16'],
            'salary_proposed_extra' => ['string', 'max:255'],
            'salary_expected_extra' => ['string', 'max:255'],
            'applicant_notes' => ['string'],
            'is_active' => ['boolean'],
            'state' => ['string', 'max:32'],
            'probability' => ['number'],
            'salary_proposed' => ['number'],
            'salary_expected' => ['number'],
            'delay_close' => ['number'],
            'create_date' => ['date'],
            'date_closed' => ['date'],
            'date_opened' => ['date'],
            'date_last_stage_updated' => ['date'],
            'refuse_date' => ['date'],
        ];
    }
}
