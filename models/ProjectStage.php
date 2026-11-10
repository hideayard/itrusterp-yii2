<?php

namespace app\models;

use app\components\BaseModel;

class ProjectStage extends BaseModel
{
    public static function tableName(): string
    {
        return 'projects_project_stages';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getProjects()
    {
        return $this->hasMany(Project::class, ['stage_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'is_active', 'is_collapsed', 'sort', 'company_id', 'creator_id', 'created_at', 'updated_at', 'deleted_at'];
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
        return ['creator' => 'creator', 'company' => 'company', 'projects' => 'projects'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:projects_project_stages,name'],
            'is_active' => ['boolean'],
            'is_collapsed' => ['boolean'],
            'sort' => ['integer'],
        ];
    }
}
