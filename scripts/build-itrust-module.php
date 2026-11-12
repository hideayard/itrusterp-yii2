<?php
/**
 * Menyalin kode ERP (project ini) menjadi modul `erp` di dalam aplikasi iTrust.
 *
 * Pemakaian (dari root project aureuserp-yii2):
 *   php scripts/build-itrust-module.php
 *   php scripts/build-itrust-module.php "E:/laragon6/www/ERP/OLD/itrust/modules/erp"
 *
 * Yang dilakukan:
 *   1. menghapus folder tujuan lalu menyalin models/ controllers/ components/ config/(resources,routes) scripts/
 *   2. menulis ulang namespace `app\` -> `erp\`
 *   3. mengubah route: prefix `erp/api/v1...` dan route modul `erp/v1/...`
 *   4. menulis Module.php + config/db.php (DB terpisah) + config/params.php
 *
 * Yang TIDAK dilakukan: menyentuh aplikasi iTrust. Pendaftaran modul di
 * `config/web.php` iTrust hanya perlu sekali (lihat INTEGRASI-ITRUST.md).
 */

$src  = dirname(__DIR__);
$dest = $argv[1] ?? (getenv('ITRUST_MODULE_PATH') ?: dirname(dirname($src)) . '/OLD/itrust/modules/erp');

if (!is_dir($dest) && !is_dir(dirname($dest))) {
    fwrite(STDERR, "Folder induk tujuan tidak ada: " . dirname($dest) . "\n");
    exit(1);
}

function erase(string $dir): void
{
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        is_dir($path) ? erase($path) : @unlink($path);
    }
    @rmdir($dir);
}

function copyDir(string $from, string $to): int
{
    @mkdir($to, 0775, true);
    $count = 0;
    foreach (scandir($from) as $item) {
        if ($item === '.' || $item === '..') continue;
        $s = $from . '/' . $item;
        $d = $to . '/' . $item;
        $count += is_dir($s) ? copyDir($s, $d) : (int) copy($s, $d);
    }
    return $count;
}

echo "sumber : $src\n";
echo "tujuan : $dest\n";

erase($dest);
mkdir($dest, 0775, true);
@mkdir($dest . '/runtime/logs', 0775, true);
@mkdir($dest . '/runtime/cache', 0775, true);

$total = 0;
foreach (['models', 'controllers', 'components', 'scripts'] as $dir) {
    if (is_dir($src . '/' . $dir)) $total += copyDir($src . '/' . $dir, $dest . '/' . $dir);
}
@mkdir($dest . '/config', 0775, true);
foreach (['resources.php', 'routes.php'] as $file) {
    copy($src . '/config/' . $file, $dest . '/config/' . $file);
    $total++;
}
echo "file disalin: $total\n";

$changed = 0;
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dest, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if (strtolower($file->getExtension()) !== 'php') continue;
    $path = $file->getPathname();
    $code = file_get_contents($path);
    $before = $code;

    $code = preg_replace('/^namespace app\\\\/m', 'namespace erp\\', $code);
    $code = preg_replace('/^use app\\\\/m', 'use erp\\', $code);
    $code = preg_replace('/(?<![\w$])app\\\\(?=[A-Z])/', 'erp\\\\', $code);
    $code = str_replace("'@app/config/", "'@erp/config/", $code);
    $code = str_replace('"@app/config/', '"@erp/config/', $code);

    if ($code !== $before) {
        file_put_contents($path, $code);
        $changed++;
    }
}
echo "berkas diubah namespace: $changed\n";

$routesPath = $dest . '/config/routes.php';
$r = file_get_contents($routesPath);
$r = str_replace("'admin/api/v1/", "'erp/api/v1/", $r);
$r = str_replace("'admin/api/v1'", "'erp/api/v1'", $r);
$r = str_replace("'admin/api/v1<slash:/>'", "'erp/api/v1<slash:/>'", $r);
$r = str_replace("\$base = 'admin/api/v1/'", "\$base = 'erp/api/v1/'", $r);
$r = str_replace("\$posBase = 'admin/api/v1/", "\$posBase = 'erp/api/v1/", $r);
$r = str_replace("\$controller = \$cfg['controller'] ?? 'v1/resource';", "\$controller = 'erp/' . (\$cfg['controller'] ?? 'v1/resource');", $r);
$r = str_replace("'route' => 'v1/", "'route' => 'erp/v1/", $r);
file_put_contents($routesPath, $r);
echo "routes: prefix erp/api/v1 + route erp/v1/...\n";

