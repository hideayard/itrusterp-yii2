<?php

namespace app\models;

use yii\db\ActiveRecord;

class PosOrderLine extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'pos_order_lines';
    }

    public function getOrder()
    {
        return $this->hasOne(PosOrder::class, ['id' => 'order_id']);
    }

    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    public function getUom()
    {
        return $this->hasOne(UOM::class, ['id' => 'uom_id']);
    }

    public function getTaxes()
    {
        return $this->hasMany(Tax::class, ['id' => 'tax_id'])
            ->viaTable('pos_order_line_taxes', ['order_line_id' => 'id']);
    }
}
