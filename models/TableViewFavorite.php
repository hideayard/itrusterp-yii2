<?php

namespace app\models;

use Yii;
use app\components\BaseModel;

class TableViewFavorite extends BaseModel
{
    public static function tableName(): string
    {
        return 'table_view_favorites';
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $this->is_favorite ??= 1;
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
        return ['id', 'is_favorite', 'view_type', 'view_key', 'filterable_type', 'user_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return [
            'is_favorite' => 'bool',
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
            'view_type' => ['required', 'string', 'max:64'],
            'view_key' => ['required', 'string', 'max:64'],
            'filterable_type' => ['required', 'string', 'max:255'],
            'user_id' => ['integer', 'exists:users'],
            'is_favorite' => ['boolean'],
        ];
    }
}
