<?php

namespace app\models;

use Yii;
use app\components\BaseModel;

class BlogPost extends BaseModel
{
    public static function tableName(): string
    {
        return 'blogs_posts';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public static function translatableAttributes(): array
    {
        return ['title', 'sub_title', 'content', 'meta_title', 'meta_keywords', 'meta_description'];
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $this->is_published ??= 0;
        $this->visits ??= 0;
        $userId = Yii::$app->access?->currentUserId();
        if ($insert) {
            $this->author_id ??= $userId;
            $this->creator_id ??= $userId;
        }
        if (!$this->slug) {
            $this->slug = WebsitePage::slugify((string) (self::translatedValue($this->title) ?? ''));
        }

        return true;
    }

    public function getCategory()
    {
        return $this->hasOne(BlogCategory::class, ['id' => 'category_id']);
    }

    public function getAuthor()
    {
        return $this->hasOne(User::class, ['id' => 'author_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getLastEditor()
    {
        return $this->hasOne(User::class, ['id' => 'last_editor_id']);
    }

    public function getTags()
    {
        return $this->hasMany(BlogTag::class, ['id' => 'tag_id'])
            ->viaTable('blogs_post_tags', ['post_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'title', 'sub_title', 'content', 'slug', 'image', 'author_name', 'is_published',
            'published_at', 'visits', 'meta_title', 'meta_keywords', 'meta_description',
            'category_id', 'author_id', 'creator_id', 'last_editor_id', 'created_at', 'updated_at', 'deleted_at',
        ];
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
            'title' => 'translated',
            'sub_title' => 'translated',
            'content' => 'translated',
            'meta_title' => 'translated',
            'meta_keywords' => 'translated',
            'meta_description' => 'translated',
            'is_published' => 'bool',
            'visits' => 'int',
            'published_at' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
            'deleted_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'category' => 'category',
            'author' => 'author',
            'creator' => 'creator',
            'lastEditor' => 'lastEditor',
            'tags' => 'tags',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'title' => ['required'],
            'content' => ['required'],
            'slug' => ['required', 'string', 'max:255', 'unique:blogs_posts,slug'],
            'sub_title' => [],
            'meta_title' => [],
            'meta_keywords' => [],
            'meta_description' => [],
            'image' => ['string', 'max:255'],
            'author_name' => ['string', 'max:255'],
            'is_published' => ['boolean'],
            'published_at' => ['date'],
            'visits' => ['integer'],
            'category_id' => ['required', 'integer', 'exists:blogs_categories'],
            'author_id' => ['integer', 'exists:users'],
            'last_editor_id' => ['integer', 'exists:users'],
        ];
    }
}
