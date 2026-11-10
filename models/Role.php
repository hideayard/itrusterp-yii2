<?php

namespace app\models;

use app\components\BaseModel;

class Role extends BaseModel
{
    public static function tableName(): string
    {
        return 'roles';
    }

    public function getPermissions()
    {
        return $this->hasMany(Permission::class, ['id' => 'permission_id'])
            ->viaTable('role_has_permissions', ['role_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'guard_name', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['permissions' => 'permissions'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'guard_name' => ['string', 'max:32'],
        ];
    }
}
