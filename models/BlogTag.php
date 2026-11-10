<?php

namespace app\models;

use Yii;
use app\components\BaseModel;

class BlogTag extends BaseModel
{
    public static function tableName(): string
    {
        return 'blogs_tags';
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
        $this->sort ??= 0;
        if ($insert && !$this->creator_id) {
            $this->creator_id = Yii::$app->access?->currentUserId();
        }

        return true;
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getPosts()
    {
        return $this->hasMany(BlogPost::class, ['id' => 'post_id'])
            ->viaTable('blogs_post_tags', ['tag_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'color', 'sort', 'creator_id', 'created_at', 'updated_at', 'deleted_at'];
    }

    public static function apiCasts(): array
    {
        return [
            'sort' => 'int',
            'created_at' => 'iso',
            'updated_at' => 'iso',
            'deleted_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return ['creator' => 'creator', 'posts' => 'posts'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['string', 'max:32'],
            'sort' => ['integer'],
        ];
    }
}
