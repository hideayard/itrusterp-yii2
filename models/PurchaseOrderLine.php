<?php

namespace app\models;

use app\components\BaseModel;

class PurchaseOrderLine extends BaseModel
{
    public static function tableName(): string
    {
        return 'purchases_order_lines';
    }

    public function getOrder()
    {
        return $this->hasOne(PurchaseOrder::class, ['id' => 'order_id']);
    }

    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    public function getUom()
    {
        return $this->hasOne(Uom::class, ['id' => 'uom_id']);
    }

    public function getProductPackaging()
    {
        return $this->hasOne(Packaging::class, ['id' => 'product_packaging_id']);
    }

    public function getPartner()
    {
        return $this->hasOne(Partner::class, ['id' => 'partner_id']);
    }

    public function getCurrency()
    {
        return $this->hasOne(Currency::class, ['id' => 'currency_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getTaxes()
    {
        return $this->hasMany(SalesTax::class, ['id' => 'tax_id'])
            ->viaTable('purchases_order_line_taxes', ['order_line_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'name', 'state', 'sort', 'qty_received_method', 'display_type', 'product_qty',
            'product_uom_qty', 'product_packaging_qty', 'price_tax', 'discount', 'price_unit',
            'price_subtotal', 'price_total', 'qty_invoiced', 'qty_received', 'qty_received_manual',
            'qty_to_invoice', 'is_downpayment', 'planned_at', 'product_description_variants',
            'propagate_cancel', 'price_total_cc', 'uom_id', 'product_id', 'product_packaging_id',
            'order_id', 'partner_id', 'currency_id', 'company_id', 'creator_id',
            'final_location_id', 'order_point_id', 'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'sort' => 'int',
            'product_qty' => 'float',
            'product_uom_qty' => 'float',
            'product_packaging_qty' => 'float',
            'price_tax' => 'float',
            'discount' => 'float',
            'price_unit' => 'float',
            'price_subtotal' => 'float',
            'price_total' => 'float',
            'qty_invoiced' => 'float',
            'qty_received' => 'float',
            'qty_received_manual' => 'float',
            'qty_to_invoice' => 'float',
            'is_downpayment' => 'bool',
            'propagate_cancel' => 'bool',
            'price_total_cc' => 'float',
            'planned_at' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'uom' => 'uom',
            'product' => 'product',
            'product_packaging' => 'productPackaging',
            'order' => 'order',
            'partner' => 'partner',
            'currency' => 'currency',
            'company' => 'company',
            'creator' => 'creator',
            'taxes' => 'taxes',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products_products'],
            'planned_at' => ['required', 'date'],
            'product_qty' => ['required', 'number', 'min_num:0', 'max_num:99999999999'],
            'uom_id' => ['integer', 'exists:unit_of_measures'],
            'product_packaging_qty' => ['number', 'min_num:0', 'max_num:99999999999'],
            'product_packaging_id' => ['integer', 'exists:products_packagings'],
            'price_unit' => ['required', 'number', 'min_num:0', 'max_num:99999999999'],
            'discount' => ['number', 'min_num:0', 'max_num:100'],
            'qty_received' => ['number', 'min_num:0', 'max_num:99999999999'],
            'qty_received_manual' => ['number', 'min_num:0', 'max_num:99999999999'],
            'name' => ['string'],
        ];
    }
}
