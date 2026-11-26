<?php
/**
 * Seed negara Indonesia + 38 provinsi + mata uang IDR (idempoten).
 *
 * Latar belakang: resource `support/countries` (config/resources.php) hanya
 * mengizinkan `index`/`view`, jadi negara dibuat lewat skrip ini — bukan lewat UI.
 *
 * Jalankan dari folder aureuserp-yii2:
 *     php scripts/seed-indonesia.php
 *
 * Aman dijalankan berulang: baris dicocokkan lewat `code` (provinsi) dan
 * `code = 'ID'` (negara), sehingga tidak menimbulkan duplikat. Provinsi yang
 * sudah ada tidak diubah.
 */
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
new yii\console\Application(require dirname(__DIR__) . '/config/console.php');

use yii\db\Query;

$db = Yii::$app->db;
$now = date('Y-m-d H:i:s');

/* -------------------------------------------------------------------------- */
/* 1. Mata uang IDR                                                           */
/* -------------------------------------------------------------------------- */
$idr = (new Query())->from('currencies')->where(['name' => 'IDR'])->one();

if (!$idr) {
    $db->createCommand()->insert('currencies', [
        'name' => 'IDR',
        'symbol' => 'Rp',
        'iso_numeric' => 360,
        'decimal_places' => 0,
        'full_name' => 'Indonesian rupiah',
        'rounding' => 0.01,
        'active' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ])->execute();
    $idr = (new Query())->from('currencies')->where(['name' => 'IDR'])->one();
    echo "currency dibuat: IDR (#{$idr['id']})\n";
} else {
    echo "currency ada: IDR (#{$idr['id']})\n";
}

/* -------------------------------------------------------------------------- */
/* 2. Negara Indonesia                                                        */
/* -------------------------------------------------------------------------- */
$country = (new Query())->from('countries')->where(['code' => 'ID'])->one();

if (!$country) {
    $db->createCommand()->insert('countries', [
        'currency_id' => (int) $idr['id'],
        'phone_code' => '62',
        'code' => 'ID',
        'name' => 'Indonesia',
        'state_required' => 1,
        'zip_required' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ])->execute();
    $country = (new Query())->from('countries')->where(['code' => 'ID'])->one();
    echo "country dibuat: Indonesia (#{$country['id']})\n";
} else {
    echo "country ada: Indonesia (#{$country['id']})\n";
}

$countryId = (int) $country['id'];

/* -------------------------------------------------------------------------- */
/* 3. 38 provinsi                                                             */
/* -------------------------------------------------------------------------- */
$provinces = [
    ['AC', 'Aceh'],
    ['BA', 'Bali'],
    ['BB', 'Bangka Belitung'],
    ['BT', 'Banten'],
    ['BE', 'Bengkulu'],
    ['GO', 'Gorontalo'],
    ['JK', 'Jakarta'],
    ['JA', 'Jambi'],
    ['JB', 'Jawa Barat'],
    ['JT', 'Jawa Tengah'],
    ['JI', 'Jawa Timur'],
    ['KB', 'Kalimantan Barat'],
    ['KS', 'Kalimantan Selatan'],
    ['KT', 'Kalimantan Tengah'],
    ['KI', 'Kalimantan Timur'],
    ['KU', 'Kalimantan Utara'],
    ['KR', 'Kepulauan Riau'],
    ['LA', 'Lampung'],
    ['MA', 'Maluku'],
    ['MU', 'Maluku Utara'],
    ['NB', 'Nusa Tenggara Barat'],
    ['NT', 'Nusa Tenggara Timur'],
    ['PA', 'Papua'],
    ['PB', 'Papua Barat'],
    ['PD', 'Papua Barat Daya'],
    ['PP', 'Papua Pegunungan'],
    ['PS', 'Papua Selatan'],
    ['PT', 'Papua Tengah'],
    ['RI', 'Riau'],
    ['SR', 'Sulawesi Barat'],
    ['SN', 'Sulawesi Selatan'],
    ['ST', 'Sulawesi Tengah'],
    ['SG', 'Sulawesi Tenggara'],
    ['SA', 'Sulawesi Utara'],
    ['SB', 'Sumatra Barat'],
    ['SS', 'Sumatra Selatan'],
    ['SU', 'Sumatra Utara'],
    ['YO', 'Yogyakarta'],
];

$created = 0;
$existing = 0;

foreach ($provinces as [$code, $name]) {
    $row = (new Query())
        ->from('states')
        ->where(['country_id' => $countryId, 'code' => $code])
        ->one();

    if ($row) {
        $existing += 1;
        continue;
    }

    $db->createCommand()->insert('states', [
        'country_id' => $countryId,
        'name' => $name,
        'code' => $code,
        'created_at' => $now,
        'updated_at' => $now,
    ])->execute();
    $created += 1;
    echo "  provinsi dibuat: $name ($code)\n";
}

$total = (new Query())->from('states')->where(['country_id' => $countryId])->count();

echo "\nRingkasan:\n";
echo "  Indonesia (#{$countryId}) — provinsi: $total (baru: $created, sudah ada: $existing)\n";
