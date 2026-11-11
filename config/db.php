<?php

return [
    'class' => \yii\db\Connection::class,
    'dsn' => 'mysql:host=' . (getenv('AUREUS_DB_HOST') ?: 'localhost') . ';dbname=' . (getenv('AUREUS_DB') ?: 'aureuserp'),
    'username' => getenv('AUREUS_DB_USER') ?: 'root',
    'password' => getenv('AUREUS_DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
    'attributes' => [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
        // Batasi waktu koneksi: bila konfigurasi host DB salah, request gagal cepat
        // dengan pesan jelas alih-alih menggantung sampai PHP menyerah (500/timeout).
        PDO::MYSQL_ATTR_CONNECT_TIMEOUT => 5,
        PDO::ATTR_TIMEOUT => 5,
    ],
];
