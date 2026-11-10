<?php

namespace app\models;

use yii\db\ActiveRecord;

class PosConfig extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'pos_configs';
    }

    public function getCurrency()
    {
        return $this->hasOne(Currency::class, ['id' => 'currency_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getWarehouse()
    {
        return $this->hasOne(Warehouse::class, ['id' => 'warehouse_id']);
    }

    public function getSessions()
    {
        return $this->hasMany(PosSession::class, ['config_id' => 'id']);
    }

    public function getCategories()
    {
        return $this->hasMany(PosCategory::class, ['id' => 'category_id'])
            ->viaTable('pos_config_categories', ['config_id' => 'id']);
    }
}
