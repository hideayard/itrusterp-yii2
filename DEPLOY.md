# Deploy API iTrustERP (Yii2) ke Hosting

Panduan langkah demi langkah menaruh API ini di hosting/VPS agar bisa dipakai frontend Astro
(mis. Cloudflare Pages `itrust-erp`).

Daftar isi:

1. [Prasyarat](#1-prasyarat)
2. [Siapkan paket deploy dari lokal](#2-siapkan-paket-deploy-dari-lokal)
3. [Opsi A — VPS (Nginx + PHP-FPM)](#3-opsi-a--vps-nginx--php-fpm)
4. [Opsi B — Shared hosting (cPanel)](#4-opsi-b--shared-hosting-cpanel)
5. [Import database](#5-import-database)
6. [Konfigurasi `.env`](#6-konfigurasi-env)
7. [Verifikasi](#7-verifikasi)
8. [Menyambungkan ke frontend (Cloudflare Pages)](#8-menyambungkan-ke-frontend-cloudflare-pages)
9. [Hardening & operasional](#9-hardening--operasional)
10. [Deploy ulang (update versi)](#10-deploy-ulang-update-versi)
11. [Troubleshooting](#11-troubleshooting)
12. [Checklist akhir](#12-checklist-akhir)

---

## 1. Prasyarat

| Kebutuhan | Keterangan |
|---|---|
| PHP | **≥ 8.2** (`composer.json`), dengan ekstensi: `pdo_mysql`, `mbstring`, `openssl`, `json`, `curl`, `fileinfo`, `xml`, `intl` |
| Database | MySQL 8 atau MariaDB 10.4+ |
| Composer | Composer 2 (di VPS) atau upload `vendor/` hasil build lokal (di shared hosting) |
| Web server | Apache (mod_rewrite) atau Nginx + PHP-FPM |
| Domain & HTTPS | **Wajib HTTPS** — token Bearer & cookie dikirim di header |
| Akses CLI/SSH | Opsional untuk shared hosting, sangat disarankan di VPS |

> **Document root HARUS diarahkan ke folder `web/`, bukan root proyek.** Kalau tidak, `.env`,
> `config/`, dan `vendor/` bisa diunduh siapa saja.

---

## 2. Siapkan paket deploy dari lokal

```powershell
cd E:\laragon6\www\ERP\aureuserp-yii2

# 1) vendor untuk produksi (tanpa paket dev)
composer install --no-dev --optimize-autoloader

# 2) dump database (struktur + data)
& "E:\laragon6\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -u root `
  -e "mysqldump --single-transaction --routines --triggers --add-drop-table aureuserp > aureuserp.sql"

# 3) paket zip (kecualikan .git & berkas lokal)
Compress-Archive -Path .\* -DestinationPath ..\itrust-api.zip -Force
```

Yang **tidak boleh** ikut naik: `.env` lokal, folder `.git`, `runtime/logs` (boleh, tapi tidak perlu).

---

## 3. Opsi A — VPS (Nginx + PHP-FPM)

Contoh Ubuntu 22.04/24.04, domain `api.domainmu.com`, aplikasi di `/var/www/itrust-api`.

```bash
# 1) Paket dasar
sudo apt update
sudo apt install -y nginx mysql-server php8.2-fpm php8.2-mysql php8.2-mbstring \
  php8.2-xml php8.2-curl php8.2-intl php8.2-zip unzip git

# 2) Ambil kode
sudo mkdir -p /var/www && cd /var/www
sudo git clone https://github.com/<USERNAME>/itrusterp-yii2.git itrust-api
cd /var/www/itrust-api

# 3) Dependensi produksi
composer install --no-dev --optimize-autoloader

# 4) Konfigurasi lingkungan
cp .env.example .env
nano .env         # isi DB, YII_ENV=prod, kunci cookie acak

# 5) Hak akses (hanya folder yang perlu ditulis)
sudo chown -R www-data:www-data /var/www/itrust-api
sudo chmod -R 775 runtime web/assets
```

Nginx (`/etc/nginx/sites-available/itrust-api`):

```nginx
server {
    listen 80;
    server_name api.domainmu.com;
    root /var/www/itrust-api/web;      # <-- WAJIB ke /web
    index index.php;

    client_max_body_size 32m;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;

        # WAJIB: teruskan header Authorization ke PHP, kalau tidak semua request
        # ber-token (Bearer) akan dijawab 401 Unauthorized.
        fastcgi_param HTTP_AUTHORIZATION $http_authorization;
    }

    location ~ /\.(?!well-known) { deny all; }   # blokir .env dll
    location ~* \.(sql|log|md|json|lock)$ { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/itrust-api /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx

# HTTPS gratis
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d api.domainmu.com
```

---

## 4. Opsi B — Shared hosting (cPanel)

1. **Upload kode**: `File Manager` → upload `itrust-api.zip` ke luar `public_html`
   (mis. `/home/user/itrust-api`), lalu Extract. Atau pakai **Git™ Version Control** di cPanel.
2. **Dependensi**: kalau ada akses SSH → `composer install --no-dev --optimize-autoloader`.
   Kalau tidak: jalankan di lokal, lalu upload folder `vendor/` hasilnya.
3. **Document root**: cPanel → *Domains* → pilih subdomain → **Document Root** diarahkan ke
   **`/home/user/itrust-api/web`** (WAJIB). Kalau kolom Document Root tidak bisa diubah ke luar
   `public_html`, opsi alternatif:
   - taruh proyek di luar `public_html`, lalu isi `public_html` dengan isi folder `web/`
     (`index.php`, `.htaccess`, `css/`, `js/`, `images/`, `assets/`) dan ubah baris `require`
     di `index.php` menjadi `__DIR__ . '/../itrust-api/vendor/autoload.php'`; atau
   - buat `public_html` sebagai symlink ke `itrust-api/web` (`ln -s`).
   > **Cek cepat**: buka `https://subdomain.domainmu.com/.env` — harus **403/404**. Kalau
   > terbaca (200), document root salah dan kredensial DB ikut terekspos.
4. **PHP version**: cPanel → *MultiPHP Manager* → pilih **8.2+** untuk subdomain tersebut.
5. **`.env`**: buat via File Manager (Copy dari `.env.example`) dan isi nilainya.
6. **Permission**: `runtime/` dan `web/assets/` → **775** (atau 755 bila suid/permission ketat).
7. **Apache rewrite**: `web/.htaccess` sudah disertakan. Pastikan `AllowOverride All` aktif
   (biasanya standar di cPanel).

### 4.1 Bahaya document root = root proyek (dan cara menutupnya)

Kalau subdomain diarahkan ke **root proyek** (bukan `web/`), berkas berikut bisa diunduh publik:

| URL | Risiko |
|---|---|
| `/.env` | 🔴 kredensial database, kunci cookie |
| `/.git/…` | 🔴 seluruh riwayat kode |
| `/vendor/composer/installed.json`, `/composer.json`, `/composer.lock` | versi paket (memudahkan eksploitasi CVE) |
| `/runtime/logs/app.log` | jejak error, kadang berisi query/data |
| `/yii`, `/requirements.php` | utilitas CLI/instalasi |

**Solusi terbaik: ubah Document Root subdomain ke folder `web/`.**
cPanel → **Domains** → baris `erp.itrust-tech.id` → **Manage** → **Document Root** →
`/home/USER/erp.itrust-tech.id/web` → **Save**. Sesudah itu `.env` otomatis tidak terjangkau
karena berada di luar document root.

**Bila hosting tidak mengizinkan Document Root diubah**, tambahkan `.htaccess` di **document root
saat ini** (root proyek) untuk menutup berkas sensitif. Berkas ini **tidak** bisa meneruskan
request ke `web/` tanpa merusak PATH_INFO, jadi base URL API menjadi
`https://domainmu.com/subfolder/web/admin/api/v1` — tidak masalah karena frontend (Astro/Worker)
memanggil API dari sisi server, URL-nya tidak terlihat pengguna.

```apache
RewriteEngine On

# Tolak berkas sensitif berawalan titik (.env, .git, .htaccess, …),
# /.well-known/ dikecualikan untuk verifikasi SSL.
RewriteCond %{REQUEST_URI} !/\.well-known/
RewriteRule (^|/)\. - [F,L]

# Tolak ekstensi sensitif
RewriteRule \.(env|sql|log|md|lock|yml|yaml|neon|sh|ps1|bat|dist|json)$ - [F,L]

# Tolak folder internal & utilitas CLI
RewriteRule ^(vendor|config|runtime|tests|scripts)(/|$) - [F,L]
RewriteRule ^(yii|yii\.bat|requirements\.php|codeception\.yml|Vagrantfile|docker-compose\.yml)$ - [F,L]

# Root proyek: tidak ada halaman di sini
RewriteRule ^$ - [F,L]
```

> Jangan memakai `<FilesMatch>` + `Require all denied`: pada hosting dengan `AllowOverride`
> terbatas direktif itu memicu **500 Internal Server Error**. Versi rewrite-only di atas aman.

**Pilihan paling rapi (tetap disarankan):** kalau document root tidak bisa dipindah, pindahkan
**isi folder `web/`** ke document root dan simpan proyek di luar document root, lalu ubah bagian
`require` di `index.php` menunjuk ke lokasi proyek (mis. `/home/USER/itrust-api/vendor/autoload.php`,
`/home/USER/itrust-api/config/web.php`) dan `$envFile` ke `/home/USER/itrust-api/.env`.

**Setelah menyegel**, wajib lakukan rotasi kredensial karena `.env` sudah pernah terbaca publik:

1. cPanel → *MySQL® Databases* → ubah password user DB; perbarui `.env`.
2. Ganti `AUREUS_COOKIE_VALIDATION_KEY` dengan nilai acak baru.
3. Hapus/putar ulang token: kosongkan tabel `personal_access_tokens` **saat tidak ada sesi aktif**
   (`TRUNCATE personal_access_tokens;`) sehingga token lama tidak berlaku.
4. Hapus isi `runtime/logs/*.log` bila pernah terekspos.

---

## 5. Import database

**Di VPS:**

```bash
mysql -u root -p -e "CREATE DATABASE aureuserp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "CREATE USER 'aureuserp'@'localhost' IDENTIFIED BY 'rahasia';
                     GRANT ALL PRIVILEGES ON aureuserp.* TO 'aureuserp'@'localhost'; FLUSH PRIVILEGES;"
mysql -u aureuserp -p aureuserp < aureuserp.sql
```

**Di cPanel:** *MySQL® Databases* → buat database + user → *phpMyAdmin* → pilih database → **Import**
→ pilih `aureuserp.sql` → Go.

Catatan:

- Repo ini **tidak memakai migrasi**; skema berasal dari dump di atas. Jadi dump wajib disiapkan.
- Seed role/permission (idempoten, aman diulang) bila perlu:
  ```bash
  php scripts/seed-roles.php
  ```
- Sesuaikan nama DB/user di `.env` dengan yang dibuat hosting (cPanel biasanya memberi prefix,
  mis. `user_aureuserp`).

---

## 6. Konfigurasi `.env`

`.env` dimuat otomatis oleh `web/index.php`, jadi **tidak perlu** menyetel env di Nginx/PHP-FPM.
Kalau env sudah ada di web server, nilainya tidak akan ditimpa.

```dotenv
YII_ENV=prod                 # mematikan YII_DEBUG
YII_DEBUG=false

AUREUS_DB_HOST=localhost
AUREUS_DB=aureuserp
AUREUS_DB_USER=aureuserp
AUREUS_DB_PASSWORD=rahasia

# php -r "echo bin2hex(random_bytes(32));"
AUREUS_COOKIE_VALIDATION_KEY=<kunci-acak-64-karakter>

AUREUS_CORS_ORIGINS=https://itrust-erp.pages.dev
AUREUS_TOKEN_TTL_DAYS=90
```

---

## 7. Verifikasi

```powershell
# 0) Health check tanpa token — membuktikan rewrite + koneksi DB sudah benar
curl.exe -i https://api.domainmu.com/admin/api/v1
# harapan: 200 JSON {"data":{"name":"iTrustERP API","status":"ok","database":"connected",...}}

# 1) Login harus menjawab JSON + token
curl.exe -i -X POST https://api.domainmu.com/admin/api/v1/login `
  -H "Content-Type: application/json" `
  -d '{\"login\":\"admin\",\"password\":\"password-ku\"}'
```

Yang benar: `HTTP/1.1 200`, `content-type: application/json`, ada `"token"`.

```powershell
# 2) Endpoint terlindungi tanpa token -> 401 JSON (bukan halaman HTML/error 500)
curl.exe -i https://api.domainmu.com/admin/api/v1/products/products

# 3) Dengan token -> data
curl.exe -H "Authorization: Bearer <id>|<token>" -H "Accept: application/json" `
  https://api.domainmu.com/admin/api/v1/system/resources
```

Cek juga:

- `https://api.domainmu.com/` → 404 JSON (bukan tampilan error PHP/stack trace);
- `https://api.domainmu.com/.env` → **403/404** (tidak boleh terbaca);
- `runtime/logs/app.log` tidak berisi error baru.

---

## 8. Menyambungkan ke frontend (Cloudflare Pages)

1. Cloudflare Pages → project `itrust-erp` → *Settings → Variables*:
   `AUREUS_API_URL = https://api.domainmu.com/admin/api/v1`.
2. **CORS tidak diperlukan** untuk pola ini, karena Worker memanggil API dari sisi server.
   `AUREUS_CORS_ORIGINS` hanya perlu bila ada klien yang memanggil API langsung dari browser.
3. Kalau API ditaruh di belakang Cloudflare (proxy, awan oranye): aman untuk `GET`, tapi
   **jangan cache** `POST`/`PATCH`/`DELETE` dan endpoint login. Pastikan SSL mode **Full (strict)**.

---

## 9. Hardening & operasional

- `YII_ENV=prod` + `YII_DEBUG=false` (stack trace tidak bocor).
- Kunci `AUREUS_COOKIE_VALIDATION_KEY` unik per lingkungan; jangan commit `.env`.
- Batasi `AUREUS_CORS_ORIGINS` hanya ke domain frontend.
- Backup harian (cron):
  ```bash
  0 2 * * * mysqldump --single-transaction aureuserp | gzip > /backup/aureuserp-$(date +\%F).sql.gz
  ```
- Pantau `runtime/logs/app.log`; rotasi berkala.
- Opsional: `limit_req` di Nginx untuk endpoint login, dan `ufw` (buka hanya 80/443/SSH).
- Token bisa dicabut dengan menghapus barisnya di `personal_access_tokens` (jangan menghapus
  seluruh tabel saat pengguna sedang aktif — itu melogout semua orang).

---

## 10. Deploy ulang (update versi)

```bash
cd /var/www/itrust-api
sudo -u www-data git pull
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo rm -rf runtime/cache/* runtime/logs/*.log
sudo systemctl reload php8.2-fpm      # bila memakai OPcache
```

Di shared hosting tanpa SSH: upload berkas yang berubah, jalankan `composer install` bila
`composer.lock` berubah, lalu hapus isi `runtime/cache/` lewat File Manager.

---

## 11. Troubleshooting

| Gejala | Penyebab & solusi |
|---|---|
| `500` tanpa pesan | Lihat `runtime/logs/app.log`. Umumnya `runtime/` tidak writable → `chmod -R 775 runtime web/assets` dan pastikan owner = user web server |
| Semua endpoint `404` | Document root belum diarahkan ke `web/`, atau rewrite mati. Apache: `AllowOverride All` + `.htaccess` ada. Nginx: `try_files $uri $uri/ /index.php$is_args$args` |
| `404` hanya di `/admin/api/v1/...` | Rewrite tidak jalan (`.htaccess` tidak terbaca) — cek `AllowOverride` |
| `502 Bad Gateway` | Socket PHP-FPM salah versi (`/run/php/php8.2-fpm.sock`) atau service mati |
| `SQLSTATE[HY000] [1045] Access denied` | Kredensial di `.env` salah; di cPanel nama DB/user ber-prefix |
| `Unknown column ...` | Dump DB tidak sesuai versi skema — impor ulang dump terbaru |
| `Could not open input file: vendor/autoload.php` | Belum `composer install` |
| Error PHP “requires 8.2” | Naikkan versi PHP di hosting (MultiPHP Manager) |
| **Semua request ber-token dijawab `401`** walau token benar | Header `Authorization` dibuang oleh web server: Apache/CGI → pastikan blok pass-through di `web/.htaccess` ada; Nginx → tambahkan `fastcgi_param HTTP_AUTHORIZATION $http_authorization;`. Uji: `curl -i -H "Authorization: Bearer x" ...` lalu cek apakah `$_SERVER['HTTP_AUTHORIZATION']` terisi |
| Redirect berulang ke HTTPS | API di belakang Cloudflare: pakai varian `RewriteCond %{HTTP:X-Forwarded-Proto} !https` di `web/.htaccess`, atau set SSL mode Cloudflare ke **Full (strict)** |
| Berkas statis (`/web/index.php`, `robots.txt`) balas `500` | Ada direktif `.htaccess` yang tidak diizinkan `AllowOverride` (`Options`, `DirectoryIndex`, `<FilesMatch>`/`Require`). Pakai `web/.htaccess` versi minimal — hanya blok `mod_rewrite` |
| `.env`, `composer.json`, `vendor/`, `yii` bisa diunduh | Document root masih menunjuk **root proyek**, bukan `web/`. Perbaiki document root, lalu **rotasi kredensial DB + `AUREUS_COOKIE_VALIDATION_KEY`** karena sudah terekspos |
| Login 200 tapi daftar data kosong | Scope perusahaan/ownership: cek `company_id` user & data, atau gunakan akun dengan role `Admin` |
| CORS error di browser | Isi `AUREUS_CORS_ORIGINS` dengan origin frontend (hanya bila klien memanggil API langsung) |
| Cepat logout | `AUREUS_TOKEN_TTL_DAYS` kecil; token juga hilang bila tabel `personal_access_tokens` dibersihkan |
| Aset/gambar 404 | `web/assets/` tidak writable atau belum dibuat |
| `.env` bisa dibuka dari browser | Document root salah (harus `web/`) — segera perbaiki |

---

## 12. Checklist akhir

- [ ] PHP ≥ 8.2 + ekstensi wajib aktif (`php requirements.php` untuk memastikan)
- [ ] Document root = `web/` dan `https://api.domainmu.com/.env` tidak dapat diakses
- [ ] `composer install --no-dev --optimize-autoloader` selesai
- [ ] `.env` terisi: DB, `YII_ENV=prod`, kunci cookie acak
- [ ] Database + user dibuat, dump diimpor, `scripts/seed-roles.php` dijalankan (bila perlu)
- [ ] `runtime/` dan `web/assets/` writable oleh user web server
- [ ] `GET /admin/api/v1` → 200 JSON `status: ok` (health check)
- [ ] `POST /admin/api/v1/login` → 200 + token JSON
- [ ] `GET /admin/api/v1/products/products` tanpa token → 401 JSON
- [ ] HTTPS aktif (certbot/Cloudflare) dan redirect 80 → 443
- [ ] `AUREUS_API_URL` di Cloudflare Pages sudah menunjuk ke domain API
- [ ] Backup DB terjadwal
