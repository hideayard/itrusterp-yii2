# Menjalankan BE iTrustERP (Yii2) dengan Docker — Linux Mint 22

Panduan menjalankan **API Yii2** + **MySQL 8.0** dengan Docker di Linux Mint 22.

Dua mode:

| Mode | Berkas | Dipakai bila |
|---|---|---|
| **All-in-one** (default) | `docker-compose.yml` | Belum ada MySQL, atau ingin DB + API dalam satu compose |
| **API saja** | `docker-compose.api-only.yml` | MySQL sudah jalan sebagai container lain (mis. `mysql-itrust` di host :3307) |

```
All-in-one (Mint 22)
├── container itrust-db    → MySQL 8.0, publish 127.0.0.1:3307 -> 3306
└── container itrust-api   → Apache + PHP 8.2, publish :8888 -> 80
        └── konek ke service `db` (hostname di network compose)
```

Kredensial default (override lewat `.env`):

| Variabel | Nilai |
|---|---|
| `MYSQL_ROOT_PASSWORD` | `root_password` |
| `MYSQL_DATABASE` | `erp` |
| `MYSQL_USER` / `MYSQL_PASSWORD` | `itrust` / `itrust_password` |
| Port MySQL di host | `127.0.0.1:3307` |
| Port API di host | `8888` |

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

## 2. User MySQL untuk aplikasi

**Mode all-in-one:** tidak perlu langkah ini — user `itrust` dan database `itrust_db` dibuat
otomatis oleh image MySQL dari variabel `MYSQL_*`. Lewati ke §4.

**Mode API-saja:** `root` di container MySQL yang sudah ada biasanya hanya boleh dari `localhost`
**di dalam container**, sedangkan API container datang lewat TCP. Buat user khusus (sekali saja):

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
| `docker-compose.yml` | **All-in-one**: service `db` (MySQL 8.0, `127.0.0.1:3307`) + service `api` (publish **8888:80**) |
| `docker-compose.api-only.yml` | Hanya service `api`, menembak MySQL yang sudah ada (`host.docker.internal:3307`) |
| `docker/mysql/init/` | Drop `*.sql` di sini → diimpor otomatis saat volume MySQL pertama kali dibuat |
| `.dockerignore` | mengecualikan `vendor/`, `runtime/`, `.git`, `.env`, dll. |

Catatan: `config/db.php` mendukung **`AUREUS_DB_PORT`** (default 3306) dan `.env` (host, port, nama
DB, user, password).

---

## 4. Jalankan (mode all-in-one)

```bash
cd ~/ERP/aureuserp-yii2        # sesuaikan lokasi project

# (opsional) salin & sesuaikan kredensial
cp .env.example .env

docker compose config          # validasi
docker compose up -d --build   # DB + API
docker compose ps
docker compose logs -f api
```

> **Port 3307 bentrok?** Kalau container `mysql-itrust` masih jalan di `0.0.0.0:3307`, `up` akan
> gagal `address already in use`. Pilih salah satu:
> ```bash
> docker stop mysql-itrust                     # kalau tidak dipakai lagi
> # atau ganti port host di docker-compose.yml → "127.0.0.1:3308:3306"
> # atau pakai mode API-saja (lihat akhir bagian ini)
> ```

### Impor data

Dua cara:

```bash
# a) taruh dump di docker/mysql/init/ lalu init ulang volume (impor otomatis)
cp erp.sql docker/mysql/init/01-erp.sql
docker compose down -v && docker compose up -d

# b) impor manual ke container yang sudah jalan
docker compose exec -T db mysql -uitrust -pitrust_password erp < erp.sql
```

### Uji

```bash
curl -s http://localhost:8888/admin/api/v1
# {"data":{"name":"iTrustERP API","status":"ok","database":"connected",...}}

curl -s -X POST http://localhost:8888/admin/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{"login":"admin","password":"admin123"}'
```

Akses MySQL dari host (mis. DBeaver/phpMyAdmin):

