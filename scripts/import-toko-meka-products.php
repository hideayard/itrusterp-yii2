<?php
/**
 * Import produk dari BACKUP/toko-meka/example/products.csv.
 *
 * - Menambah kolom `toko_*` pada products_products agar sesuai kolom CSV
 *   (barcode 2/3, supplier, harga toko/partai/cabang, ukuran, warna, stok min/max, dll).
 * - Kategori CSV (KATEGORI / SUB_KATEGORI) dibuat di products_categories di bawah root
 *   "Toko Meka" dan dipakai sebagai category_id produk.
 * - Idempoten: produk dicocokkan lewat `reference` (= KODE_BARANG), di-update bila sudah ada.
 * - Barcode duplikat / kosong disimpan NULL agar tidak melanggar unique (company_id, barcode).
 *
 * Jalankan: php scripts/import-toko-meka-products.php [--limit=N]
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

new yii\console\Application(require __DIR__ . '/../config/console.php');

$csvPath = 'E:/laragon6/www/ERP/BACKUP/toko-meka/example/products.csv';
$limit = 0;
foreach ($argv as $arg) {
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
        $limit = (int) $m[1];
    }
}

if (!is_file($csvPath)) {
    fwrite(STDERR, "CSV tidak ditemukan: $csvPath\n");
    exit(1);
}

$db = Yii::$app->db;
$now = date('Y-m-d H:i:s');
$companyId = 1;
$creatorId = 1;
$uomId = 1;     // Units
$uomPoId = 1;

/* ------------------------------------------------------------------ */
/* 1. Kolom tambahan (mengikuti kolom CSV)                            */
/* ------------------------------------------------------------------ */
$columns = [
    'toko_barcode_2' => 'VARCHAR(64) NULL',
    'toko_barcode_3' => 'VARCHAR(64) NULL',
    'toko_supplier' => 'VARCHAR(255) NULL',
    'toko_purchase_date' => 'DATE NULL',
    'toko_content_qty' => 'DECIMAL(15,4) NULL',
    'toko_content_unit' => 'VARCHAR(32) NULL',
    'toko_uom_2' => 'VARCHAR(32) NULL',
    'toko_uom_3' => 'VARCHAR(32) NULL',
    'toko_price_2' => 'DECIMAL(15,4) NULL',
    'toko_price_3' => 'DECIMAL(15,4) NULL',
    'toko_wholesale_1' => 'DECIMAL(15,4) NULL',
    'toko_wholesale_2' => 'DECIMAL(15,4) NULL',
    'toko_wholesale_3' => 'DECIMAL(15,4) NULL',
    'toko_branch_1' => 'DECIMAL(15,4) NULL',
    'toko_branch_2' => 'DECIMAL(15,4) NULL',
    'toko_branch_3' => 'DECIMAL(15,4) NULL',
    'toko_location' => 'VARCHAR(64) NULL',
    'toko_size' => 'VARCHAR(64) NULL',
    'toko_color' => 'VARCHAR(64) NULL',
    'toko_alt_name_2' => 'VARCHAR(255) NULL',
    'toko_alt_name_3' => 'VARCHAR(255) NULL',
    'toko_stock_min' => 'INT NULL',
    'toko_stock_max' => 'INT NULL',
    'toko_stock_store' => 'DECIMAL(15,4) NULL',
    'toko_stock_warehouse' => 'DECIMAL(15,4) NULL',
];

$existingColumns = (new yii\db\Query())->select('column_name')
    ->from('information_schema.columns')
    ->where(['table_schema' => $db->createCommand('SELECT DATABASE()')->queryScalar(), 'table_name' => 'products_products'])
    ->column();

$added = 0;
foreach ($columns as $name => $definition) {
    if (in_array($name, $existingColumns, true)) {
        continue;
    }
    $db->createCommand("ALTER TABLE products_products ADD COLUMN `$name` $definition")->execute();
    $added++;
}
echo "kolom ditambahkan: $added\n";

/* ------------------------------------------------------------------ */
/* 2. Kategori                                                         */
/* ------------------------------------------------------------------ */
$rootName = 'Toko Meka';
$rootId = (new yii\db\Query())->from('products_categories')->where(['name' => $rootName])->select('id')->scalar();
if (!$rootId) {
    $db->createCommand()->insert('products_categories', [
        'name' => $rootName, 'full_name' => $rootName, 'parent_path' => $rootName,
        'parent_id' => null, 'creator_id' => $creatorId, 'created_at' => $now, 'updated_at' => $now,
    ])->execute();
    $rootId = (int) $db->getLastInsertID();
    echo "kategori root dibuat: $rootName (#{$rootId})\n";
}
$rootId = (int) $rootId;

$categoryCache = [];
$categoryCreated = 0;

