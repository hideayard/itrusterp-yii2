<?php

namespace app\models;

use yii\db\ActiveRecord;

class PosCategory extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'pos_categories';
    }

    public function getParent()
    {
        return $this->hasOne(self::class, ['id' => 'parent_id']);
    }

    public function getChildren()
    {
        return $this->hasMany(self::class, ['parent_id' => 'id']);
    }

    public function getProducts()
    {
        return $this->hasMany(Product::class, ['id' => 'product_id'])
            ->viaTable('pos_category_products', ['category_id' => 'id']);
    }
}
