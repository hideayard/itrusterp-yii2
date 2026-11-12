# Integrasi ERP ke dalam project iTrust (modul `erp`)

Dokumen ini menjelaskan cara ERP (project ini) dijalankan **di dalam** aplikasi iTrust
(`OLD/itrust`, Yii2 basic, PHP ≥ 8.4) tanpa mengganggu fungsi iTrust.

## Ringkasan hasil

| Aspek | Nilai |
|---|---|
| Lokasi modul | `OLD/itrust/modules/erp/` (168 berkas: 125 model, 13 controller, 28 komponen) |
| Namespace | seluruh kode ERP: `erp\*` (bukan `app\*`, jadi tidak bentrok dengan `app\models\User/Users/Settings/...`) |
| Prefix URL | `/erp/api/v1/...` |
| Database | terpisah: `itrust_erp` (default), env `AUREUS_DB*` |
| Autentikasi | bearer token ERP (`erp\models\User` + tabel `personal_access_tokens` di DB ERP), **tanpa session iTrust** |
| iTrust | hanya 3 titik tambahan di `config/web.php`; kode/view/route lain tidak diubah |

## Tabel yang bentrok antar database

`aureuserp` (284 tabel) vs `itrust_040826` (35 tabel): **hanya 2 nama yang sama**, dan
strukturnya berbeda total sehingga tidak bisa dipakai bersama:

| Tabel | ERP | iTrust |
|---|---|---|
| `users` | `id, name, username, email, password, …` | `user_id, user_name, user_pass, user_tipe, user_status, …` |
| `settings` | `id, group, name, company_id, payload` | `settings_id, settings_type, settings_name, settings_value` |

Karena itu modul memakai **database sendiri**. Bila suatu saat dipaksa satu database:
`RENAME TABLE users TO erp_users, settings TO erp_settings;` lalu ubah `erp\models\User::tableName()`
menjadi `erp_users`.

## 1. Menyalin/menyegarkan modul

Dari root project ini:

```powershell
php scripts/build-itrust-module.php
# atau tentukan tujuan lain:
php scripts/build-itrust-module.php "E:/laragon6/www/ERP/OLD/itrust/modules/erp"
```

Skrip ini menghapus `modules/erp` lama, menyalin kode, menulis ulang namespace, mengubah route,
dan menulis `Module.php` + `config/{db,params}.php`.

## 2. Pendaftaran di iTrust (sekali saja)

`OLD/itrust/config/web.php`:

```php
'aliases' => [
    '@bower' => '@vendor/bower-asset',
    '@npm'   => '@vendor/npm-asset',
    '@erp'   => '@app/modules/erp',              // (1) alias modul
],

'urlManager' => [
    'rules' => array_merge([ /* aturan iTrust apa adanya */ ],
        require __DIR__ . '/../modules/erp/config/routes.php'),   // (2) route ERP
],

'modules' => [
    'erp' => ['class' => 'erp\Module'],           // (3) daftarkan modul
    'gridview' => [ /* … */ ],
],
```

> Gunakan `require __DIR__ . '/../modules/erp/config/routes.php'` — jangan `require '@erp/...'`,
> karena alias Yii belum tersedia saat file config dievaluasi.

## 3. Database `itrust_erp`

Salin dari database ERP yang sudah ada (aman diulang; pastikan tidak ada koneksi aktif):

```powershell
# 1) buat database tujuan
mysql -u root -e "CREATE DATABASE IF NOT EXISTS itrust_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2) dump + impor (jalankan lewat cmd agar tidak ter-encode UTF-16 oleh PowerShell)
cmd /c "mysqldump -u root --single-transaction --routines --triggers --skip-lock-tables aureuserp > %TEMP%\aureuserp.sql"
cmd /c "mysql -u root itrust_erp < %TEMP%\aureuserp.sql"
```

Verifikasi: `SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='itrust_erp';`
(harus **284** tabel) dan `SELECT COUNT(*) FROM itrust_erp.products_products;` (**32199**).

Kredensial dapat dioverride lewat `AUREUS_DB_HOST`, `AUREUS_DB`, `AUREUS_DB_USER`,
`AUREUS_DB_PASSWORD` (lihat `modules/erp/config/db.php`).

## 4. URL & verifikasi lokal (Laragon)

`erp.local` di Laragon memakai document root `E:/laragon6/www/ERP`, jadi aplikasi iTrust diakses
lewat subfolder `web`:

```powershell
# health check (tanpa token)
curl.exe -s http://erp.local/OLD/itrust/web/erp/api/v1

# login
curl.exe -i -X POST http://erp.local/OLD/itrust/web/erp/api/v1/login ^
  -H "Content-Type: application/json" -d "{\"login\":\"admin\",\"password\":\"admin123\"}"
```

Hasil uji terakhir:

| Uji | Hasil |
|---|---|
| `GET /erp/api/v1` | 200 — `status: ok`, `database: connected` |
| `POST /erp/api/v1/login` | 200 + token |
| `GET /erp/api/v1/system/resources` | 200 — 114 resource |
| `GET /erp/api/v1/products/products` | 200 — 32.199 produk |
| `GET /erp/api/v1/point-of-sale/categories` | 200 — 2 kategori |
| `GET /erp/api/v1/employees/employees` | 200 — 7 karyawan |
| iTrust `/`, `/site/login`, `/gmr` | 200 (tidak terganggu) |

## 5. Deploy ke shared hosting

Langkah lengkap (cPanel, dua database, SetEnv, permission, verifikasi, troubleshooting):
**[`DEPLOY-ITRUST-HOSTING.md`](./DEPLOY-ITRUST-HOSTING.md)**.

## 6. Catatan penting

- **Jangan** menaruh kode ERP di `app\models`/`app\controllers` iTrust: nama seperti `User`,
  `Users`, `Settings`, `Service`, `Role` sudah dipakai. Modul memakai `erp\*`.
- Modul **mengganti komponen `db`, `user`, `access`, `errorHandler`, `response.format`, dan target
  `log`** hanya ketika request diarahkan ke modul (`/erp/api/v1/...`), di `erp\Module::init()`.
  Halaman iTrust tidak pernah melalui jalur itu.
- Body `application/json` didaftarkan modul (`request->parsers`) karena iTrust belum punya.
- Log ERP terpisah di `modules/erp/runtime/logs/app.log`.
- Frontend Astro cukup diarahkan ke `https://<domain>/erp/api/v1` (sesuaikan `AUREUS_API_URL`).
- Jika iTrust punya `web/.htaccess` yang meneruskan semua request ke `index.php`, route ERP otomatis
  ikut tertangani. Bila akses memakai subfolder (`/OLD/itrust/`), gunakan `/web/` pada URL API.
