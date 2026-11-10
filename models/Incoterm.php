<?php

namespace app\models;

use app\components\BaseModel;

class Incoterm extends BaseModel
{
    public static function tableName(): string
    {
        return 'accounts_incoterms';
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
        return ['id', 'code', 'name', 'creator_id', 'created_at', 'updated_at', 'deleted_at'];
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
            'code' => ['required', 'string', 'max:3'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
