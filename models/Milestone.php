<?php

namespace app\models;

use app\components\BaseModel;

class Milestone extends BaseModel
{
    public static function tableName(): string
    {
        return 'projects_milestones';
    }

    public function getProject()
    {
        return $this->hasOne(Project::class, ['id' => 'project_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'deadline', 'is_completed', 'completed_at', 'project_id', 'creator_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return [
            'is_completed' => 'bool',
            'deadline' => 'iso',
            'completed_at' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return ['project' => 'project', 'creator' => 'creator'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'deadline' => ['date'],
            'is_completed' => ['boolean'],
            'project_id' => ['required', 'integer', 'exists:projects_projects'],
        ];
    }
}
