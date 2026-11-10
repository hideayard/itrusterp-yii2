<?php

namespace app\models;

use app\components\BaseModel;

class RecruitmentDegree extends BaseModel
{
    public static function tableName(): string
    {
        return 'recruitments_degrees';
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $this->sort ??= 0;

        return true;
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'creator_id', 'sort', 'name', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['sort' => 'int', 'created_at' => 'iso', 'updated_at' => 'iso'];
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
        ];
    }
}