```bash
mysql -h127.0.0.1 -P3307 -uitrust -p erp -e "SELECT COUNT(*) FROM products_products;"
# root: mysql -h127.0.0.1 -P3307 -uroot -proot_password
```

Kalau `database` = `unreachable` → kredensial/port DB salah (lihat §7).

### Mode API-saja (MySQL sudah ada)

```bash
docker compose -f docker-compose.api-only.yml up -d --build
# env: AUREUS_DB_HOST=host.docker.internal, AUREUS_DB_PORT=3307
```

---

## 5. Menunjuk frontend Astro ke container

`aureuserp-astro/.env.development`:

```dotenv
AUREUS_API_URL=http://localhost:8888/admin/api/v1
```

Lalu `npm run dev` (Astro memanggil API dari sisi server — tidak perlu CORS).

Untuk uji cepat dari host lain di jaringan LAN, ganti `localhost` dengan IP laptop
(port 8888 sudah dipublish ke `0.0.0.0`).

---

## 6. Perintah harian

```bash
docker compose logs -f api                 # log Apache/PHP
docker compose logs -f db                  # log MySQL
docker compose exec db mysql -uitrust -pitrust_password erp   # shell SQL
docker compose exec api php yii help       # CLI Yii (migrasi/seed/script)
docker compose exec api php scripts/seed-roles.php
docker compose exec api sh                 # masuk ke container

# backup / restore database
docker compose exec -T db mysqldump -uitrust -pitrust_password erp > backup-$(date +%F).sql

# setelah mengubah kode / composer.json
docker compose up -d --build

# hentikan / hapus
docker compose down            # container & network (data tetap di volume)
docker compose down -v         # + hapus volume DB (SEMUA DATA HILANG)
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
| `SQLSTATE[HY000] [2002] Connection refused` | Host/port DB salah. Mode all-in-one: host `db`, port `3306`. Mode API-saja: `host.docker.internal` + `AUREUS_DB_PORT=3307` + `extra_hosts: gateway`. Catatan: `localhost` di dalam container = container itu sendiri |
| `SQLSTATE[HY000] [1045] Access denied` | User MySQL belum dibuat untuk host `'%'`, atau password beda (§2) |
| `SQLSTATE[HY000] [1049] Unknown database` | Nama DB salah / dump belum diimpor |
| `404` di semua endpoint | Document root bukan `web/`. Cek `curl -I http://localhost:8888/index.php` dan pastikan `APACHE_DOCUMENT_ROOT=/var/www/html/web` |
| `.htaccess` error / 500 | `AllowOverride All` belum aktif (sudah di-set di Dockerfile; kalau mengubah image, pastikan tetap ada) |
| `Class not found` setelah update kode | `composer dump-autoload --optimize` dijalankan saat build — rebuild image |
| Healthcheck `unhealthy` | Cek `docker compose logs api`; pastikan `curl` ada di image (sudah dipasang) |
| `host.docker.internal` tidak resolve | Tambahkan `extra_hosts: ["host.docker.internal:host-gateway"]` (wajib di Linux) |

---

## 8. Lupa password MySQL di container

> Cara tercepat: pakai skrip `scripts/reset-mysql-root.sh` (stop → backup → reset via `--init-file`
> → start). Langkah manualnya di bawah.
>
> ```bash
> chmod +x scripts/reset-mysql-root.sh
> ./scripts/reset-mysql-root.sh mysql-itrust 'PasswordBaru#123'
> ```

### A. Cari dulu dari konfigurasi container (sering masih tersimpan)

```bash
docker inspect mysql-itrust --format '{{json .Config.Env}}' | tr ',' '\n' | grep -i MYSQL
# contoh: MYSQL_ROOT_PASSWORD=rahasia
docker exec mysql-itrust env | grep -i MYSQL
```

Kalau ada `MYSQL_ROOT_PASSWORD`, itu password root yang diset **saat container dibuat** (selama tidak
pernah diubah). Cek juga file compose/`docker run` yang dipakai untuk membuatnya.

