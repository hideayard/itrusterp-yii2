<?php

namespace app\models;

use Yii;
use app\components\BaseModel;

class MaintenanceRequest extends BaseModel
{
    public static function tableName(): string
    {
        return 'maintenance_requests';
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
        $this->recurring_maintenance ??= 0;
        if ($insert && !$this->creator_id) {
            $this->creator_id = Yii::$app->access?->currentUserId();
        }

        return true;
    }

    public function getEquipment()
    {
        return $this->hasOne(Equipment::class, ['id' => 'equipment_id']);
    }

    public function getStage()
    {
        return $this->hasOne(MaintenanceStage::class, ['id' => 'stage_id']);
    }

    public function getCategory()
    {
        return $this->hasOne(EquipmentCategory::class, ['id' => 'category_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getTeam()
    {
        return $this->hasOne(MaintenanceTeam::class, ['id' => 'maintenance_team_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'name', 'priority', 'maintenance_type', 'recurring_maintenance', 'repeat_interval',
            'repeat_unit', 'repeat_type', 'repeat_until', 'duration', 'requested_at', 'closed_at',
            'scheduled_at', 'description', 'instruction_type', 'instruction_pdf',
            'instruction_google_slide', 'instruction_text', 'equipment_id', 'stage_id', 'category_id',
            'user_id', 'maintenance_team_id', 'company_id', 'creator_id', 'created_at', 'updated_at', 'deleted_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'recurring_maintenance' => 'bool',
            'repeat_interval' => 'int',
            'duration' => 'float',
            'repeat_until' => 'iso',
            'requested_at' => 'iso',
            'closed_at' => 'iso',
            'scheduled_at' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
            'deleted_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'equipment' => 'equipment',
            'stage' => 'stage',
            'category' => 'category',
            'user' => 'user',
            'team' => 'team',
            'company' => 'company',
            'creator' => 'creator',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'maintenance_team_id' => ['required', 'integer', 'exists:maintenance_teams'],
            'company_id' => ['required', 'integer', 'exists:companies', 'company_allowed'],
            'equipment_id' => ['integer', 'exists:maintenance_equipments'],
            'stage_id' => ['integer', 'exists:maintenance_stages'],
            'category_id' => ['integer', 'exists:maintenance_equipment_categories'],
            'user_id' => ['integer', 'exists:users'],
            'priority' => ['string', 'max:16'],
            'maintenance_type' => ['string', 'max:32'],
            'recurring_maintenance' => ['boolean'],
            'repeat_interval' => ['integer'],
            'repeat_unit' => ['string', 'max:16'],
            'repeat_type' => ['string', 'max:16'],
            'repeat_until' => ['date'],
            'duration' => ['number'],
            'requested_at' => ['date'],
            'closed_at' => ['date'],
            'scheduled_at' => ['date'],
            'description' => ['string'],
            'instruction_type' => ['string', 'max:32'],
            'instruction_pdf' => ['string', 'max:255'],
            'instruction_google_slide' => ['string', 'max:255'],
            'instruction_text' => ['string'],
        ];
    }
}
