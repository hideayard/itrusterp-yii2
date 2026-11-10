<?php

namespace app\models;

use app\components\BaseModel;

class ProductivityLoss extends BaseModel
{
    public static function tableName(): string
    {
        return 'manufacturing_work_center_productivity_losses';
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $this->sort ??= 0;
        $this->manual ??= 0;

        return true;
    }

    public function getLossType()
    {
        return $this->hasOne(LossType::class, ['id' => 'loss_type_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'sort', 'loss_type', 'name', 'manual', 'loss_type_id', 'creator_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['sort' => 'int', 'manual' => 'bool', 'created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['loss_type_ref' => 'lossType', 'creator' => 'creator'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'loss_type' => ['string', 'max:255'],
            'manual' => ['boolean'],
            'loss_type_id' => ['integer', 'exists:manufacturing_work_center_loss_types'],
            'sort' => ['integer'],
        ];
    }
}
