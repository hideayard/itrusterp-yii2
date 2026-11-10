<?php

namespace app\components;

use Yii;

class Resources
{
    private static ?array $registry = null;

    public static function all(): array
    {
        if (self::$registry === null) {
            self::$registry = require Yii::getAlias('@app/config/resources.php');
        }
        return self::$registry;
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }
}
