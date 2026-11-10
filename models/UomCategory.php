<?php

namespace app\models;

use app\components\BaseModel;

class UomCategory extends BaseModel
{
    public static function tableName(): string
    {
        return 'unit_of_measure_categories';
    }

    public function getUoms()
    {
        return $this->hasMany(Uom::class, ['category_id' => 'id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
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
        return ['uoms' => 'uoms', 'creator' => 'creator'];
    }

    public static function apiRules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }
}
