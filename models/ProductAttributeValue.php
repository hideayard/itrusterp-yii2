<?php

namespace app\models;

use app\components\BaseModel;

class ProductAttributeValue extends BaseModel
{
    public static function tableName(): string
    {
        return 'products_product_attribute_values';
    }

    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    public function getAttributeModel()
    {
        return $this->hasOne(Attribute::class, ['id' => 'attribute_id'])->withoutScopesAll();
    }

    public function getProductAttribute()
    {
        return $this->hasOne(ProductAttribute::class, ['id' => 'product_attribute_id']);
    }

    public function getAttributeOption()
    {
        return $this->hasOne(AttributeOption::class, ['id' => 'attribute_option_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'extra_price', 'product_id', 'attribute_id', 'product_attribute_id', 'attribute_option_id'];
    }

    public static function apiCasts(): array
    {
        return ['extra_price' => 'float'];
    }

    public static function apiRelations(): array
    {
        return [
            'attribute' => 'attributeModel',
            'product_attribute' => 'productAttribute',
            'attribute_option' => 'attributeOption',
            'creator' => 'creator',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'extra_price' => ['number', 'min_num:0'],
            'attribute_id' => ['required', 'integer', 'exists:products_attributes'],
            'product_attribute_id' => ['required', 'integer', 'exists:products_product_attributes'],
            'attribute_option_id' => ['required', 'integer', 'exists:products_attribute_options'],
            'product_id' => ['integer'],
        ];
    }
}
