<?php

namespace app\models;

use app\components\BaseModel;

class BillOfMaterial extends BaseModel
{
    public static function tableName(): string
    {
        return 'manufacturing_bills_of_materials';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public static function isCompanyScoped(): bool
    {
        return true;
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $this->type ??= 'normal';
        $this->ready_to_produce ??= 'all_available';
        $this->consumption ??= 'flexible';
        $this->quantity ??= 1;
        $this->allow_operation_dependencies ??= 0;
        $this->produce_delay ??= 0;
        $this->days_to_prepare_mo ??= 0;

        return true;
    }

    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    public function getUom()
    {
        return $this->hasOne(UOM::class, ['id' => 'uom_id']);
    }

    public function getOperationType()
    {
        return $this->hasOne(InventoryOperationType::class, ['id' => 'operation_type_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getLines()
    {
        return $this->hasMany(BomLine::class, ['bill_of_material_id' => 'id']);
    }

    public function getOperations()
    {
        return $this->hasMany(ManufacturingOperation::class, ['bill_of_material_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'code', 'type', 'ready_to_produce', 'consumption', 'quantity',
            'allow_operation_dependencies', 'produce_delay', 'days_to_prepare_mo', 'product_id',
            'uom_id', 'operation_type_id', 'company_id', 'creator_id', 'created_at', 'updated_at', 'deleted_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'quantity' => 'float',
            'allow_operation_dependencies' => 'bool',
            'produce_delay' => 'int',
            'days_to_prepare_mo' => 'int',
            'created_at' => 'iso',
            'updated_at' => 'iso',
            'deleted_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'product' => 'product',
            'uom' => 'uom',
            'operation_type' => 'operationType',
            'company' => 'company',
            'creator' => 'creator',
            'lines' => 'lines',
            'operations' => 'operations',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'code' => ['string', 'max:255'],
            'type' => ['required', 'in:normal,phantom'],
            'ready_to_produce' => ['required', 'in:all_available,asap'],
            'consumption' => ['required', 'in:flexible,warning,strict'],
            'quantity' => ['required', 'number', 'min_num:0'],
            'allow_operation_dependencies' => ['boolean'],
            'produce_delay' => ['integer'],
            'days_to_prepare_mo' => ['integer'],
            'product_id' => ['integer', 'exists:products_products'],
            'uom_id' => ['required', 'integer', 'exists:unit_of_measures'],
            'operation_type_id' => ['integer', 'exists:inventories_operation_types'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
        ];
    }
}
