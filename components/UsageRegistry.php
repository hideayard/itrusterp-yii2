<?php

namespace app\components;

use Yii;
use yii\db\Query;

class UsageRegistry
{
    private const TABLES = [
        'accounts_account_move_lines',
        'inventories_moves',
        'inventories_move_lines',
        'inventories_product_quantities',
        'inventories_lots',
        'inventories_scraps',
        'manufacturing_manufacturing_orders',
        'manufacturing_work_orders',
        'manufacturing_unbuild_orders',
        'manufacturing_bills_of_materials',
        'manufacturing_bill_of_material_lines',
        'manufacturing_bill_of_material_byproducts',
        'manufacturing_work_center_capacities',
        'pos_pos_order_lines',
        'pos_pos_configs',
        'purchases_purchases_order_lines',
        'purchases_purchases_requisition_lines',
        'sales_sales_order_lines',
        'sales_sales_order_options',
        'sales_sales_order_template_products',
    ];

    private static ?array $columns = null;

    public static function isProductInUse(int|string $productId): bool
    {
        foreach (static::columns() as $table => $cols) {
            if ($cols === []) {
                continue;
            }
            $or = ['or'];
            foreach ($cols as $col) {
                $or[] = ['in', $col, (array) $productId];
            }
            if ((new Query())->from($table)->where($or)->exists()) {
                return true;
            }
        }
        return false;
    }

    private static function columns(): array
    {
        if (self::$columns !== null) {
            return self::$columns;
        }
        self::$columns = [];
        $schema = (string) Yii::$app->db->createCommand('SELECT DATABASE()')->queryScalar();
        $rows = (new Query())
            ->from('information_schema.columns')
            ->select(['table_name', 'column_name'])
            ->where(['table_schema' => $schema])
            ->andWhere(['in', 'table_name', self::TABLES])
            ->andWhere(['or', ['column_name' => 'product_id'], ['like', 'column_name', '_product_id', false]])
            ->all();
        foreach ($rows as $row) {
            self::$columns[$row['table_name']][] = $row['column_name'];
        }
        return self::$columns;
    }
}
