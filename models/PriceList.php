<?php

namespace app\models;

use app\components\BaseModel;

class PriceList extends BaseModel
{
    public static function tableName(): string
    {
        return 'products_product_price_lists';
    }

    public static function isCompanyScoped(): bool
    {
        return true;
    }

    public static function autoAssignCompany(): bool
    {
        return true;
    }

    public function getCurrency()
    {
        return $this->hasOne(Currency::class, ['id' => 'currency_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getItems()
    {
        return $this->hasMany(PriceRuleItem::class, ['price_list_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'sort', 'is_active', 'currency_id', 'creator_id', 'company_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['sort' => 'int', 'is_active' => 'bool', 'created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['currency' => 'currency', 'creator' => 'creator', 'company' => 'company', 'items' => 'items'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'currency_id' => ['required', 'integer', 'exists:currencies'],
            'company_id' => ['integer', 'company_allowed'],
            'sort' => ['integer'],
            'is_active' => ['boolean'],
        ];
    }
}
