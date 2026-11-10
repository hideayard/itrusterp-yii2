<?php

namespace app\models;

use app\components\BaseModel;

class BomLine extends BaseModel
{
    public static function tableName(): string
    {
        return 'manufacturing_bill_of_material_lines';
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $this->quantity ??= 1;
        $this->is_manual_consumption ??= 0;
        $this->sort ??= 0;

        return true;
    }

    public function getBillOfMaterial()
    {
        return $this->hasOne(BillOfMaterial::class, ['id' => 'bill_of_material_id']);
    }

    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    public function getUom()
    {
        return $this->hasOne(UOM::class, ['id' => 'uom_id']);
    }

    public function getOperation()
    {
        return $this->hasOne(ManufacturingOperation::class, ['id' => 'operation_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'sort', 'quantity', 'is_manual_consumption', 'bill_of_material_id', 'product_id', 'company_id', 'uom_id', 'operation_id', 'creator_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return [
            'sort' => 'int',
            'quantity' => 'float',
            'is_manual_consumption' => 'bool',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'bill_of_material' => 'billOfMaterial',
            'product' => 'product',
            'uom' => 'uom',
            'operation' => 'operation',
            'creator' => 'creator',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'quantity' => ['number', 'min_num:0'],
            'is_manual_consumption' => ['boolean'],
            'product_id' => ['required', 'integer', 'exists:products_products'],
            'uom_id' => ['integer', 'exists:unit_of_measures'],
            'operation_id' => ['integer', 'exists:manufacturing_operations'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
            'sort' => ['integer'],
        ];
    }
}
