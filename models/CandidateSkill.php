<?php

namespace app\models;

use app\components\BaseModel;

class CandidateSkill extends BaseModel
{
    public static function tableName(): string
    {
        return 'recruitments_candidate_skills';
    }

    public function getCandidate()
    {
        return $this->hasOne(Candidate::class, ['id' => 'candidate_id']);
    }

    public function getSkill()
    {
        return $this->hasOne(Skill::class, ['id' => 'skill_id']);
    }

    public function getSkillLevel()
    {
        return $this->hasOne(SkillLevel::class, ['id' => 'skill_level_id']);
    }

    public function getSkillType()
    {
        return $this->hasOne(SkillType::class, ['id' => 'skill_type_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'candidate_id', 'skill_id', 'skill_level_id', 'skill_type_id', 'creator_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return [
            'candidate' => 'candidate',
            'skill' => 'skill',
            'skill_level' => 'skillLevel',
            'skill_type' => 'skillType',
            'creator' => 'creator',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'candidate_id' => ['required', 'integer', 'exists:recruitments_candidates'],
            'skill_id' => ['required', 'integer', 'exists:employees_skills'],
            'skill_level_id' => ['required', 'integer', 'exists:employees_skill_levels'],
            'skill_type_id' => ['required', 'integer', 'exists:employees_skill_types'],
        ];
    }
}
