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
        try {
            Yii::$app->db->createCommand('SELECT 1')->queryScalar();
        } catch (\Throwable $e) {
            $database = 'unreachable';
        }

        return [
            'data' => [
                'name' => 'iTrustERP API',
                'status' => $database === 'connected' ? 'ok' : 'degraded',
                'database' => $database,
                'version' => '1.0.0',
                'time' => gmdate('c'),
                'endpoints' => [
                    'login' => '/admin/api/v1/login',
                    'resources' => '/admin/api/v1/system/resources',
                    'example' => '/admin/api/v1/products/products',
                ],
            ],
        ];
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
