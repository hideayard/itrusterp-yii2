<?php

namespace app\models;

use app\components\BaseModel;

class PurchaseAgreementLine extends BaseModel
{
    public static function tableName(): string
    {
        return 'purchases_requisition_lines';
    }

    public function getRequisition()
    {
        return $this->hasOne(PurchaseAgreement::class, ['id' => 'requisition_id']);
    }

    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    public function getUom()
    {
        return $this->hasOne(Uom::class, ['id' => 'uom_id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'qty', 'price_unit', 'requisition_id', 'product_id', 'uom_id', 'company_id', 'creator_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['qty' => 'float', 'price_unit' => 'float', 'created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['product' => 'product', 'uom' => 'uom'];
    }

    public static function apiRules(): array
    {
        return [
            'qty' => ['number', 'min_num:0'],
            'price_unit' => ['number', 'min_num:0'],
            'product_id' => ['required', 'integer', 'exists:products_products'],
            'uom_id' => ['integer', 'exists:unit_of_measures'],
        ];
    }
}
