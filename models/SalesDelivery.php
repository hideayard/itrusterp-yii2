<?php

namespace app\models;

use app\components\BaseModel;

class SalesDelivery extends BaseModel
{
    public static function tableName(): string
    {
        return 'inventories_operations';
    }

    public function getPartner()
    {
        return $this->hasOne(Partner::class, ['id' => 'partner_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'name', 'origin', 'move_type', 'state', 'scheduled_at', 'closed_at',
            'user_id', 'partner_id', 'company_id', 'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'scheduled_at' => 'iso',
            'closed_at' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return ['partner' => 'partner', 'user' => 'user'];
    }

    public static function apiRules(): array
    {
        return ['name' => ['string']];
    }
}