file_put_contents($dest . '/Module.php', <<<'PHP'
<?php

namespace erp;

use Yii;
use yii\log\FileTarget;
use yii\web\Response;
use yii\web\User;

/**
 * Modul ERP iTrustERP di dalam aplikasi iTrust.
 *
 * Semua kode ERP berada di namespace `erp\*` dan memakai koneksi database
 * sendiri (`AUREUS_DB`, default `itrust_erp`). Konfigurasi di sini hanya
 * berlaku untuk request yang masuk ke modul (URL /erp/api/v1/...), sehingga
 * halaman iTrust tidak terpengaruh.
 */
class Module extends \yii\base\Module
{
    public $controllerNamespace = 'erp\controllers';

    public function init()
    {
        parent::init();

        // 1) Database ERP (terpisah dari database iTrust)
        Yii::$app->set('db', require __DIR__ . '/config/db.php');

        // 2) Komponen hak akses ERP
        Yii::$app->set('access', ['class' => \erp\components\Access::class]);

        // 3) Identitas stateless (token bearer ERP), tanpa session iTrust
        Yii::$app->set('user', [
            'class' => User::class,
            'identityClass' => \erp\models\User::class,
            'enableSession' => false,
            'enableAutoLogin' => false,
            'loginUrl' => null,
        ]);

        // 4) Respons & error JSON khusus API ERP
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->set('errorHandler', ['class' => \erp\components\ApiErrorHandler::class]);

        // 4b) Terima body application/json (aplikasi iTrust belum mendaftarkannya)
        Yii::$app->request->parsers['application/json'] = \yii\web\JsonParser::class;

        // 5) Parameter ERP — tidak menimpa parameter iTrust yang sudah ada
        Yii::$app->params['erpApiPrefix'] = '/erp/api/v1';
        foreach ((array) require __DIR__ . '/config/params.php' as $key => $value) {
            if (!array_key_exists($key, Yii::$app->params)) {
                Yii::$app->params[$key] = $value;
            }
        }

        // 6) Log ERP terpisah dari log iTrust
        $log = Yii::$app->get('log', false);
        if ($log !== null) {
            $log->targets = [new FileTarget([
                'logFile' => '@erp/runtime/logs/app.log',
                'levels' => ['error', 'warning'],
                'logVars' => [],
            ])];
        }

        @mkdir(Yii::getAlias('@erp/runtime/logs'), 0775, true);
    }
}
PHP);

file_put_contents($dest . '/config/db.php', <<<'PHP'
<?php

/**
 * Koneksi database ERP (terpisah dari database aplikasi iTrust).
 *
 * Nilai dibaca dari environment (getenv / $_SERVER), sehingga di shared hosting
 * bisa disetel lewat `.htaccess` (SetEnv) atau file .env loader.
 * Default: itrust_erp @ localhost.
 */
$env = static function (string $key, ?string $default = null): ?string {
    $value = getenv($key);
    if ($value === false && isset($_SERVER[$key])) {
        $value = $_SERVER[$key];
    }
    return ($value === false || $value === '') ? $default : (string) $value;
};

return [
    'class' => \yii\db\Connection::class,
    'dsn' => 'mysql:host=' . $env('AUREUS_DB_HOST', 'localhost')
        . ';dbname=' . $env('AUREUS_DB', 'itrust_erp'),
    'username' => $env('AUREUS_DB_USER', 'root'),
    'password' => $env('AUREUS_DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'attributes' => [
        \PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
        \PDO::ATTR_TIMEOUT => 5,
    ],
];
PHP);

file_put_contents($dest . '/config/params.php', <<<'PHP'
<?php

/**
 * Parameter modul ERP. Hanya dipakai bila kunci belum ada di params aplikasi iTrust.
 */
$origins = array_filter(array_map('trim', explode(',', (string) (
    getenv('AUREUS_CORS_ORIGINS') ?: 'https://itrust-erp.pages.dev,http://localhost:4321'
))));

return [
    'corsOrigins' => array_values($origins),
    'tokenTtlDays' => max(1, (int) (getenv('AUREUS_TOKEN_TTL_DAYS') ?: 90)),
];
PHP);

file_put_contents($dest . '/.gitignore', "runtime/*\n!runtime/.gitkeep\n");
file_put_contents($dest . '/runtime/.gitkeep', '');

echo "Module.php + config dibuat\nselesai.\n";
