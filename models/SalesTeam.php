<?php

namespace app\models;

use app\components\BaseModel;

class SalesTeam extends BaseModel
{
    public static function tableName(): string
    {
        return 'sales_teams';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'color', 'is_active', 'invoiced_target', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return [
            'is_active' => 'bool',
            'invoiced_target' => 'float',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return ['creator' => 'creator'];
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['string', 'max:7'],
            'is_active' => ['boolean'],
            'invoiced_target' => ['number', 'min_num:0'],
        ];
    }
}
