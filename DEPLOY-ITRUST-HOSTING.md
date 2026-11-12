# Deploy iTrust + Modul ERP ke Shared Hosting (cPanel)

Panduan menaikkan **aplikasi iTrust** beserta **modul ERP** (`/erp/api/v1`) ke shared hosting
berbasis cPanel, dengan dua database terpisah.

```
https://domainmu.com            → aplikasi iTrust (Yii2)   → DB <akun>_itrust
https://domainmu.com/erp/api/v1 → modul ERP (namespace erp) → DB <akun>_itrust_erp
```

---

## 1. Prasyarat

| Kebutuhan | Catatan |
|---|---|
| PHP | **8.4** (iTrust mensyaratkan `>=8.4`; cek `composer.json`) — jangan 8.2/8.3 |
| Ekstensi PHP | `pdo_mysql`, `mbstring`, `openssl`, `json`, `curl`, `fileinfo`, `xml`, `intl`, `zip` |
| Database | MySQL 8 / MariaDB 10.4+, **dua** database (iTrust & ERP) |
| Web server | Apache (cPanel standar) + mod_rewrite |
| Domain | Subdomain/domain dengan **Document Root diarahkan ke folder `web/`** |
| HTTPS | Aktifkan AutoSSL / Let's Encrypt di cPanel |

> **Wajib**: document root → `…/web`. Kalau diarahkan ke root proyek, `config/db.php`, `params.php`,
> dump SQL, dan folder `vendor/` bisa diunduh publik.

---

## 2. Siapkan paket di lokal

```powershell
cd E:\laragon6\www\ERP\OLD\itrust

# 1) dependensi produksi (vendor tidak perlu seluruh paket dev)
composer install --no-dev --optimize-autoloader

# 2) pastikan modul ERP terbaru sudah disalin ke project iTrust
cd E:\laragon6\www\ERP\aureuserp-yii2
php scripts/build-itrust-module.php "E:/laragon6/www/ERP/OLD/itrust/modules/erp"

# 3) dump kedua database (jalankan via cmd agar tidak ter-encode UTF-16 oleh PowerShell)
cmd /c "mysqldump -u root --single-transaction --routines --triggers --skip-lock-tables itrust_040826 > %TEMP%\itrust.sql"
cmd /c "mysqldump -u root --single-transaction --routines --triggers --skip-lock-tables aureuserp > %TEMP%\itrust_erp.sql"

# 4) arsipkan kode (kecualikan arsip vendor lama, .git, runtime)
Compress-Archive -Path .\* -DestinationPath ..\itrust-deploy.zip -Force
```

> Di folder iTrust ada banyak `vendor*.zip` (ratusan MB) — **jangan** ikut diunggah.

---

## 3. Unggah ke hosting

1. **cPanel → File Manager**: unggah `itrust-deploy.zip` ke luar `public_html`
   (mis. `/home/<akun>/itrust`), lalu **Extract**.
   Alternatif: **Git™ Version Control** (clone repo), lalu `composer install --no-dev` via SSH.
2. **PHP version**: cPanel → *MultiPHP Manager* → pilih **8.4** untuk domain/subdomain tersebut.
3. **Document Root**: cPanel → *Domains* → baris domain → **Manage** → **Document Root** =
   `/home/<akun>/itrust/web` → **Save**.

---

## 4. Database

1. cPanel → **MySQL® Databases** → buat:
   - database `<akun>_itrust` (data aplikasi iTrust)
   - database `<akun>_itrust_erp` (data ERP)
   - satu user, beri **ALL PRIVILEGES** ke kedua database.
2. Impor dump: **phpMyAdmin** → pilih DB → tab **Import** → unggah `itrust.sql`
   (ulangi untuk `itrust_erp.sql` ke database ERP).
   Jika melebihi batas unggah phpMyAdmin: unggah `.sql` ke folder di home, lalu pakai opsi
   *Import → “Import from web server”*, atau jalankan via SSH:
   ```bash
   mysql -u <akun>_user -p <akun>_itrust     < ~/itrust.sql
   mysql -u <akun>_user -p <akun>_itrust_erp < ~/itrust_erp.sql
   ```
3. Hapus kembali berkas `.sql` dari hosting setelah impor.

---

## 5. Konfigurasi

### 5.0 `.htaccess` di ROOT PROYEK (wajib bila document root = root proyek)

Kalau document root tidak bisa diarahkan ke `web/` (mis. `/home/<akun>/public_html/itrust`),
maka `.env`, `config/db.php`, `vendor/`, `runtime/logs/`, `*.sql`, dan `vendor*.zip` **bisa
diunduh publik**. Tutup dengan `.htaccess` di root proyek:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On

    # 1) URL kosong -> folder web/
    RewriteRule ^$ web/ [L]

    # 2) folder web/ diteruskan (aturannya ada di web/.htaccess)
    RewriteRule ^web(/|$) - [L]

    # 3) selain itu DITOLAK (403); /.well-known/ dikecualikan untuk SSL
    RewriteCond %{REQUEST_URI} !/\.well-known/
    RewriteRule ^ - [F,L]
