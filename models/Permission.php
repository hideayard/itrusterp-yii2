<?php

namespace app\models;

use app\components\BaseModel;

class Permission extends BaseModel
{
    public static function tableName(): string
    {
        return 'permissions';
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'guard_name', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'guard_name' => ['string', 'max:32'],
        ];
    }
}
