<?php

namespace app\models;

use Yii;
use app\components\BaseModel;
use app\components\hooks\TranslatableHook;

class WebsitePage extends BaseModel
{
    public static function tableName(): string
    {
        return 'website_pages';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public static function translatableAttributes(): array
    {
        return ['title', 'content', 'meta_title', 'meta_keywords', 'meta_description'];
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $this->is_published ??= 0;
        $this->is_header_visible ??= 0;
        $this->is_footer_visible ??= 0;
        if ($insert && !$this->creator_id) {
            $this->creator_id = Yii::$app->access?->currentUserId();
        }
        if (!$this->slug) {
            $this->slug = self::slugify((string) (self::translatedValue($this->title) ?? ''));
        }

        return true;
    }

    public static function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'title', 'content', 'slug', 'is_published', 'published_at', 'is_header_visible',
            'is_footer_visible', 'meta_title', 'meta_keywords', 'meta_description', 'creator_id',
            'created_at', 'updated_at', 'deleted_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'title' => 'translated',
            'content' => 'translated',
            'meta_title' => 'translated',
            'meta_keywords' => 'translated',
            'meta_description' => 'translated',
            'is_published' => 'bool',
            'is_header_visible' => 'bool',
            'is_footer_visible' => 'bool',
            'published_at' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
            'deleted_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return ['creator' => 'creator'];
    }

    public static function apiRules(): array
    {
        return [
            'title' => ['required'],
            'content' => ['required'],
            'slug' => ['required', 'string', 'max:255', 'unique:website_pages,slug'],
            'is_published' => ['boolean'],
            'is_header_visible' => ['boolean'],
            'is_footer_visible' => ['boolean'],
            'published_at' => ['date'],
            'meta_title' => [],
            'meta_keywords' => [],
            'meta_description' => [],
        ];
    }
}