### B. Reset lewat `--init-file` (disarankan, tanpa mode tanpa-password)

1. Catat **volume** datadir dan **image** aslinya:

   ```bash
   docker inspect mysql-itrust --format '{{range .Mounts}}{{.Type}} {{.Name}} {{.Source}} -> {{.Destination}}{{println}}{{end}}'
   docker inspect mysql-itrust --format '{{.Config.Image}}'
   ```

2. Backup data (disarankan):

   ```bash
   docker stop mysql-itrust
   docker cp mysql-itrust:/var/lib/mysql ./mysql-data-backup-$(date +%F)
   ```

3. Buat berkas SQL reset:

   ```bash
   cat > /tmp/reset.sql <<'SQL'
   ALTER USER 'root'@'localhost' IDENTIFIED BY 'PasswordBaru#123';
   ALTER USER 'root'@'%' IDENTIFIED BY 'PasswordBaru#123';
   SQL
   ```

4. Jalankan container sementara memakai **volume yang sama** + init-file:

   ```bash
   docker run --rm -it \
     -v <VOLUME_NAME>:/var/lib/mysql \
     -v /tmp/reset.sql:/reset.sql:ro \
     mysql:8 --init-file=/reset.sql --skip-networking
   # tunggu "ready for connections", lalu Ctrl+C
   ```

   Gunakan versi **mayor yang sama** dengan image asli (mis. `mysql:8` vs `mysql:8.4`).

5. Nyalakan container asli & uji:

   ```bash
   docker start mysql-itrust
   mysql -h127.0.0.1 -P3307 -uroot -p -e "SELECT VERSION();"
   ```

### C. Alternatif: `--skip-grant-tables`

```bash
docker stop mysql-itrust
docker run --rm -it --name mysql-recover \
  -v <VOLUME_NAME>:/var/lib/mysql \
  mysql:8 --skip-grant-tables --skip-networking
```

Di terminal lain:

```bash
docker exec -it mysql-recover mysql -uroot
FLUSH PRIVILEGES;
ALTER USER 'root'@'localhost' IDENTIFIED BY 'PasswordBaru#123';
```

Selalu pakai `--skip-networking`; tanpa itu siapa pun bisa login tanpa password. Setelah selesai
hentikan container recovery, lalu `docker start mysql-itrust`.

### D. Kalau data boleh hilang (paling cepat)

```bash
docker compose down -v      # hapus volume MySQL
docker compose up -d        # init ulang dengan MYSQL_ROOT_PASSWORD baru
```

### E. Setelah berhasil masuk

1. Perbarui `AUREUS_DB_PASSWORD` di `.env`/`docker-compose.yml` API, lalu `docker compose up -d`.
2. Buat user aplikasi khusus (§2) dan hentikan pemakaian `root` untuk aplikasi.
3. Simpan password di password manager.


## 9. Varian: iTrust + modul ERP

Kalau yang dijalankan adalah **iTrust + modul ERP** (`OLD/itrust`, dokumen root `web/`), polanya sama:

1. Build dari folder itu (`Dockerfile` di atas bisa dipakai apa adanya — docroot `web/`).
2. Tambahkan env modul: `AUREUS_DB_*` menunjuk DB ERP (mis. `itrust_erp` di MySQL container),
   serta `.env`/`SetEnv` untuk `YII_ENV`, `AUREUS_CORS_ORIGINS`.
3. Modul memakai `modules/erp/config/db.php` (membaca `AUREUS_DB_*`, termasuk `AUREUS_DB_PORT`)
   atau `modules/erp/config/db.local.php` (di-gitignore).
4. URL API menjadi `http://localhost:8888/erp/api/v1` (bila document root `web/`).

> Jangan menaruh kredensial di `docker-compose.yml` yang di-commit untuk produksi. Untuk server,
> gunakan `.env` (compose otomatis membacanya) atau `docker secrets`.
