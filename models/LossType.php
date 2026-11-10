<?php

namespace app\models;

use app\components\BaseModel;

class LossType extends BaseModel
{
    public static function tableName(): string
    {
        return 'manufacturing_work_center_loss_types';
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getLosses()
    {
        return $this->hasMany(ProductivityLoss::class, ['loss_type_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'loss_type', 'creator_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['creator' => 'creator', 'losses' => 'losses'];
    }

    public static function apiRules(): array
    {
        return [
            'loss_type' => ['required', 'in:productive,performance,quality,availability'],
        ];
    }
}
