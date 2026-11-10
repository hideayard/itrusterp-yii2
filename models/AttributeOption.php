<?php

namespace app\models;

use app\components\BaseModel;

class AttributeOption extends BaseModel
{
    public static function tableName(): string
    {
        return 'products_attribute_options';
    }

    public function getAttributeModel()
    {
        return $this->hasOne(Attribute::class, ['id' => 'attribute_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'color', 'extra_price', 'sort', 'attribute_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['extra_price' => 'float', 'sort' => 'int', 'created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['attribute' => 'attributeModel', 'creator' => 'creator'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['string', 'max:7'],
            'extra_price' => ['number', 'min_num:0'],
            'sort' => ['integer', 'min_num:0'],
            'attribute_id' => ['required', 'integer', 'exists:products_attributes'],
        ];
    }
}
