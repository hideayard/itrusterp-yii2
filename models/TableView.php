<?php

namespace app\models;

use Yii;
use app\components\BaseModel;

class TableView extends BaseModel
{
    public static function tableName(): string
    {
        return 'table_views';
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $this->is_public ??= 0;
        if ($insert && !$this->user_id) {
            $this->user_id = Yii::$app->access?->currentUserId();
        }

        return true;
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'icon', 'color', 'is_public', 'filters', 'filterable_type', 'user_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return [
            'is_public' => 'bool',
            'filters' => 'json',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return ['user' => 'user'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'filterable_type' => ['required', 'string', 'max:255'],
            'user_id' => ['integer', 'exists:users'],
            'icon' => ['string', 'max:64'],
            'color' => ['string', 'max:32'],
            'is_public' => ['boolean'],
        ];
    }

    public static function writableAttributes(): array
    {
        return array_merge(parent::writableAttributes(), ['filters']);
    }
}
