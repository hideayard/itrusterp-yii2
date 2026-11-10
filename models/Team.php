<?php

namespace app\models;

use app\components\BaseModel;

class Team extends BaseModel
{
    public static function tableName(): string
    {
        return 'teams';
    }

    public static function isOwnershipScoped(): bool
    {
        return true;
    }

    public static function ownershipSources(): array
    {
        return ['creator_id'];
    }

    public function getUsers()
    {
        return $this->hasMany(User::class, ['id' => 'user_id'])
            ->viaTable('user_team', ['team_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['users' => 'users', 'creator' => 'creator'];
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiRules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }
}