</IfModule>
```

Pola `^web` bersifat relatif terhadap folder `.htaccess`, jadi berkas yang sama juga aman dipakai
di Laragon (subfolder `/OLD/itrust/`). Hasil uji: `/.env`, `/config/db.php`,
`/vendor/autoload.php`, `/runtime/logs/app.log`, `/composer.json`, `/yii` → **403**, sedangkan
`/`, `/web/site/login`, `/web/erp/api/v1` → **200**.

### 5.1 Database iTrust — `config/db.php`
Berkas ini **gitignored**, jadi harus dibuat/diedit langsung di server:

```php
<?php
return [
    'class' => \yii\db\Connection::class,
    'dsn' => 'mysql:host=localhost;dbname=<akun>_itrust',
    'username' => '<akun>_itrust',
    'password' => 'PASSWORD_DB_ITRUST',
    'charset' => 'utf8mb4',
];
```

### 5.2 Database ERP — `web/.htaccess` (disarankan, tahan regenerasi modul)

```apache
# ==== Modul ERP ====
SetEnv AUREUS_DB_HOST localhost
SetEnv AUREUS_DB <akun>_itrust_erp
SetEnv AUREUS_DB_USER <akun>_erp
SetEnv AUREUS_DB_PASSWORD 'PASSWORD_DB_ERP'
SetEnv AUREUS_CORS_ORIGINS https://itrust-erp.pages.dev
SetEnv AUREUS_TOKEN_TTL_DAYS 90
```

Modul membaca variabel ini lewat `getenv()` **dan** `$_SERVER` (lihat
`modules/erp/config/db.php`), jadi tetap jalan di CGI/suPHP maupun PHP-FPM.

> Jika hosting tidak meneruskan `SetEnv` (jarang, biasanya PHP-FPM dengan `clear_env=on`),
> edit langsung `modules/erp/config/db.php` dan isi kredensialnya. Ingat: berkas itu
> **tertimpa** saat modul diregenerasi — catat nilainya.

### 5.3 Mode produksi & environment — `.env` atau `web/.htaccess`

iTrust kini membaca environment lewat **dua cara** (pilih salah satu):

**A. Berkas `.env` di root proyek (disarankan)** — `web/index.php` memuatnya otomatis
(berkas ini di luar document root, jadi tidak bisa diunduh):

```dotenv
# /home/<akun>/itrust/.env   (contoh lengkap: .env.example)
YII_ENV=prod
YII_DEBUG=false

AUREUS_DB_HOST=localhost
AUREUS_DB=<akun>_itrust_erp
AUREUS_DB_USER=<akun>_erp
AUREUS_DB_PASSWORD=rahasia
AUREUS_CORS_ORIGINS=https://itrust-erp.pages.dev
```

**B. `SetEnv` di `web/.htaccess`** (kalau lebih suka tanpa berkas `.env`):

```apache
SetEnv YII_ENV prod
SetEnv YII_DEBUG false
SetEnv AUREUS_DB_HOST localhost
SetEnv AUREUS_DB <akun>_itrust_erp
SetEnv AUREUS_DB_USER <akun>_erp
SetEnv AUREUS_DB_PASSWORD 'rahasia'
```

Keduanya mematikan **debugger (`yii\debug`)** dan **`gii`**, serta menghentikan log
`$_POST`/`$_SERVER` setiap request:

```
[yii\debug\Module::checkAccess] Access to debugger is denied due to IP address restriction...
[info][application] $_POST = [ ... ]  $_SERVER = [ ... ]
```

Tambahan pengaman di `web/.htaccess`:

```apache
RewriteRule ^(gii|debug)(/|$) - [R=404,L]
```

Jika kedua cara tidak terbaca (PHP-FPM `clear_env=on`), hardcode di `web/index.php`:
`YII_DEBUG=false`, `YII_ENV='prod'`.

---

## 6. Permission

Lewat File Manager (atau SSH):

```bash
chmod -R 775 runtime web/assets modules/erp/runtime
# jika ada unggahan pengguna:
chmod -R 775 uploads web/uploads
```

Pemilik berkas harus user pemilik akun hosting (bukan `nobody`).

---

## 7. Verifikasi

```powershell
# 1) health check modul ERP (tanpa token)
curl.exe -s https://domainmu.com/erp/api/v1
# harapan: {"data":{"name":"iTrustERP API","status":"ok","database":"connected",...}}

# 2) endpoint terlindungi tanpa token
curl.exe -i https://domainmu.com/erp/api/v1/products/products
# harapan: 401 JSON

