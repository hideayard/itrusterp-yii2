<?php

namespace app\models;

use app\components\BaseModel;

class Skill extends BaseModel
{
    public static function tableName(): string
    {
        return 'employees_skills';
    }

    public static function isSoftDelete(): bool
    {
        return true;
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
        return ['id', 'sort', 'name', 'skill_type_id', 'creator_id', 'created_at', 'updated_at', 'deleted_at'];
    }

    public static function apiCasts(): array
    {
        return ['sort' => 'int', 'created_at' => 'iso', 'updated_at' => 'iso', 'deleted_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['skill_type' => 'skillType', 'creator' => 'creator'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'skill_type_id' => ['integer', 'exists:employees_skill_types'],
            'sort' => ['integer'],
        ];
    }
}
