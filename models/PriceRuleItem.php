<?php

namespace app\models;

use app\components\BaseModel;

class PriceRuleItem extends BaseModel
{
    public const APPLY_TO_VARIANT = 'variant';
    public const APPLY_TO_PRODUCT = 'product';
    public const APPLY_TO_CATEGORY = 'category';
    public const APPLY_TO_GLOBAL = 'global';

    public const BASE_LIST_PRICE = 'list_price';
    public const BASE_STANDARD_PRICE = 'standard_price';
    public const BASE_PRICE_RULES = 'price_rules';

    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FORMULA = 'formula';
    public const TYPE_FIXED = 'fixed';

    public static function tableName(): string
    {
        return 'products_price_rule_items';
    }

    public static function isCompanyScoped(): bool
    {
        return true;
    }

    public function getPriceList()
    {
        return $this->hasOne(PriceList::class, ['id' => 'price_list_id']);
    }

    public function getBasePriceList()
    {
        return $this->hasOne(PriceList::class, ['id' => 'base_price_list_id']);
    }

    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    public function getCategory()
    {
        return $this->hasOne(Category::class, ['id' => 'category_id']);
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

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        if ($this->type === null || $this->type === '') {
            $this->type = self::TYPE_FIXED;
        }
        if ($this->base === null || $this->base === '') {
            $this->base = self::BASE_LIST_PRICE;
        }
        if ($this->price_list_id) {
            $list = PriceList::findUnscoped()->where(['id' => $this->price_list_id])->one();
            $this->company_id = $list?->company_id;
            $this->currency_id = $list?->currency_id;
        }
        if ($this->display_apply_to === null || $this->display_apply_to === '') {
            $this->display_apply_to = $this->apply_to === self::APPLY_TO_CATEGORY
                ? self::APPLY_TO_CATEGORY
                : self::APPLY_TO_PRODUCT;
        }
        $this->computeApplyTo();
        $this->computeBase();
        return true;
    }

    private function computeApplyTo(): void
    {
        $display = $this->display_apply_to;
        $this->display_apply_to = $display === self::APPLY_TO_CATEGORY
            ? self::APPLY_TO_CATEGORY
            : self::APPLY_TO_PRODUCT;

        if ($this->display_apply_to === self::APPLY_TO_CATEGORY) {
            $this->product_id = null;
            $this->apply_to = $this->category_id ? self::APPLY_TO_CATEGORY : self::APPLY_TO_GLOBAL;
            return;
        }

        $this->category_id = null;

        if (!$this->product_id) {
            $this->apply_to = self::APPLY_TO_GLOBAL;
            return;
        }

        $parentId = Product::findUnscoped()
            ->withTrashed()
            ->where(['id' => $this->product_id])
            ->select('parent_id')
            ->scalar();
        $this->apply_to = $parentId ? self::APPLY_TO_VARIANT : self::APPLY_TO_PRODUCT;
    }

    private function computeBase(): void
    {
        if ($this->type === self::TYPE_FIXED) {
            $this->base_price_list_id = null;
            $this->base = self::BASE_LIST_PRICE;
            return;
        }
        if ($this->type === self::TYPE_PERCENTAGE) {
            $this->base = $this->base_price_list_id ? self::BASE_PRICE_RULES : self::BASE_LIST_PRICE;
            return;
        }
        if ($this->base !== self::BASE_PRICE_RULES) {
            $this->base_price_list_id = null;
        }
    }

    public static function apiFields(): array
    {
        return [
            'id', 'price_list_id', 'apply_to', 'type', 'base', 'product_id', 'category_id',
            'base_price_list_id', 'min_quantity', 'fixed_price', 'percent_price', 'price_discount',
            'price_markup', 'price_round', 'price_surcharge', 'price_min_margin', 'price_max_margin',
            'starts_at', 'ends_at', 'currency_id', 'company_id', 'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'min_quantity' => 'float',
            'fixed_price' => 'float',
            'percent_price' => 'float',
            'price_discount' => 'float',
            'price_markup' => 'float',
            'price_round' => 'float',
            'price_surcharge' => 'float',
            'price_min_margin' => 'float',
            'price_max_margin' => 'float',
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
            'category' => 'category',
            'currency' => 'currency',
            'price_list' => 'priceList',
            'base_price_list' => 'basePriceList',
            'company' => 'company',
            'creator' => 'creator',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'price_list_id' => ['integer', 'exists:products_product_price_lists'],
            'apply_to' => ['in:variant,product,category,global'],
            'type' => ['in:percentage,formula,fixed'],
            'base' => ['in:list_price,standard_price,price_rules'],
            'display_apply_to' => ['in:product,category'],
            'product_id' => ['integer', 'exists:products_products'],
            'category_id' => ['integer', 'exists:products_categories'],
            'base_price_list_id' => ['integer', 'exists:products_product_price_lists'],
            'min_quantity' => ['number', 'min_num:0'],
            'fixed_price' => ['number'],
            'percent_price' => ['number'],
            'price_discount' => ['number'],
            'price_markup' => ['number'],
            'price_round' => ['number', 'min_num:0'],
            'price_surcharge' => ['number'],
            'price_min_margin' => ['number'],
            'price_max_margin' => ['number'],
            'starts_at' => ['date'],
            'ends_at' => ['date'],
            'currency_id' => ['integer', 'exists:currencies'],
            'company_id' => ['integer'],
        ];
    }
}