$ensureCategory = function (string $parentName, string $name, ?int $parentId) use ($db, $now, $creatorId, $rootId, &$categoryCache, &$categoryCreated): int {
    $key = $parentName . '||' . $name;
    if (isset($categoryCache[$key])) {
        return $categoryCache[$key];
    }
    $row = (new yii\db\Query())->from('products_categories')
        ->where(['name' => $name, 'parent_id' => $parentId])->select('id')->scalar();
    if (!$row) {
        $full = $parentName !== '' ? $parentName . ' / ' . $name : $name;
        $db->createCommand()->insert('products_categories', [
            'name' => $name, 'full_name' => $full, 'parent_path' => $full,
            'parent_id' => $parentId, 'creator_id' => $creatorId, 'created_at' => $now, 'updated_at' => $now,
        ])->execute();
        $row = (int) $db->getLastInsertID();
        $categoryCreated++;
    }
    return $categoryCache[$key] = (int) $row;
};

/* ------------------------------------------------------------------ */
/* 3. Import produk                                                    */
/* ------------------------------------------------------------------ */
$handle = fopen($csvPath, 'r');
if ($handle === false) {
    fwrite(STDERR, "Gagal membuka CSV\n");
    exit(1);
}

$header = fgetcsv($handle, 0, ",", "\"", "");
if ($header === false) {
    fwrite(STDERR, "CSV kosong\n");
    exit(1);
}
$header = array_map(fn ($h) => trim((string) $h), $header);

// Deteksi encoding (Windows-1252 → UTF-8) bila perlu
$sample = file_get_contents($csvPath, false, null, 0, 200000);
$isUtf8 = mb_check_encoding($sample, 'UTF-8');
fclose($handle);
$handle = fopen($csvPath, 'r');
fgetcsv($handle, 0, ",", "\"", ""); // skip header

$col = array_flip($header);
$value = function (array $row, string $name) use ($col): string {
    $index = $col[$name] ?? null;
    return $index === null ? '' : trim((string) ($row[$index] ?? ''));
};
$decimal = function (string $raw): ?float {
    $raw = str_replace(['.', ','], ['', '.'], $raw); // 19.218,16 → 19218.16
    if ($raw === '' || !is_numeric($raw)) {
        return null;
    }
    return (float) $raw;
};
$dateOrNull = function (string $raw): ?string {
    if ($raw === '' || str_starts_with($raw, '1899') || str_starts_with($raw, '1900')) {
        return null;
    }
    $time = strtotime($raw);
    return $time ? date('Y-m-d', $time) : null;
};

// Peta reference yang sudah ada (idempoten)
$existing = [];
foreach ((new yii\db\Query())->from('products_products')->select(['id', 'reference'])->where(['not', ['reference' => null]])->all() as $row) {
    $existing[(string) $row['reference']] = (int) $row['id'];
}
$usedBarcodes = [];
foreach ((new yii\db\Query())->from('products_products')->select('barcode')->where(['not', ['barcode' => null]])->column() as $code) {
    $usedBarcodes[(string) $code] = true;
}

$inserted = 0;
$updated = 0;
$skipped = 0;
$dupBarcode = 0;
$processed = 0;
$batch = 0;

$db->createCommand('SET autocommit=0')->execute();

