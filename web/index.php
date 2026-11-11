<?php

declare(strict_types=1);

/*
 * Muat .env (bila ada) sebelum konfigurasi dibaca, sehingga deploy di hosting
 * cukup menaruh berkas .env — tidak perlu menyetel env di Apache/Nginx/PHP-FPM.
 * Variabel yang sudah ada di lingkungan web server tidak akan ditimpa.
 */
$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (strlen($value) > 1 && ($value[0] === '"' || $value[0] === "'") && str_ends_with($value, $value[0])) {
            $value = substr($value, 1, -1);
        }
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Produksi: set `YII_ENV=prod` di .env (YII_DEBUG otomatis mati).
defined('YII_ENV') or define('YII_ENV', getenv('YII_ENV') ?: 'dev');
defined('YII_DEBUG') or define(
    'YII_DEBUG',
    filter_var(getenv('YII_DEBUG') ?: (YII_ENV !== 'prod'), FILTER_VALIDATE_BOOL),
);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/../config/web.php';

(new yii\web\Application($config))->run();
