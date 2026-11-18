<?php

namespace app\controllers\v1;

use Yii;
use app\components\ApiController;

/**
 * Metadata untuk UI: daftar resource yang terdaftar di registry
 * (dipakai halaman "Kelola Data" agar CRUD tersedia untuk semua modul).
 */
class MetaController extends ApiController
{
    /**
     * Health check / info API di root `/admin/api/v1` (publik, tanpa token).
     */
    protected function publicActions(): array
    {
        return ['index'];
    }

    public function actionIndex()
    {
        $database = 'connected';
        $databaseError = null;

        try {
            Yii::$app->db->createCommand('SELECT 1')->queryScalar();
        } catch (\Throwable $e) {
            $database = 'unreachable';
            $databaseError = $e->getMessage();

            // Jejak diagnostik ke runtime/logs/app.log: pesan asli + konfigurasi
            // yang dipakai (tanpa password) supaya penyebabnya bisa langsung dilihat.
            Yii::error(
                'Health check DB gagal: ' . $databaseError
                . ' | host=' . (getenv('AUREUS_DB_HOST') ?: '-')
                . ' port=' . (getenv('AUREUS_DB_PORT') ?: '-')
                . ' db=' . (getenv('AUREUS_DB') ?: '-')
                . ' user=' . (getenv('AUREUS_DB_USER') ?: '-'),
                __METHOD__
            );
        }

        $apiRoot = (string) (Yii::$app->params['erpApiPrefix'] ?? '/admin/api/v1');

        $data = [
            'name' => 'iTrustERP API',
            'status' => $database === 'connected' ? 'ok' : 'degraded',
            'database' => $database,
            'version' => '1.0.0',
            'time' => gmdate('c'),
            'endpoints' => [
                'login' => $apiRoot . '/login',
                'resources' => $apiRoot . '/system/resources',
                'example' => $apiRoot . '/products/products',
            ],
        ];

        // Detail error hanya saat debug (mis. YII_DEBUG=true sementara) — endpoint ini publik.
        if ($databaseError !== null && YII_DEBUG) {
            $data['database_error'] = $databaseError;
            $data['database_config'] = [
                'host' => getenv('AUREUS_DB_HOST') ?: '(default: localhost)',
                'port' => getenv('AUREUS_DB_PORT') ?: '(default: 3306)',
                'db' => getenv('AUREUS_DB') ?: '(default: aureuserp)',
                'user' => getenv('AUREUS_DB_USER') ?: '(default: root)',
            ];
        }

        return ['data' => $data];
    }

    public function actionResources()
    {
        $registry = require Yii::getAlias('@app/config/resources.php');
        $rows = [];

        foreach ($registry as $key => $cfg) {
            $permission = $cfg['permission'] ?? null;

            if ($permission !== null && !Yii::$app->access->can('view_any_' . $permission)) {
                continue;
            }

            $actions = $cfg['actions'] ?? null;
            $writable = $actions === null || in_array('create', $actions, true);
            $editable = $actions === null || in_array('update', $actions, true);
            $deletable = $actions === null || in_array('delete', $actions, true);

            $rows[] = [
                'key' => (string) $key,
                'label' => (string) ($cfg['label'] ?? $key),
                'pattern' => (string) ($cfg['pattern'] ?? ''),
                'writable' => $writable,
                'editable' => $editable,
                'deletable' => $deletable,
                'soft' => !empty($cfg['soft']),
            ];
        }

        usort($rows, static fn ($a, $b) => strcmp($a['pattern'], $b['pattern']));

        // Endpoint custom (bukan registry) yang tetap punya CRUD.
        if (Yii::$app->access->can('view_any_point_of_sale_category')) {
            $rows[] = [
                'key' => 'pos-category',
                'label' => 'POS Category',
                'pattern' => 'point-of-sale/categories',
                'writable' => Yii::$app->access->can('create_point_of_sale_category'),
                'editable' => Yii::$app->access->can('update_point_of_sale_category'),
                'deletable' => Yii::$app->access->can('delete_point_of_sale_category'),
                'soft' => true,
            ];
            usort($rows, static fn ($a, $b) => strcmp($a['pattern'], $b['pattern']));
        }

        return ['data' => $rows];
    }
}
