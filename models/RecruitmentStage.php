<?php

namespace app\models;

use app\components\BaseModel;

class RecruitmentStage extends BaseModel
{
    public static function tableName(): string
    {
        return 'recruitments_stages';
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $this->legend_blocked ??= 'Blocked';
        $this->legend_done ??= 'Done';
        $this->legend_normal ??= 'In Progress';
        $this->is_default ??= 0;
        $this->fold ??= 0;
        $this->sort ??= 0;

        return true;
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getJobs()
    {
        return $this->hasMany(JobPosition::class, ['id' => 'job_id'])
            ->viaTable('recruitments_stages_jobs', ['stage_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'is_default', 'sort', 'creator_id', 'name', 'legend_blocked', 'legend_done', 'legend_normal', 'requirements', 'hired_stage', 'fold', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return [
            'is_default' => 'bool',
            'fold' => 'bool',
            'sort' => 'int',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return ['creator' => 'creator', 'jobs' => 'jobs'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'legend_blocked' => ['string', 'max:255'],
            'legend_done' => ['string', 'max:255'],
            'legend_normal' => ['string', 'max:255'],
            'requirements' => ['string'],
            'hired_stage' => ['string', 'max:255'],
            'is_default' => ['boolean'],
            'fold' => ['boolean'],
            'sort' => ['integer'],
        ];
    }
}
