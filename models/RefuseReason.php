<?php

namespace app\models;

use app\components\BaseModel;

class RefuseReason extends BaseModel
{
    public static function tableName(): string
    {
        return 'recruitments_refuse_reasons';
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $this->sort ??= 0;
        $this->is_active ??= 1;

        return true;
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'creator_id', 'sort', 'name', 'template', 'is_active', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['sort' => 'int', 'is_active' => 'bool', 'created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['creator' => 'creator'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sort' => ['integer'],
            'template' => ['string'],
            'is_active' => ['boolean'],
        ];
    }
}
