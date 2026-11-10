<?php

namespace app\models;

use app\components\BaseModel;

class InventoryOperation extends BaseModel
{
    public static function tableName(): string
    {
        return 'inventories_operations';
    }

    public function getOperationType()
    {
        return $this->hasOne(InventoryOperationType::class, ['id' => 'operation_type_id']);
    }

    public function getSourceLocation()
    {
        return $this->hasOne(Location::class, ['id' => 'source_location_id']);
    }

    public function getDestinationLocation()
    {
        return $this->hasOne(Location::class, ['id' => 'destination_location_id']);
    }

    public function getPartner()
    {
        return $this->hasOne(Partner::class, ['id' => 'partner_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getMoves()
    {
        return $this->hasMany(InventoryMove::class, ['operation_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'name', 'origin', 'move_type', 'state', 'is_favorite', 'description',
            'has_deadline_issue', 'is_printed', 'is_locked', 'deadline', 'scheduled_at', 'closed_at',
            'user_id', 'owner_id', 'operation_type_id', 'source_location_id', 'destination_location_id',
            'partner_id', 'company_id', 'creator_id', 'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'is_favorite' => 'bool',
            'has_deadline_issue' => 'bool',
            'is_printed' => 'bool',
            'is_locked' => 'bool',
            'deadline' => 'iso',
            'scheduled_at' => 'iso',
            'closed_at' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'operation_type' => 'operationType',
            'source_location' => 'sourceLocation',
            'destination_location' => 'destinationLocation',
            'partner' => 'partner',
            'company' => 'company',
            'creator' => 'creator',
            'user' => 'user',
            'moves' => 'moves',
        ];
    }

    public static function apiRules(): array
    {
        return ['name' => ['string']];
    }
}
