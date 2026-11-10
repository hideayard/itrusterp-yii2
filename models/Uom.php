<?php

namespace app\models;

use app\components\BaseModel;
use app\components\UsageRegistry;
use app\components\ValidationException;
use yii\db\Query;

class Uom extends BaseModel
{
    public const TYPE_REFERENCE = 'reference';
    public const TYPE_BIGGER = 'bigger';
    public const TYPE_SMALLER = 'smaller';

    public static function tableName(): string
    {
        return 'unit_of_measures';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public function getCategory()
    {
        return $this->hasOne(UomCategory::class, ['id' => 'category_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function delete()
    {
        $product = (new Query())->from('products_products')
            ->where(['or', ['uom_id' => $this->id], ['uom_po_id' => $this->id]])
            ->one();
        if (!empty($product)) {
            throw new ValidationException(
                ['uom' => ["UOM {$this->name} is in use by product {$product['name']}."]],
                "UOM {$this->name} is in use by product {$product['name']}."
            );
        }
        return parent::delete();
    }

    public static function apiFields(): array
    {
        return ['id', 'type', 'name', 'factor', 'rounding', 'category_id', 'creator_id', 'created_at', 'updated_at', 'deleted_at'];
    }

    public static function apiCasts(): array
    {
        return ['created_at' => 'iso', 'updated_at' => 'iso', 'deleted_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['category' => 'category', 'creator' => 'creator'];
    }

    public static function apiRules(): array
    {
        return [
            'type' => ['required', 'in:reference,bigger,smaller'],
            'name' => ['required', 'string', 'max:255'],
            'factor' => ['required', 'number', 'min_num:0'],
            'rounding' => ['required', 'number', 'min_num:0'],
            'category_id' => ['required', 'integer', 'exists:unit_of_measure_categories'],
        ];
    }
}
