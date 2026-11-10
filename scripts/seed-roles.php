<?php
/**
 * Seed role & user untuk modul Security (idempoten).
 * Jalankan: php tmp-seed-roles.php
 */
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';
new yii\console\Application(require __DIR__ . '/config/console.php');

use yii\db\Query;
use yii\db\Expression;

$db = Yii::$app->db;

$roleSets = [
    'Admin' => null, // semua permission
    'Manager' => ['view_%', 'create_%', 'update_%'],
    'Cashier' => ['%point_of_sale%', 'view_%sale_order%', 'view_%partner%'],
    'Warehouse' => ['%inventor%', 'view_%purchase%', 'create_%inventor%', 'update_%inventor%'],
    'Accountant' => ['%account%', 'view_%sale_order%', 'view_%purchase%'],
    'Employee' => ['view_%employee%', 'view_%time_off%', 'create_%time_off%', 'view_%project_timesheet%', 'create_%project_timesheet%'],
];

foreach ($roleSets as $name => $patterns) {
    $role = (new Query())->from('roles')->where(['name' => $name])->one();
    if (!$role) {
        $db->createCommand()->insert('roles', [
            'name' => $name,
            'guard_name' => 'web',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ])->execute();
        $role = (new Query())->from('roles')->where(['name' => $name])->one();
        echo "role dibuat: $name\n";
    }

    $db->createCommand()->delete('role_has_permissions', ['role_id' => (int) $role['id']])->execute();

    if ($patterns === null) {
        $db->createCommand()->delete('role_has_permissions', ['role_id' => (int) $role['id']])->execute();
        $db->createCommand("INSERT INTO role_has_permissions (role_id, permission_id) SELECT :rid, id FROM permissions", [':rid' => (int) $role['id']])->execute();
    } else {
        foreach ($patterns as $pattern) {
            $db->createCommand(
                "INSERT IGNORE INTO role_has_permissions (role_id, permission_id) SELECT :rid, id FROM permissions WHERE name LIKE :p",
                [':rid' => (int) $role['id'], ':p' => $pattern]
            )->execute();
        }
    }

    $count = (new Query())->from('role_has_permissions')->where(['role_id' => (int) $role['id']])->count();
    echo "role $name: $count permission\n";
}

// User demo: kasir & gudang (password admin123)
foreach ([['Kasir Demo', 'cashier', 'cashier@example.com', 'Cashier'], ['Gudang Demo', 'gudang', 'gudang@example.com', 'Warehouse']] as [$name, $username, $email, $roleName]) {
    $user = (new Query())->from('users')->where(['email' => $email])->one();
    if (!$user) {
        $db->createCommand()->insert('users', [
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]),
            'is_active' => 1,
            'is_default' => 0,
            'has_email_authentication' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ])->execute();
        $user = (new Query())->from('users')->where(['email' => $email])->one();
        echo "user dibuat: $email\n";
    }

    $role = (new Query())->from('roles')->where(['name' => $roleName])->one();
    if ($role && $user) {
        $exists = (new Query())->from('model_has_roles')->where([
            'role_id' => (int) $role['id'],
            'model_type' => app\models\User::LARAVEL_TYPE,
            'model_id' => (int) $user['id'],
        ])->exists();
        if (!$exists) {
            $db->createCommand()->insert('model_has_roles', [
                'role_id' => (int) $role['id'],
                'model_type' => app\models\User::LARAVEL_TYPE,
                'model_id' => (int) $user['id'],
            ])->execute();
        }
        echo "user $email -> role $roleName\n";
    }
}

echo "\nRingkasan:\n";
foreach ((new Query())->from('roles')->orderBy(['id' => SORT_ASC])->all() as $role) {
    $count = (new Query())->from('role_has_permissions')->where(['role_id' => (int) $role['id']])->count();
    echo "  {$role['name']} (#{$role['id']}): $count permission\n";
}
