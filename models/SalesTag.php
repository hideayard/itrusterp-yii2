<?php

namespace app\models;

use app\components\BaseModel;

class SalesTag extends BaseModel
{
    public static function tableName(): string
    {
        return 'sales_tags';
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'color', 'creator_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['creator' => 'creator'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:sales_tags,name'],
            'color' => ['string', 'max:7'],
        ];
    }
}
