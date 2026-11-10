<?php

namespace app\models;

use app\components\BaseModel;

class Attribute extends BaseModel
{
    public static function tableName(): string
    {
        return 'products_attributes';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public function getOptions()
    {
        return $this->hasMany(AttributeOption::class, ['attribute_id' => 'id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'type', 'sort', 'creator_id', 'created_at', 'updated_at', 'deleted_at'];
    }

    public static function apiCasts(): array
    {
        return ['sort' => 'int', 'created_at' => 'iso', 'updated_at' => 'iso', 'deleted_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['options' => 'options', 'creator' => 'creator'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:radio,select,color'],
            'sort' => ['integer', 'min_num:0'],
        ];
    }
}