# 3) login
curl.exe -i -X POST https://domainmu.com/erp/api/v1/login `
  -H "Content-Type: application/json" `
  -d '{\"login\":\"admin\",\"password\":\"PASSWORD_ADMIN\"}'
# harapan: 200 + token

# 4) aplikasi iTrust tetap normal
curl.exe -i https://domainmu.com/
curl.exe -i https://domainmu.com/site/login

# 5) keamanan: berkas sensitif tidak boleh terbaca
curl.exe -i https://domainmu.com/config/db.php     # 403/404
curl.exe -i https://domainmu.com/.env              # 404
```

Kalau health menjawab `"database":"unreachable"` → kredensial/nama DB ERP salah.
Kalau health 500 → lihat `modules/erp/runtime/logs/app.log` dan `runtime/logs/app.log`.

---

## 8. Hubungkan frontend (Cloudflare Pages)

Cloudflare Pages → project `itrust-erp` → *Settings → Variables*:

```
AUREUS_API_URL = https://domainmu.com/erp/api/v1
```

Deploy ulang, lalu login di `https://itrust-erp.pages.dev`.

---

## 9. Update versi berikutnya

```powershell
# lokal
git pull
composer install --no-dev --optimize-autoloader
php scripts/build-itrust-module.php "E:/laragon6/www/ERP/OLD/itrust/modules/erp"
```

Di hosting (SSH):

```bash
cd ~/itrust
git pull
composer install --no-dev --optimize-autoloader
rm -rf runtime/cache/* modules/erp/runtime/cache/*
```

Tanpa SSH: unggah ulang hanya berkas yang berubah (`modules/erp/**`, `config/web.php`, dst.),
lalu hapus isi `runtime/cache/` dan `modules/erp/runtime/cache/`.

---

## 10. Troubleshooting

| Gejala | Penyebab & solusi |
|---|---|
| `500` di semua halaman | PHP bukan 8.4, `vendor/` belum lengkap, atau `runtime/` tidak writable (775) |
| `500` hanya di `/erp/api/v1` | `modules/erp/runtime` tidak writable, atau `SetEnv` tidak terbaca → edit `modules/erp/config/db.php` |
| `404` semua endpoint | Document root belum ke `web/`, atau rewrite mati (`AllowOverride All` + `web/.htaccess`) |
| Log: `.htaccess: AllowOverride not allowed here` | Ada blok vhost (`<VirtualHost>/<Directory>/AllowOverride`) tertempel di `.htaccess` → pakai versi rewrite-only |
| `Table '<db>.users' doesn't exist` | Nama DB ERP di `SetEnv`/`db.php` salah (di cPanel ada prefix akun) |
| Health `database: unreachable` | Kredensial DB ERP salah / user belum diberi privilege |
| Semua request ber-token `401` | Header `Authorization` dibuang CGI/suPHP → pastikan baris pass-through ada di `web/.htaccess` |
| Halaman iTrust error setelah menambah modul | Cek `config/web.php`: `require __DIR__ . '/../modules/erp/config/routes.php'` (bukan `require '@erp/...'`) |
| CPU/proses 100% | Bot scanner + CGI; lihat `DEPLOY.md` §9.1 (rate limit, matikan debug, PHP-FPM, Cloudflare) |
| Login 422 | Body JSON tidak terbaca → pastikan modul terbaru (mendaftarkan `JsonParser`) |
| Log berisi `yii\debug\Module::checkAccess` + rekaman `$_POST/$_SERVER` tiap request | `YII_DEBUG` masih `true` di produksi → tambahkan `SetEnv YII_ENV prod` + `SetEnv YII_DEBUG false` di `web/.htaccess`; `gii`/`debug` otomatis mati |
| `/web/gii` dapat diakses publik | Sama seperti di atas; tambahkan juga `RewriteRule ^(gii|debug)(/|$) - [R=404,L]` |
| `.env` / `config/db.php` / `vendor/` dapat diunduh | Document root = root proyek tanpa proteksi → pasang `.htaccess` root (§5.0) atau pindahkan document root ke `web/` |

---

## 11. Checklist akhir

- [ ] PHP **8.4** + ekstensi lengkap untuk domain
- [ ] Document root = `/home/<akun>/itrust/web`; `/config/db.php` & `/.env` tidak dapat diakses
- [ ] Database `<akun>_itrust` & `<akun>_itrust_erp` dibuat, kedua dump diimpor
- [ ] `config/db.php` (iTrust) & kredensial ERP (SetEnv / `modules/erp/config/db.php`) benar
- [ ] `YII_DEBUG=false`, `YII_ENV=prod` di `web/index.php`
- [ ] Permission 775: `runtime`, `web/assets`, `modules/erp/runtime`, `uploads`
- [ ] `GET /erp/api/v1` → 200 `status: ok`, `database: connected`
- [ ] `POST /erp/api/v1/login` → 200 + token
- [ ] Halaman iTrust (`/`, `/site/login`) normal
- [ ] `AUREUS_API_URL` di Cloudflare Pages menunjuk `/erp/api/v1`
- [ ] Backup harian kedua database terjadwal
