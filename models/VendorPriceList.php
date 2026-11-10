<?php

namespace app\models;

use app\components\BaseModel;

class VendorPriceList extends BaseModel
{
    public static function tableName(): string
    {
        return 'products_product_suppliers';
    }

    public static function isCompanyScoped(): bool
    {
        return true;
    }

    public static function autoAssignCompany(): bool
    {
        return true;
    }

    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
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

    public static function apiFields(): array
    {
        return [
            'id', 'sort', 'delay', 'product_name', 'product_code', 'starts_at', 'ends_at',
            'min_qty', 'price', 'discount', 'product_id', 'partner_id', 'currency_id',
            'company_id', 'creator_id', 'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'sort' => 'int',
            'delay' => 'int',
            'min_qty' => 'float',
            'price' => 'float',
            'discount' => 'float',
            'starts_at' => 'iso',
            'ends_at' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'product' => 'product',
            'partner' => 'partner',
            'currency' => 'currency',
            'company' => 'company',
            'creator' => 'creator',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'partner_id' => ['required', 'integer', 'exists:partners_partners'],
            'product_id' => ['required', 'integer', 'exists:products_products'],
            'currency_id' => ['required', 'integer', 'exists:currencies'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
            'product_name' => ['string', 'max:255'],
            'product_code' => ['string', 'max:255'],
            'delay' => ['integer', 'min_num:0', 'max_num:99999999'],
            'min_qty' => ['number', 'min_num:0', 'max_num:99999999999'],
            'price' => ['number', 'min_num:0', 'max_num:99999999999'],
            'discount' => ['number', 'min_num:0', 'max_num:99999999999'],
            'starts_at' => ['date'],
            'ends_at' => ['date'],
            'sort' => ['integer'],
        ];
    }
}
