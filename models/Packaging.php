<?php

namespace app\models;

use app\components\BaseModel;

class Packaging extends BaseModel
{
    public static function tableName(): string
    {
        return 'products_packagings';
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

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'barcode', 'qty', 'sort', 'product_id', 'creator_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['sort' => 'int', 'created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['product' => 'product', 'creator' => 'creator', 'company' => 'company'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'barcode' => ['string', 'max:255', 'barcode:packaging'],
            'qty' => ['required', 'number', 'min_num:0'],
            'sort' => ['integer'],
            'product_id' => ['required', 'integer', 'exists:products_products'],
            'company_id' => ['integer', 'company_allowed'],
        ];
    }
}
