<?php

$resources = require __DIR__ . '/resources.php';

$rules = [
    ['class' => 'yii\web\UrlRule', 'pattern' => 'admin/api/v1', 'route' => 'v1/meta/index', 'verb' => 'GET'],
    ['class' => 'yii\web\UrlRule', 'pattern' => 'admin/api/v1<slash:/>', 'route' => 'v1/meta/index', 'verb' => 'GET'],
    ['class' => 'yii\web\UrlRule', 'pattern' => 'admin/api/v1/system/resources', 'route' => 'v1/meta/resources', 'verb' => 'GET'],
    ['class' => 'yii\web\UrlRule', 'pattern' => 'admin/api/v1/login', 'route' => 'v1/auth/login', 'verb' => 'POST'],
    ['class' => 'yii\web\UrlRule', 'pattern' => 'admin/api/v1/logout', 'route' => 'v1/auth/logout', 'verb' => 'POST'],
    ['class' => 'yii\web\UrlRule', 'pattern' => 'admin/api/v1/<path:.*>', 'route' => 'v1/error/options', 'verb' => 'OPTIONS'],
];

foreach ($resources as $key => $cfg) {
    $base = 'admin/api/v1/' . $cfg['pattern'];
    $controller = $cfg['controller'] ?? 'v1/resource';
    $defaults = ['resource' => $key];

    $add = function (array $methods, string $pattern, string $action) use ($controller, $defaults, &$rules) {
        $rules[] = [
            'class' => 'yii\web\UrlRule',
            'pattern' => $pattern,
            'route' => $controller . '/' . $action,
            'defaults' => $defaults,
            'verb' => $methods,
        ];
    };

    $add(['GET'], $base, 'index');
    $add(['POST'], $base, 'create');
    $add(['GET'], $base . '/<id:\d+>', 'view');
    $add(['PUT', 'PATCH'], $base . '/<id:\d+>', 'update');
    $add(['DELETE'], $base . '/<id:\d+>', 'delete');

    if (!empty($cfg['soft'])) {
        $add(['POST'], $base . '/<id:\d+>/restore', 'restore');
        $add(['DELETE'], $base . '/<id:\d+>/force', 'force-delete');
    }

    foreach ($cfg['extraRoutes'] ?? [] as $extra) {
        $rules[] = [
            'class' => 'yii\web\UrlRule',
            'pattern' => $base . '/' . $extra['pattern'],
            'route' => $controller . '/' . $extra['action'],
            'defaults' => $defaults,
            'verb' => $extra['verb'],
        ];
    }
}

$posBase = 'admin/api/v1/point-of-sale';
$pos = [
    [['GET'], $posBase . '/registers', 'registers'],
    [['GET'], $posBase . '/categories', 'categories'],
    [['POST'], $posBase . '/categories', 'create-category'],
    [['GET'], $posBase . '/categories/<id:\d+>', 'category'],
    [['PUT', 'PATCH'], $posBase . '/categories/<id:\d+>', 'update-category'],
    [['DELETE'], $posBase . '/categories/<id:\d+>', 'delete-category'],
    [['POST'], $posBase . '/registers/<config:\d+>/discard', 'discard'],
    [['GET'], $posBase . '/configs/<config:\d+>/catalog', 'catalog'],
    [['GET'], $posBase . '/configs/<config:\d+>/session', 'current-session'],
    [['POST'], $posBase . '/configs/<config:\d+>/session', 'open-session'],
    [['POST'], $posBase . '/sessions/<session:\d+>/opening-control', 'opening-control'],
    [['POST'], $posBase . '/sessions/<session:\d+>/closing-control', 'closing-control'],
    [['POST'], $posBase . '/sessions/<session:\d+>/close', 'close-session'],
    [['POST'], $posBase . '/sessions/<session:\d+>/cash-movements', 'cash-movement'],
    [['GET'], $posBase . '/sessions/<session:\d+>/orders', 'orders'],
    [['GET'], $posBase . '/sessions/<session:\d+>/sales-details', 'sales-details'],
    [['GET'], $posBase . '/sessions/<session:\d+>/closing-control-report', 'closing-report'],
    [['GET'], $posBase . '/sessions/<session:\d+>/boot', 'boot'],
    [['POST'], $posBase . '/sessions/<session:\d+>/cancel-draft-orders', 'cancel-draft-orders'],
    [['POST'], $posBase . '/orders/sync', 'sync-orders'],
    [['GET'], $posBase . '/orders', 'order-history'],
    [['GET'], $posBase . '/orders/<order:[^/]+>/refundable-lines', 'refundable-lines'],
    [['POST'], $posBase . '/orders/<order:[^/]+>/refund', 'refund'],
    [['POST'], $posBase . '/orders/<order:[^/]+>/invoice', 'invoice'],
    [['GET'], $posBase . '/orders/<order:[^/]+>/invoice-pdf', 'invoice-pdf'],
    [['GET'], $posBase . '/orders/<order:[^/]+>/receipt', 'receipt'],
];

foreach ($pos as [$methods, $pattern, $action]) {
    $rules[] = [
        'class' => 'yii\web\UrlRule',
        'pattern' => $pattern,
        'route' => 'v1/point-of-sale/' . $action,
        'verb' => $methods,
    ];
}

return $rules;
