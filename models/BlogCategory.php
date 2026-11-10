<?php

namespace app\models;

use Yii;
use app\components\BaseModel;

class BlogCategory extends BaseModel
{
    public static function tableName(): string
    {
        return 'blogs_categories';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public static function translatableAttributes(): array
    {
        return ['name', 'sub_title', 'meta_title', 'meta_keywords', 'meta_description'];
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        if ($insert && !$this->creator_id) {
            $this->creator_id = Yii::$app->access?->currentUserId();
        }
        if (!$this->slug) {
            $this->slug = WebsitePage::slugify((string) (self::translatedValue($this->name) ?? ''));
        }

        return true;
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getPosts()
    {
        return $this->hasMany(BlogPost::class, ['category_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'sub_title', 'slug', 'image', 'meta_title', 'meta_keywords', 'meta_description', 'creator_id', 'created_at', 'updated_at', 'deleted_at'];
    }

    public static function apiComputed(): array
    {
        return [
            'image_url' => fn ($model) => $model->image ? '/storage/' . ltrim((string) $model->image, '/') : null,
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'name' => 'translated',
            'sub_title' => 'translated',
            'meta_title' => 'translated',
            'meta_keywords' => 'translated',
            'meta_description' => 'translated',
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
            'name' => ['required'],
            'slug' => ['required', 'string', 'max:255', 'unique:blogs_categories,slug'],
            'sub_title' => [],
            'meta_title' => [],
            'meta_keywords' => [],
            'meta_description' => [],
            'image' => ['string', 'max:255'],
        ];
    }
}
