<?php

namespace app\models;

use app\components\BaseModel;

class SkillType extends BaseModel
{
    public static function tableName(): string
    {
        return 'employees_skill_types';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getSkills()
    {
        return $this->hasMany(Skill::class, ['skill_type_id' => 'id']);
    }

    public function getLevels()
    {
        return $this->hasMany(SkillLevel::class, ['skill_type_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'color', 'is_active', 'creator_id', 'created_at', 'updated_at', 'deleted_at'];
    }

    public static function apiCasts(): array
    {
        return ['is_active' => 'bool', 'created_at' => 'iso', 'updated_at' => 'iso', 'deleted_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['creator' => 'creator', 'skills' => 'skills', 'levels' => 'levels'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['string', 'max:32'],
            'is_active' => ['boolean'],
        ];
    }
}
