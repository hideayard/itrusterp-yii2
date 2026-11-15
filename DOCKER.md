# Menjalankan BE iTrustERP (Yii2) dengan Docker — Linux Mint 22

Panduan menjalankan **API Yii2** di container, sementara **MySQL sudah berjalan sebagai container
lain** (`mysql-itrust`, publish `0.0.0.0:3307->3306`).

```
Laptop (Mint 22)
├── container mysql-itrust     → MySQL 3306, di-publish ke host :3307
└── container itrust-api       → Apache+PHP 8.2, di-publish ke host :8899
        └── konek ke host.docker.internal:3307 → MySQL container
```

---

## 1. Prasyarat (Mint 22)

```bash
# Docker Engine + compose plugin
sudo apt update
sudo apt install -y docker.io docker-compose-v2
sudo usermod -aG docker "$USER"   # logout/login agar berlaku
docker --version && docker compose version

# Pastikan MySQL container hidup
docker ps --format '{{.Names}}\t{{.Status}}\t{{.Ports}}'
# contoh: mysql-itrust  Up 2 months (healthy)  0.0.0.0:3307->3306/tcp
```

> Kalau `mysql-itrust` berjalan di **network** tertentu, catat namanya:
> ```bash
> docker inspect mysql-itrust --format '{{json .NetworkSettings.Networks}}'
> ```

---

## 2. Siapkan user MySQL untuk aplikasi

`root` di container MySQL biasanya hanya boleh dari `localhost` **di dalam container**. Karena API
container datang lewat TCP, buat user khusus (sekali saja):

```bash
docker exec -it mysql-itrust mysql -uroot -p -e "
CREATE USER IF NOT EXISTS 'itrust'@'%' IDENTIFIED BY 'ganti-password';
GRANT ALL PRIVILEGES ON aureuserp.* TO 'itrust'@'%';
FLUSH PRIVILEGES;"
```

Uji dari host:

```bash
mysql -h127.0.0.1 -P3307 -uitrust -p -e "SELECT COUNT(*) FROM aureuserp.products_products;"
```

> Belum ada database? Impor dump dulu:
> ```bash
> mysql -h127.0.0.1 -P3307 -uitrust -p aureuserp < aureuserp.sql
> ```

---

## 3. File Docker yang dipakai

| Berkas | Fungsi |
|---|---|
| `Dockerfile` | PHP 8.2 + Apache, docroot **`/var/www/html/web`**, ekstensi `pdo_mysql intl zip mbstring gd opcache`, `AllowOverride All` |
| `docker-compose.yml` | Service `api`, publish **8899:80**, env DB (`AUREUS_DB_*`), `host.docker.internal` → host |
| `.dockerignore` | mengecualikan `vendor/`, `runtime/`, `.git`, `.env`, dll. |

Catatan: `config/db.php` kini mendukung **`AUREUS_DB_PORT`** (default 3306), jadi bisa menembak
MySQL container di port **3307**.

---

## 4. Jalankan

```bash
cd ~/ERP/aureuserp-yii2        # sesuaikan lokasi project

# (opsional) validasi compose
docker compose config

# build image + jalankan
docker compose up -d --build

docker compose ps
docker compose logs -f api      # pantau
```

Uji:

```bash
curl -s http://localhost:8899/admin/api/v1
# {"data":{"name":"iTrustERP API","status":"ok","database":"connected",...}}

curl -s -X POST http://localhost:8899/admin/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{"login":"admin","password":"admin123"}'
```

Kalau `database` = `unreachable` → kredensial/port DB salah (lihat §7).

---

## 5. Menunjuk frontend Astro ke container

`aureuserp-astro/.env.development`:

```dotenv
AUREUS_API_URL=http://localhost:8899/admin/api/v1
```

Lalu `npm run dev` (Astro memanggil API dari sisi server — tidak perlu CORS).

Untuk uji cepat dari host lain di jaringan LAN, ganti `localhost` dengan IP laptop
(port 8899 sudah dipublish ke `0.0.0.0`).

---

## 6. Perintah harian

```bash
docker compose logs -f api                 # log Apache/PHP
docker compose exec api php yii help       # CLI Yii (migrasi/seed/script)
docker compose exec api php scripts/seed-roles.php
docker compose exec api sh                 # masuk ke container

# setelah mengubah kode / composer.json
docker compose up -d --build

# hentikan / hapus
docker compose down            # container & network
docker compose down --rmi local
```

Perubahan kode **tidak** otomatis masuk (image di-COPY saat build), jadi rebuild seperti di atas.
Alternatif saat pengembangan: tambahkan bind mount di `docker-compose.yml`:

```yaml
    volumes:
      - ./:/var/www/html
```

(konsekuensinya permission `runtime/` & `web/assets/` harus `www-data`, lihat §7).

---

## 7. Troubleshooting

| Gejala | Penyebab & solusi |
|---|---|
| `500` di semua request | `runtime/` / `web/assets/` tidak writable → `docker compose exec api chown -R www-data:www-data runtime web/assets` |
| `SQLSTATE[HY000] [2002] Connection refused` | Host/port DB salah. Dari dalam container `localhost` = container itu sendiri. Pakai `host.docker.internal` + `AUREUS_DB_PORT=3307`, dan pastikan `extra_hosts: host-gateway` ada |
| `SQLSTATE[HY000] [1045] Access denied` | User MySQL belum dibuat untuk host `'%'`, atau password beda (§2) |
| `SQLSTATE[HY000] [1049] Unknown database` | Nama DB salah / dump belum diimpor |
| `404` di semua endpoint | Document root bukan `web/`. Cek `curl -I http://localhost:8899/index.php` dan pastikan `APACHE_DOCUMENT_ROOT=/var/www/html/web` |
| `.htaccess` error / 500 | `AllowOverride All` belum aktif (sudah di-set di Dockerfile; kalau mengubah image, pastikan tetap ada) |
| `Class not found` setelah update kode | `composer dump-autoload --optimize` dijalankan saat build — rebuild image |
| Healthcheck `unhealthy` | Cek `docker compose logs api`; pastikan `curl` ada di image (sudah dipasang) |
| `host.docker.internal` tidak resolve | Tambahkan `extra_hosts: ["host.docker.internal:host-gateway"]` (wajib di Linux) |

---

## 8. Varian: iTrust + modul ERP

Kalau yang dijalankan adalah **iTrust + modul ERP** (`OLD/itrust`, dokumen root `web/`), polanya sama:

1. Build dari folder itu (`Dockerfile` di atas bisa dipakai apa adanya — docroot `web/`).
2. Tambahkan env modul: `AUREUS_DB_*` menunjuk DB ERP (mis. `itrust_erp` di MySQL container),
   serta `.env`/`SetEnv` untuk `YII_ENV`, `AUREUS_CORS_ORIGINS`.
3. Modul memakai `modules/erp/config/db.php` (membaca `AUREUS_DB_*`, termasuk `AUREUS_DB_PORT`)
   atau `modules/erp/config/db.local.php` (di-gitignore).
4. URL API menjadi `http://localhost:8899/erp/api/v1` (bila document root `web/`).

> Jangan menaruh kredensial di `docker-compose.yml` yang di-commit untuk produksi. Untuk server,
> gunakan `.env` (compose otomatis membacanya) atau `docker secrets`.
