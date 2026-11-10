<?php

namespace app\models;

use app\components\BaseModel;

class ProductAttribute extends BaseModel
{
    public static function tableName(): string
    {
        return 'products_product_attributes';
    }

    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    public function getAttributeModel()
    {
        return $this->hasOne(Attribute::class, ['id' => 'attribute_id'])->withoutScopesAll();
    }

    public function getValues()
    {
        return $this->hasMany(ProductAttributeValue::class, ['product_attribute_id' => 'id']);
    }

    public function getOptions()
    {
        return $this->hasMany(AttributeOption::class, ['id' => 'attribute_option_id'])
            ->viaTable('products_product_attribute_values', ['product_attribute_id' => 'id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'sort', 'product_id', 'attribute_id', 'creator_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['sort' => 'int', 'created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return [
            'attribute' => 'attributeModel',
            'values' => 'values',
            'options' => 'options',
            'creator' => 'creator',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'attribute_id' => ['required', 'integer', 'exists:products_attributes'],
            'sort' => ['integer', 'min_num:0'],
        ];
    }
}
