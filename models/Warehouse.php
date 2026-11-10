<?php

namespace app\models;

use app\components\BaseModel;

class Warehouse extends BaseModel
{
    public static function tableName(): string
    {
        return 'inventories_warehouses';
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

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        if ($insert) {
            $this->reception_steps = $this->reception_steps ?: 'one_step';
            $this->delivery_steps = $this->delivery_steps ?: 'ship_only';

            if (!$this->view_location_id) {
                $view = new Location();
                $view->setAttributes([
                    'name' => $this->code ?: $this->name,
                    'full_name' => $this->code ?: $this->name,
                    'type' => Location::TYPE_VIEW,
                    'company_id' => $this->company_id,
                    'is_scrap' => 0,
                    'is_replenish' => 0,
                    'is_dock' => 0,
                ], false);
                $view->save(false);

                $stock = new Location();
                $stock->setAttributes([
                    'name' => 'Stock',
                    'full_name' => ($this->code ?: $this->name) . ' / Stock',
                    'type' => Location::TYPE_INTERNAL,
                    'parent_id' => $view->id,
                    'company_id' => $this->company_id,
                    'is_scrap' => 0,
                    'is_replenish' => 0,
                    'is_dock' => 0,
                ], false);
                $stock->save(false);

                $this->view_location_id = $view->id;
                $this->lot_stock_location_id = $stock->id;
            }
        }
        return true;
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        if ($insert && $this->view_location_id && $this->lot_stock_location_id) {
            $ids = [$this->view_location_id, $this->lot_stock_location_id];
            Location::updateAll(['warehouse_id' => $this->id], ['in', 'id', $ids]);
        }
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getPartnerAddress()
    {
        return $this->hasOne(Partner::class, ['id' => 'partner_address_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getLocations()
    {
        return $this->hasMany(Location::class, ['warehouse_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'name', 'code', 'sort', 'reception_steps', 'delivery_steps',
            'company_id', 'partner_address_id', 'creator_id', 'created_at', 'updated_at', 'deleted_at',
        ];
    }

    public static function apiCasts(): array
    {
        return ['sort' => 'int', 'created_at' => 'iso', 'updated_at' => 'iso', 'deleted_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return [
            'company' => 'company',
            'partner_address' => 'partnerAddress',
            'creator' => 'creator',
            'locations' => 'locations',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:inventories_warehouses,name'],
            'code' => ['required', 'string', 'max:255', 'unique:inventories_warehouses,code'],
            'company_id' => ['required', 'integer', 'exists:companies', 'company_allowed'],
            'partner_address_id' => ['integer', 'exists:partners_partners'],
            'reception_steps' => ['string'],
            'delivery_steps' => ['string'],
            'sort' => ['integer'],
        ];
    }
}
