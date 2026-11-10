<?php

namespace app\models;

use app\components\BaseModel;

class SalesTax extends BaseModel
{
    public static function tableName(): string
    {
        return 'accounts_taxes';
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'amount', 'amount_type', 'price_include_override', 'include_base_amount', 'type_tax_use'];
    }

    public static function apiCasts(): array
    {
        return ['amount' => 'float', 'include_base_amount' => 'bool'];
    }

    public static function apiRules(): array
    {
        return ['name' => ['string']];
    }
}
