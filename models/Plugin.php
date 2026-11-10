<?php

namespace app\models;

use app\components\BaseModel;

class Plugin extends BaseModel
{
    public static function tableName(): string
    {
        return 'plugins';
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $this->sort ??= 0;
        $this->is_active ??= 0;
        $this->is_installed ??= 0;

        return true;
    }

    public function getDependencies()
    {
        return $this->hasMany(self::class, ['id' => 'dependency_id'])
            ->viaTable('plugin_dependencies', ['plugin_id' => 'id']);
    }

    public function getDependents()
    {
        return $this->hasMany(self::class, ['id' => 'plugin_id'])
            ->viaTable('plugin_dependencies', ['dependency_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'name', 'author', 'summary', 'description', 'latest_version', 'license',
            'is_active', 'is_installed', 'sort', 'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'is_active' => 'bool',
            'is_installed' => 'bool',
            'sort' => 'int',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return ['dependencies' => 'dependencies', 'dependents' => 'dependents'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'author' => ['string', 'max:255'],
            'summary' => ['string'],
            'description' => ['string'],
            'latest_version' => ['string', 'max:64'],
            'license' => ['string', 'max:64'],
            'is_active' => ['boolean'],
            'is_installed' => ['boolean'],
            'sort' => ['integer'],
        ];
    }
}
