<?php

namespace app\models;

use app\components\BaseModel;

class ProjectTag extends BaseModel
{
    public static function tableName(): string
    {
        return 'projects_tags';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'color', 'creator_id', 'created_at', 'updated_at', 'deleted_at'];
    }

    public static function apiCasts(): array
    {
        return ['created_at' => 'iso', 'updated_at' => 'iso', 'deleted_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['creator' => 'creator'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:projects_tags,name'],
            'color' => ['regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
        ];
    }
}
