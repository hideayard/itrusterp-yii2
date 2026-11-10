<?php

namespace app\models;

use Yii;
use app\components\BaseModel;

class MaintenanceTeam extends BaseModel
{
    public static function tableName(): string
    {
        return 'maintenance_teams';
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
        if ($insert && !$this->creator_id) {
            $this->creator_id = Yii::$app->access?->currentUserId();
        }

        return true;
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getUsers()
    {
        return $this->hasMany(User::class, ['id' => 'user_id'])
            ->viaTable('maintenance_team_users', ['team_id' => 'id']);
    }

    public function getRequests()
    {
        return $this->hasMany(MaintenanceRequest::class, ['maintenance_team_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'creator_id', 'company_id', 'created_at', 'updated_at', 'deleted_at'];
    }

    public static function apiCasts(): array
    {
        return ['created_at' => 'iso', 'updated_at' => 'iso', 'deleted_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['creator' => 'creator', 'company' => 'company', 'users' => 'users', 'requests' => 'requests'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
        ];
    }
}
