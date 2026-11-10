<?php

return [
    'class' => \yii\db\Connection::class,
    'dsn' => 'mysql:host=' . (getenv('AUREUS_DB_HOST') ?: 'localhost') . ';dbname=' . (getenv('AUREUS_DB') ?: 'aureuserp'),
    'username' => getenv('AUREUS_DB_USER') ?: 'root',
    'password' => getenv('AUREUS_DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
    'attributes' => [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
    ],
];
