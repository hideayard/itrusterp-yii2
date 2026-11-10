<?php

namespace app\models;

use app\components\BaseModel;

class TaskStage extends BaseModel
{
    public static function tableName(): string
    {
        return 'projects_task_stages';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public function getProject()
    {
        return $this->hasOne(Project::class, ['id' => 'project_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getTasks()
    {
        return $this->hasMany(Task::class, ['stage_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'is_active', 'is_collapsed', 'sort', 'project_id', 'company_id', 'user_id', 'creator_id', 'created_at', 'updated_at', 'deleted_at'];
    }

    public static function apiCasts(): array
    {
        return [
            'is_active' => 'bool',
            'is_collapsed' => 'bool',
            'sort' => 'int',
            'created_at' => 'iso',
            'updated_at' => 'iso',
            'deleted_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'project' => 'project',
            'user' => 'user',
            'creator' => 'creator',
            'company' => 'company',
            'tasks' => 'tasks',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'project_id' => ['required', 'integer', 'exists:projects_projects'],
            'user_id' => ['integer', 'exists:users'],
            'is_active' => ['boolean'],
            'is_collapsed' => ['boolean'],
            'sort' => ['integer'],
        ];
    }
}
