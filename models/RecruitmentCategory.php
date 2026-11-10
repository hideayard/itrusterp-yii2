<?php

namespace app\models;

use app\components\BaseModel;

class RecruitmentCategory extends BaseModel
{
    public static function tableName(): string
    {
        return 'recruitments_applicant_categories';
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getApplicants()
    {
        return $this->hasMany(Applicant::class, ['id' => 'applicant_id'])
            ->viaTable('recruitments_applicant_applicant_categories', ['category_id' => 'id']);
    }

    public function getCandidates()
    {
        return $this->hasMany(Candidate::class, ['id' => 'candidate_id'])
            ->viaTable('recruitments_candidate_applicant_categories', ['category_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'creator_id', 'name', 'color', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['creator' => 'creator', 'applicants' => 'applicants', 'candidates' => 'candidates'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['string', 'max:32'],
        ];
    }
}
