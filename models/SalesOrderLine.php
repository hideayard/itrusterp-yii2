<?php

namespace app\models;

use app\components\BaseModel;

class SalesOrderLine extends BaseModel
{
    public static function tableName(): string
    {
        return 'sales_order_lines';
    }

    public function getOrder()
    {
        return $this->hasOne(SalesOrder::class, ['id' => 'order_id']);
    }

    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    public function getUom()
    {
        return $this->hasOne(Uom::class, ['id' => 'product_uom_id']);
    }

    public function getCurrency()
    {
        return $this->hasOne(Currency::class, ['id' => 'currency_id']);
    }

    public function getOrderPartner()
    {
        return $this->hasOne(Partner::class, ['id' => 'order_partner_id']);
    }

    public function getSalesman()
    {
        return $this->hasOne(User::class, ['id' => 'salesman_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getProductPackaging()
    {
        return $this->hasOne(Packaging::class, ['id' => 'product_packaging_id']);
    }

    public function getLinkedSaleOrderSale()
    {
        return $this->hasOne(self::class, ['id' => 'linked_sale_order_sale_id']);
    }

    public function getTaxes()
    {
        return $this->hasMany(SalesTax::class, ['id' => 'tax_id'])
            ->viaTable('sales_order_line_taxes', ['order_line_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'sort', 'name', 'display_type', 'product_uom_qty', 'product_qty', 'qty_delivered',
            'qty_invoiced', 'qty_to_invoice', 'product_packaging_qty', 'price_unit', 'technical_price_unit',
            'discount', 'price_subtotal', 'price_tax', 'price_total', 'price_reduce_taxexcl',
            'price_reduce_taxinc', 'purchase_price', 'margin', 'margin_percent',
            'untaxed_amount_invoiced', 'untaxed_amount_to_invoice', 'state', 'invoice_status',
            'qty_delivered_method', 'virtual_id', 'linked_virtual_id', 'is_downpayment', 'is_expense',
            'customer_lead', 'analytic_distribution', 'order_id', 'product_id', 'linked_sale_order_sale_id',
            'product_uom_id', 'product_packaging_id', 'currency_id', 'order_partner_id', 'salesman_id',
            'warehouse_id', 'route_id', 'company_id', 'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'sort' => 'int',
            'product_uom_qty' => 'float',
            'product_qty' => 'float',
            'qty_delivered' => 'float',
            'qty_invoiced' => 'float',
            'qty_to_invoice' => 'float',
            'product_packaging_qty' => 'float',
            'price_unit' => 'float',
            'technical_price_unit' => 'float',
            'discount' => 'float',
            'price_subtotal' => 'float',
            'price_tax' => 'float',
            'price_total' => 'float',
            'price_reduce_taxexcl' => 'float',
            'price_reduce_taxinc' => 'float',
            'purchase_price' => 'float',
            'margin' => 'float',
            'margin_percent' => 'float',
            'untaxed_amount_invoiced' => 'float',
            'untaxed_amount_to_invoice' => 'float',
            'is_downpayment' => 'bool',
            'is_expense' => 'bool',
            'customer_lead' => 'float',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'product' => 'product',
            'linked_sale_order_sale' => 'linkedSaleOrderSale',
            'product_uom' => 'uom',
            'uom' => 'uom',
            'product_packaging' => 'productPackaging',
            'currency' => 'currency',
            'order_partner' => 'orderPartner',
            'salesman' => 'salesman',
            'company' => 'company',
            'order' => 'order',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products_products'],
            'name' => ['string', 'max:255'],
            'product_qty' => ['required', 'number', 'min_num:0', 'max_num:99999999999'],
            'product_uom_qty' => ['number', 'min_num:0', 'max_num:99999999999'],
            'qty_delivered' => ['number', 'min_num:0', 'max_num:99999999999'],
            'product_packaging_qty' => ['number', 'min_num:0', 'max_num:99999999999'],
            'price_unit' => ['number', 'min_num:0', 'max_num:99999999999'],
            'discount' => ['number', 'min_num:0', 'max_num:100'],
            'customer_lead' => ['number', 'min_num:0', 'max_num:99999999999'],
            'product_uom_id' => ['integer', 'exists:unit_of_measures'],
            'product_packaging_id' => ['integer', 'exists:products_packagings'],
            'display_type' => ['string'],
        ];
    }
}
