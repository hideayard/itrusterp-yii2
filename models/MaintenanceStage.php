<?php

namespace app\models;

use app\components\BaseModel;

class MaintenanceStage extends BaseModel
{
    public static function tableName(): string
    {
        return 'maintenance_stages';
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $this->sort ??= 0;
        $this->done ??= 0;

        return true;
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getRequests()
    {
        return $this->hasMany(MaintenanceRequest::class, ['stage_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'sort', 'name', 'done', 'creator_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['sort' => 'int', 'done' => 'bool', 'created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['creator' => 'creator', 'requests' => 'requests'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sort' => ['integer'],
            'done' => ['boolean'],
        ];
    }
}
