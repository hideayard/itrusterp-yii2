<?php

namespace app\models;

use app\components\BaseModel;

class EmployeeSkill extends BaseModel
{
    public static function tableName(): string
    {
        return 'employees_employee_skills';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public function getEmployee()
    {
        return $this->hasOne(Employee::class, ['id' => 'employee_id']);
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
        return ['id', 'employee_id', 'skill_id', 'skill_level_id', 'skill_type_id', 'creator_id', 'created_at', 'updated_at', 'deleted_at'];
    }

    public static function apiCasts(): array
    {
        return ['created_at' => 'iso', 'updated_at' => 'iso', 'deleted_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return [
            'employee' => 'employee',
            'skill' => 'skill',
            'skill_level' => 'skillLevel',
            'skill_type' => 'skillType',
            'creator' => 'creator',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees_employees'],
            'skill_id' => ['required', 'integer', 'exists:employees_skills'],
            'skill_level_id' => ['integer', 'exists:employees_skill_levels'],
            'skill_type_id' => ['integer', 'exists:employees_skill_types'],
        ];
    }
}
