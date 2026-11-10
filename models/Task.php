<?php

namespace app\models;

use app\components\BaseModel;

class Task extends BaseModel
{
    public static function tableName(): string
    {
        return 'projects_tasks';
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
        return $this->hasOne(TaskStage::class, ['id' => 'stage_id']);
    }

    public function getProject()
    {
        return $this->hasOne(Project::class, ['id' => 'project_id']);
    }

    public function getMilestone()
    {
        return $this->hasOne(Milestone::class, ['id' => 'milestone_id']);
    }

    public function getPartner()
    {
        return $this->hasOne(Partner::class, ['id' => 'partner_id']);
    }

    public function getParent()
    {
        return $this->hasOne(self::class, ['id' => 'parent_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getSubTasks()
    {
        return $this->hasMany(self::class, ['parent_id' => 'id']);
    }

    public function getUsers()
    {
        return $this->hasMany(User::class, ['id' => 'user_id'])
            ->viaTable('projects_task_users', ['task_id' => 'id']);
    }

    public function getTags()
    {
        return $this->hasMany(ProjectTag::class, ['id' => 'tag_id'])
            ->viaTable('projects_task_tag', ['task_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'title', 'description', 'color', 'priority', 'state', 'sort', 'is_active',
            'is_recurring', 'deadline', 'working_hours_open', 'working_hours_close',
            'allocated_hours', 'remaining_hours', 'effective_hours', 'total_hours_spent',
            'subtask_effective_hours', 'overtime', 'progress', 'stage_id', 'project_id',
            'partner_id', 'parent_id', 'company_id', 'creator_id', 'created_at', 'updated_at', 'deleted_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'priority' => 'bool',
            'sort' => 'int',
            'is_active' => 'bool',
            'is_recurring' => 'bool',
            'deadline' => 'iso',
            'working_hours_open' => 'float',
            'working_hours_close' => 'float',
            'allocated_hours' => 'float',
            'remaining_hours' => 'float',
            'effective_hours' => 'float',
            'total_hours_spent' => 'float',
            'subtask_effective_hours' => 'float',
            'overtime' => 'float',
            'progress' => 'float',
            'created_at' => 'iso',
            'updated_at' => 'iso',
            'deleted_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'stage' => 'stage',
            'project' => 'project',
            'milestone' => 'milestone',
            'partner' => 'partner',
            'parent' => 'parent',
            'company' => 'company',
            'creator' => 'creator',
            'sub_tasks' => 'subTasks',
            'users' => 'users',
            'tags' => 'tags',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'state' => ['required', 'in:in_progress,change_requested,approved,cancelled,done'],
            'stage_id' => ['required', 'integer', 'exists:projects_task_stages'],
            'project_id' => ['integer', 'exists:projects_projects'],
            'milestone_id' => ['integer', 'exists:projects_milestones'],
            'partner_id' => ['integer', 'exists:partners_partners'],
            'parent_id' => ['integer', 'exists:projects_tasks'],
            'deadline' => ['date'],
            'allocated_hours' => ['number', 'min_num:0'],
            'priority' => ['boolean'],
            'description' => ['string'],
            'color' => ['string', 'max:7'],
            'is_active' => ['boolean'],
            'is_recurring' => ['boolean'],
            'sort' => ['integer'],
        ];
    }
}
