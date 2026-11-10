<?php

namespace app\models;

use Yii;
use yii\db\Query;
use app\components\BaseModel;
use app\components\UsageRegistry;
use app\components\ValidationException;

class Product extends BaseModel
{
    public const TYPE_GOODS = 'goods';
    public const TYPE_SERVICE = 'service';

    public static function tableName(): string
    {
        return 'products_products';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public static function isCompanyScoped(): bool
    {
        return true;
    }

    public static function autoAssignCompany(): bool
    {
        return true;
    }

    public function getParent()
    {
        return $this->hasOne(self::class, ['id' => 'parent_id']);
    }

    public function getUom()
    {
        return $this->hasOne(Uom::class, ['id' => 'uom_id']);
    }

    public function getUomPO()
    {
        return $this->hasOne(Uom::class, ['id' => 'uom_po_id']);
    }

    public function getCategory()
    {
        return $this->hasOne(Category::class, ['id' => 'category_id']);
    }

    public function getTags()
    {
        return $this->hasMany(ProductTag::class, ['id' => 'tag_id'])
            ->viaTable('products_product_tag', ['product_id' => 'id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getProductAttributes()
    {
        return $this->hasMany(ProductAttribute::class, ['product_id' => 'id']);
    }

    public function getAttributeValues()
    {
        return $this->hasMany(ProductAttributeValue::class, ['product_id' => 'id']);
    }

    public function getVariants()
    {
        return $this->hasMany(self::class, ['parent_id' => 'id']);
    }

    public function getCombinations()
    {
        return $this->hasMany(ProductCombination::class, ['product_id' => 'id']);
    }

    public function isInUse(): bool
    {
        return UsageRegistry::isProductInUse($this->id);
    }

    public function generateVariants(): void
    {
        $attributes = $this->productAttributes;
        if ($attributes === []) {
            return;
        }
        if (!$this->is_configurable && UsageRegistry::isProductInUse($this->id)) {
            throw new ValidationException(
                [],
                "The product {$this->name} is in use and cannot be modified."
            );
        }

        $existingVariants = $this->variants;
        $comboMap = [];
        foreach ($existingVariants as $variant) {
            $ids = (new Query())->from('products_product_combinations')
                ->where(['product_id' => $variant->id])
                ->select('product_attribute_value_id')
                ->column();
            sort($ids, SORT_NUMERIC);
            $comboMap[implode(',', $ids)] = $variant;
        }

        $sets = [];
        foreach ($attributes as $productAttribute) {
            $sets[] = $productAttribute->values;
        }

        $processed = [];
        foreach ($this->cartesian($sets) as $combination) {
            $valueIds = array_map(fn ($v) => (int) $v->id, $combination);
            sort($valueIds, SORT_NUMERIC);
            $key = implode(',', $valueIds);

            $optionNames = array_map(fn ($v) => $v->attributeOption->name, $combination);
            $name = $this->name . ' - ' . implode(' / ', $optionNames);
            $price = (float) $this->price + array_sum(array_map(fn ($v) => (float) $v->extra_price, $combination));
            $cost = $this->cost;

            $variant = $comboMap[$key] ?? null;
            if ($variant !== null) {
                $variant->name = $name;
                $variant->price = $price;
                $variant->cost = $cost;
                $variant->save(false);
            } else {
                $variant = new self();
                $variant->setAttributes([
                    'type' => $this->type,
                    'name' => $name,
                    'price' => $price,
                    'cost' => $this->cost,
                    'enable_sales' => $this->enable_sales,
                    'enable_purchase' => $this->enable_purchase,
                    'parent_id' => $this->id,
                    'company_id' => $this->company_id,
                    'uom_id' => $this->uom_id,
                    'uom_po_id' => $this->uom_po_id,
                    'category_id' => $this->category_id,
                    'volume' => $this->volume,
                    'weight' => $this->weight,
                    'description' => $this->description,
                    'description_purchase' => $this->description_purchase,
                    'description_sale' => $this->description_sale,
                    'barcode' => null,
                    'reference' => $this->reference . '-' . strtolower(str_replace(' ', '-', $name)),
                    'images' => $this->images,
                ], false);
                $variant->save(false);
            }

            Yii::$app->db->createCommand()
                ->delete('products_product_combinations', ['product_id' => $variant->id])
                ->execute();
            foreach ($combination as $value) {
                Yii::$app->db->createCommand()->insert('products_product_combinations', [
                    'product_id' => $variant->id,
                    'product_attribute_value_id' => $value->id,
                ])->execute();
            }
            $processed[] = (int) $variant->id;
        }

        foreach ($existingVariants as $variant) {
            if (!in_array((int) $variant->id, $processed, true)) {
                Yii::$app->db->createCommand()
                    ->delete('products_product_combinations', ['product_id' => $variant->id])
                    ->execute();
                $variant->deleteOrArchive();
            }
        }

        $this->is_configurable = true;
        $this->save(false);
    }

    private function cartesian(array $sets): array
    {
        $result = [[]];
        foreach ($sets as $set) {
            $append = [];
            foreach ($result as $prefix) {
                foreach ($set as $item) {
                    $append[] = array_merge($prefix, [$item]);
                }
            }
            $result = $append;
        }
        return $result;
    }

    public function deleteOrArchive(): void
    {
        try {
            $this->forceDelete();
        } catch (\yii\db\Exception $e) {
            $this->delete();
        }
    }

    public function delete()
    {
        foreach ($this->variants as $variant) {
            $variant->delete();
        }
        return parent::delete();
    }

    public function forceDelete()
    {
        foreach ($this->variants as $variant) {
            $variant->forceDelete();
        }
        return parent::forceDelete();
    }

    public static function apiFields(): array
    {
        return [
            'id', 'type', 'name', 'service_tracking', 'reference', 'barcode', 'price', 'cost',
            'volume', 'weight', 'description', 'description_purchase', 'description_sale',
            'enable_sales', 'enable_purchase', 'is_favorite', 'is_configurable', 'images', 'sort',
            'parent_id', 'uom_id', 'uom_po_id', 'category_id', 'creator_id', 'company_id',
            'created_at', 'updated_at', 'deleted_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'price' => 'float',
            'cost' => 'float',
            'volume' => 'float',
            'weight' => 'float',
            'enable_sales' => 'bool',
            'enable_purchase' => 'bool',
            'is_favorite' => 'bool',
            'is_configurable' => 'bool',
            'images' => 'json',
            'sort' => 'int',
            'created_at' => 'iso',
            'updated_at' => 'iso',
            'deleted_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'parent' => 'parent',
            'variants' => 'variants',
            'uom' => 'uom',
            'uom_po' => 'uomPO',
            'category' => 'category',
            'attributes' => 'productAttributes',
            'attribute_values' => 'attributeValues',
            'tags' => 'tags',
            'company' => 'company',
            'creator' => 'creator',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'type' => ['required', 'in:goods,service'],
            'name' => ['required', 'string', 'max:255'],
            'service_tracking' => ['string', 'max:255'],
            'reference' => ['string', 'max:255'],
            'barcode' => ['string', 'max:255', 'barcode:product'],
            'price' => ['required', 'number', 'min_num:0'],
            'cost' => ['number', 'min_num:0'],
            'volume' => ['number', 'min_num:0', 'max_num:99999999999'],
            'weight' => ['number', 'min_num:0', 'max_num:99999999999'],
            'description' => ['string'],
            'description_purchase' => ['string'],
            'description_sale' => ['string'],
            'enable_sales' => ['boolean'],
            'enable_purchase' => ['boolean'],
            'is_favorite' => ['boolean'],
            'images' => ['array_of_string'],
            'uom_id' => ['required', 'integer', 'exists:unit_of_measures'],
            'uom_po_id' => ['required', 'integer', 'exists:unit_of_measures'],
            'category_id' => ['required', 'integer', 'exists:products_categories'],
            'company_id' => ['integer', 'company_allowed'],
        ];
    }
}
