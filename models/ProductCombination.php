<?php

namespace app\models;

use app\components\BaseModel;

class ProductCombination extends BaseModel
{
    public static function tableName(): string
    {
        return 'products_product_combinations';
    }

    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    public function getAttributeValue()
    {
        return $this->hasOne(ProductAttributeValue::class, ['id' => 'product_attribute_value_id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'product_id', 'product_attribute_value_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['product' => 'product', 'attribute_value' => 'attributeValue'];
    }

    public static function apiRules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products_products'],
            'product_attribute_value_id' => ['required', 'integer', 'exists:products_product_attribute_values'],
        ];
    }
}
