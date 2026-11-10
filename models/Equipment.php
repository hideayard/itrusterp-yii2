<?php

namespace app\models;

use Yii;
use app\components\BaseModel;

class Equipment extends BaseModel
{
    public static function tableName(): string
    {
        return 'maintenance_equipments';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $this->maintenance_count ??= 0;
        $this->maintenance_open_count ??= 0;
        if ($insert && !$this->creator_id) {
            $this->creator_id = Yii::$app->access?->currentUserId();
        }

        return true;
    }

    public function getCategory()
    {
        return $this->hasOne(EquipmentCategory::class, ['id' => 'category_id']);
    }

    public function getPartner()
    {
        return $this->hasOne(Partner::class, ['id' => 'partner_id']);
    }

    public function getOwner()
    {
        return $this->hasOne(User::class, ['id' => 'owner_user_id']);
    }

    public function getTeam()
    {
        return $this->hasOne(MaintenanceTeam::class, ['id' => 'maintenance_team_id']);
    }

    public function getTechnician()
    {
        return $this->hasOne(User::class, ['id' => 'technician_user_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getRequests()
    {
        return $this->hasMany(MaintenanceRequest::class, ['equipment_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'partner_ref', 'location', 'model', 'serial_no', 'effective_date', 'warranty_date',
            'assigned_at', 'scraped_at', 'name', 'note', 'cost', 'maintenance_count',
            'maintenance_open_count', 'expected_mtbf', 'category_id', 'partner_id', 'owner_user_id',
            'maintenance_team_id', 'technician_user_id', 'company_id', 'creator_id',
            'created_at', 'updated_at', 'deleted_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'cost' => 'float',
            'maintenance_count' => 'int',
            'maintenance_open_count' => 'int',
            'expected_mtbf' => 'int',
            'effective_date' => 'iso',
            'warranty_date' => 'iso',
            'assigned_at' => 'iso',
            'scraped_at' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
            'deleted_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'category' => 'category',
            'partner' => 'partner',
            'owner' => 'owner',
            'team' => 'team',
            'technician' => 'technician',
            'company' => 'company',
            'creator' => 'creator',
            'requests' => 'requests',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'effective_date' => ['required', 'date'],
            'warranty_date' => ['date'],
            'assigned_at' => ['date'],
            'scraped_at' => ['date'],
            'partner_ref' => ['string', 'max:255'],
            'location' => ['string', 'max:255'],
            'model' => ['string', 'max:255'],
            'serial_no' => ['string', 'max:255'],
            'note' => ['string'],
            'cost' => ['number'],
            'expected_mtbf' => ['integer'],
            'category_id' => ['integer', 'exists:maintenance_equipment_categories'],
            'partner_id' => ['integer', 'exists:partners_partners'],
            'owner_user_id' => ['integer', 'exists:users'],
            'maintenance_team_id' => ['integer', 'exists:maintenance_teams'],
            'technician_user_id' => ['integer', 'exists:users'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
        ];
    }
}
