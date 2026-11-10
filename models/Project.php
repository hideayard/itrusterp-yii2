<?php

namespace app\models;

use app\components\BaseModel;

class Project extends BaseModel
{
    public static function tableName(): string
    {
        return 'projects_projects';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public static function isCompanyScoped(): bool
    {
        return true;
    }

    public function getStage()
    {
        return $this->hasOne(ProjectStage::class, ['id' => 'stage_id']);
    }

    public function getPartner()
    {
        return $this->hasOne(Partner::class, ['id' => 'partner_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getTasks()
    {
        return $this->hasMany(Task::class, ['project_id' => 'id']);
    }

    public function getTaskStages()
    {
        return $this->hasMany(TaskStage::class, ['project_id' => 'id']);
    }

    public function getMilestones()
    {
        return $this->hasMany(Milestone::class, ['project_id' => 'id']);
    }

    public function getTags()
    {
        return $this->hasMany(ProjectTag::class, ['id' => 'tag_id'])
            ->viaTable('projects_project_tag', ['project_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'name', 'tasks_label', 'description', 'visibility', 'color', 'sort',
            'start_date', 'end_date', 'allocated_hours', 'allow_timesheets', 'allow_milestones',
            'allow_task_dependencies', 'is_active', 'stage_id', 'partner_id', 'company_id',
            'user_id', 'creator_id', 'created_at', 'updated_at', 'deleted_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'sort' => 'int',
            'allocated_hours' => 'float',
            'allow_timesheets' => 'bool',
            'allow_milestones' => 'bool',
            'allow_task_dependencies' => 'bool',
            'is_active' => 'bool',
            'start_date' => 'iso',
            'end_date' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
            'deleted_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'stage' => 'stage',
            'partner' => 'partner',
            'company' => 'company',
            'user' => 'user',
            'creator' => 'creator',
            'tasks' => 'tasks',
            'task_stages' => 'taskStages',
            'milestones' => 'milestones',
            'tags' => 'tags',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'visibility' => ['required', 'in:private,internal,public'],
            'stage_id' => ['required', 'integer', 'exists:projects_project_stages'],
            'user_id' => ['integer', 'exists:users'],
            'partner_id' => ['integer', 'exists:partners_partners'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
            'start_date' => ['date'],
            'end_date' => ['date'],
            'allocated_hours' => ['number', 'min_num:0'],
            'allow_timesheets' => ['boolean'],
            'allow_milestones' => ['boolean'],
            'allow_task_dependencies' => ['boolean'],
            'tasks_label' => ['string', 'max:255'],
            'description' => ['string'],
            'color' => ['string', 'max:7'],
            'is_active' => ['boolean'],
            'sort' => ['integer'],
        ];
    }
}