while (($row = fgetcsv($handle, 0, ",", "\"", "")) !== false) {
    if ($row === [null] || $row === []) {
        continue;
    }
    $processed++;
    if ($limit > 0 && $processed > $limit) {
        break;
    }

    // Normalisasi encoding per baris
    if (!$isUtf8) {
        $row = array_map(fn ($v) => mb_convert_encoding((string) $v, 'UTF-8', 'Windows-1252'), $row);
    }

    $code = $value($row, 'KODE_BARANG');
    $name = $value($row, 'NAMA');
    if ($name === '') {
        $name = $value($row, 'NAMA_2');
    }
    if ($name === '') {
        $skipped++;
        continue;
    }

    $barcode = $value($row, 'KODE_BARCODE');
    if ($barcode === '' || isset($usedBarcodes[$barcode])) {
        if ($barcode !== '') {
            $dupBarcode++;
        }
        $barcode = null;
    } else {
        $usedBarcodes[$barcode] = true;
    }

    // Kategori
    $catName = $value($row, 'KATEGORI');
    $subName = $value($row, 'SUB_KATEGORI');
    $categoryId = null;
    if ($catName !== '') {
        $catId = $ensureCategory($rootName, $catName, $rootId);
        $categoryId = $subName !== ''
            ? $ensureCategory($catName, $subName, $catId)
            : $catId;
    }
    if (!$categoryId) {
        $categoryId = (int) (new yii\db\Query())->from('products_categories')->where(['id' => 6])->select('id')->scalar(); // Saleable
    }

    $price1 = $decimal($value($row, 'HARGA_TOKO_1'));
    $price2 = $decimal($value($row, 'HARGA_TOKO_2'));
    $price3 = $decimal($value($row, 'HARGA_TOKO_3'));
    $price = null;
    foreach ([$price1, $price2, $price3, $decimal($value($row, 'HARGA_PARTAI_1')), $decimal($value($row, 'HARGA_CABANG_1'))] as $candidate) {
        if ($candidate !== null && $candidate > 0) {
            $price = $candidate;
            break;
        }
    }
    $price ??= 0.0;

    $attributes = [
        'type' => 'goods',
        'name' => mb_substr($name, 0, 255),
        'reference' => $code !== '' ? mb_substr($code, 0, 255) : null,
        'barcode' => $barcode !== null ? mb_substr($barcode, 0, 64) : null,
        'price' => $price,
        'cost' => $decimal($value($row, 'HPP')) ?? 0.0,
        'uom_id' => $uomId,
        'uom_po_id' => $uomPoId,
        'category_id' => $categoryId,
        'company_id' => $companyId,
        'creator_id' => $creatorId,
        'is_storable' => 1,
        'available_in_pos' => 1,
        'enable_sales' => 1,
        'enable_purchase' => 1,
        'sales_ok' => 1,
        'purchase_ok' => 1,
        'sort' => $processed,
        'toko_barcode_2' => $value($row, 'KODE_BARCODE_2') ?: null,
        'toko_barcode_3' => $value($row, 'KODE_BARCODE_3') ?: null,
        'toko_supplier' => $value($row, 'SUPPLIER') ?: null,
        'toko_purchase_date' => $dateOrNull($value($row, 'TANGGAL_BELI')),
        'toko_content_qty' => $decimal($value($row, 'ISI')),
        'toko_content_unit' => $value($row, 'ISI_SATUAN_3') ?: null,
        'toko_uom_2' => $value($row, 'SATUAN_2') ?: null,
        'toko_uom_3' => $value($row, 'SATUAN_3') ?: null,
        'toko_price_2' => $price2,
        'toko_price_3' => $price3,
        'toko_wholesale_1' => $decimal($value($row, 'HARGA_PARTAI_1')),
        'toko_wholesale_2' => $decimal($value($row, 'HARGA_PARTAI_2')),
        'toko_wholesale_3' => $decimal($value($row, 'HARGA_PARTAI_3')),
        'toko_branch_1' => $decimal($value($row, 'HARGA_CABANG_1')),
        'toko_branch_2' => $decimal($value($row, 'HARGA_CABANG_2')),
        'toko_branch_3' => $decimal($value($row, 'HARGA_CABANG_3')),
        'toko_location' => $value($row, 'LOKASI') ?: null,
        'toko_size' => $value($row, 'UKURAN') ?: null,
        'toko_color' => $value($row, 'WARNA') ?: null,
        'toko_alt_name_2' => $value($row, 'NAMA_2') ?: null,
        'toko_alt_name_3' => $value($row, 'NAMA_3') ?: null,
        'toko_stock_min' => (int) ($decimal($value($row, 'STOK_MIN')) ?? 0),
        'toko_stock_max' => (int) ($decimal($value($row, 'STOK_MAX')) ?? 0),
        'toko_stock_store' => $decimal($value($row, 'TOKO')),
        'toko_stock_warehouse' => $decimal($value($row, 'GUDANG')),
        'updated_at' => $now,
    ];

    if ($code !== '' && isset($existing[$code])) {
        $db->createCommand()->update('products_products', $attributes, ['id' => $existing[$code]])->execute();
        $updated++;
    } else {
        $attributes['created_at'] = $now;
        $db->createCommand()->insert('products_products', $attributes)->execute();
        if ($code !== '') {
            $existing[$code] = (int) $db->getLastInsertID();
        }
        $inserted++;
    }

    if (++$batch >= 500) {
        $db->createCommand('COMMIT')->execute();
        $batch = 0;
        if ($processed % 5000 === 0) {
            echo "  ... $processed baris (insert=$inserted update=$updated)\n";
        }
    }
}

$db->createCommand('COMMIT')->execute();
$db->createCommand('SET autocommit=1')->execute();
fclose($handle);

echo "\nSelesai:\n";
echo "  baris CSV diproses : $processed\n";
echo "  produk baru        : $inserted\n";
echo "  produk di-update   : $updated\n";
echo "  dilewati (tanpa nama): $skipped\n";
echo "  barcode duplikat (NULL): $dupBarcode\n";
echo "  kategori dibuat    : $categoryCreated\n";
echo '  total produk di DB : ' . (new yii\db\Query())->from('products_products')->where(['deleted_at' => null])->count() . "\n";
