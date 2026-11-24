# Menjalankan BE iTrustERP (Yii2) dengan Docker — Linux Mint 22

Panduan menjalankan **API Yii2** di container, memakai **container MySQL yang sudah ada**
(`mysql-itrust`, publish `0.0.0.0:3307->3306`). **Tidak ada container MySQL baru** yang dibuat.

```
Laptop (Mint 22)
├── container mysql-itrust   → MySQL di host :3307   (SUDAH ADA, tidak diubah)
└── container itrust-api     → Apache + PHP 8.2, publish :8888
        └── host.docker.internal:3307 → MySQL container
```

Kredensial & port (sumber konfigurasi container: **`docker/env/api.env`**, contohnya
`docker/env/api.env.example`):

| Variabel | Nilai |
|---|---|
| `MYSQL_DATABASE` | `erp` |
| `MYSQL_USER` / `MYSQL_PASSWORD` | `itrust` / `itrust_password` |
| `MYSQL_ROOT_PASSWORD` | `root_password` |
| MySQL dari host | `127.0.0.1:3307` |
| API | `http://localhost:8888/admin/api/v1` |

---

## 1. Prasyarat (Mint 22)

> Menjalankan di **WSL2** (Windows)? Lihat juga [`WSL.md`](./WSL.md) — daftar perintah WSL, cara
> mengakses MySQL dari Windows, dan pemulihan saat build crash (`SIGBUS`).

```bash
sudo apt install -y docker.io docker-compose-v2
sudo usermod -aG docker "$USER"   # logout/login agar berlaku
docker --version && docker compose version

# pastikan MySQL container hidup dan lihat port publikasinya
docker ps --format '{{.Names}}\t{{.Status}}\t{{.Ports}}'
# contoh: mysql-itrust  Up 2 months (healthy)  0.0.0.0:3307->3306/tcp
```

Kalau port publikasinya bukan 3307, catat dan sesuaikan `AUREUS_DB_PORT`.

---

## 2. Siapkan database `erp` + user `itrust` di container yang ada

Dilakukan **sekali** saja, langsung di dalam container MySQL (tidak perlu password root bila
kamu bisa `docker exec` — klausa `-p` di bawah hanya perlu bila root punya password):

> PENTING: variabel `MYSQL_DATABASE`/`MYSQL_USER`/`MYSQL_PASSWORD` (di `docker/env/db.env`) **hanya
> dipakai saat container MySQL baru dibuat pertama kali**. Untuk container MySQL yang sudah berjalan
> (mis. `mysql-itrust` yang sudah hidup berbulan-bulan), variabel itu diabaikan — database, user, dan
> hak akses **harus** dibuat lewat SQL seperti di bawah. Kalau tidak, backend akan gagal dengan
> `[1044] Access denied for user … to database …` walaupun user & database-nya "ada".

```bash
docker exec -it mysql-itrust mysql -uroot -proot_password -e "
CREATE DATABASE IF NOT EXISTS erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'itrust'@'%' IDENTIFIED BY 'itrust_password';
GRANT ALL PRIVILEGES ON erp.* TO 'itrust'@'%';
FLUSH PRIVILEGES;
SHOW DATABASES LIKE 'erp';"
```

Uji dari host:

```bash
mysql -h127.0.0.1 -P3307 -uitrust -pitrust_password erp -e "SELECT 'ok' AS status;"
```

Impor dump (bila tabel belum ada):

```bash
mysql -h127.0.0.1 -P3307 -uitrust -pitrust_password erp < erp.sql
```

> Butuh `root`? Bila password root lupa, lihat §8.

---

## 3. File Docker yang dipakai

| Berkas | Fungsi |
|---|---|
| `Dockerfile` | PHP 8.2 + Apache, docroot **`/var/www/html/web`**, ekstensi `pdo_mysql intl zip mbstring gd opcache`, `AllowOverride All` |
| `docker-compose.yml` | **Default**: hanya service `api` (publish **8888:80**), bergabung ke network `${DB_NETWORK:-itrust-db-network}` milik MySQL |
| `docker/mysql/docker-compose.yml` | **MySQL 8.0 saja** (tanpa API): publish `127.0.0.1:3307`, data di named volume `itrust-mysql_db_data`, init dump di `docker/mysql/init` |
| `docker/env/api.env` | Env container **API** (DB, `YII_ENV`, CORS, TTL token, `TZ`) — jangan di-commit; contoh: `docker/env/api.env.example` |
| `docker/env/db.env` | Env container **MySQL** (`docker/mysql/docker-compose.yml`); contoh: `docker/env/db.env.example` |
| `.dockerignore` | mengecualikan `vendor/`, `runtime/`, `.git`, `.env`, `docker/env`, dll. |

Env dipisah per container dan masuk lewat `env_file:` di compose — jadi variabelnya benar-benar
menjadi **environment di dalam container** (tidak perlu `.env` di root, tidak perlu set env di
Apache). Salin contohnya sekali:

```bash
cp docker/env/api.env.example docker/env/api.env
cp docker/env/db.env.example  docker/env/db.env     # hanya bila pakai MySQL dari Docker
```

Catatan: `config/db.php` membaca `AUREUS_DB_HOST`, **`AUREUS_DB_PORT`** (default 3306), `AUREUS_DB`,
`AUREUS_DB_USER`, `AUREUS_DB_PASSWORD`.

---

## 4. Jalankan API

```bash
cd ~/ERP/aureuserp-yii2        # sesuaikan lokasi project

cp docker/env/api.env.example docker/env/api.env   # sesuaikan bila perlu
docker compose config          # validasi
docker compose up -d --build   # build image + jalankan API
docker compose ps
docker compose logs -f api     # pantau
```

Uji:

```bash
# root (sejak rute '' ditambahkan) → info + status API
curl -s http://localhost:8888/
# {"data":{"name":"iTrustERP API","status":"ok","database":"connected",...}}

# base path API sebenarnya:
curl -s http://localhost:8888/admin/api/v1

curl -s -X POST http://localhost:8888/admin/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{"login":"admin","password":"admin123"}'
```

> Membuka `localhost:8888` di browser sebelum rute root ada akan menampilkan
> `{"message":"Resource not found."}` — itu benar, karena base path API adalah `/admin/api/v1`.
> Setelah rute root ada (rebuild image), `/` menampilkan info API, dan `database` di situ bisa
> dipakai untuk memastikan koneksi DB berhasil.

Kalau `database` bukan `connected` → lihat §7.

---

## 5. Menunjuk frontend Astro ke API container

`aureuserp-astro/.env.development`:

```dotenv
AUREUS_API_URL=http://localhost:8888/admin/api/v1
```

Lalu `npm run dev` (Astro memanggil API dari sisi server — tidak perlu CORS).
Untuk akses dari perangkat lain di LAN, ganti `localhost` dengan IP laptop.

---

## 6. Perintah harian

```bash
docker compose logs -f api                    # log Apache/PHP
docker compose exec api php yii help          # CLI Yii (migrasi/seed/script)
docker compose exec api php scripts/seed-roles.php
docker compose exec api sh                    # masuk ke container

# database (tetap di container MySQL yang sudah ada)
docker exec -it mysql-itrust mysql -uitrust -pitrust_password erp
docker exec -t mysql-itrust mysqldump -uitrust -pitrust_password erp > backup-$(date +%F).sql

# setelah mengubah kode / composer.json
docker compose up -d --build

# hentikan API (MySQL tidak tersentuh)
docker compose down
```

### Akses MySQL dari Windows (host) — kasus WSL2

MySQL berjalan di Docker **di dalam VM WSL2**, jadi dari Windows host ada tiga jalur:

**1. Localhost forwarding (cara utama, tanpa setup tambahan)** — cukup connect ke `127.0.0.1`:

```powershell
Test-NetConnection 127.0.0.1 -Port 3307        # TcpTestSucceeded : True
# klien Windows (contoh pakai mysql.exe Laragon):
& "E:\laragon6\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -h127.0.0.1 -P3307 -uitrust -pitrust_password erp -e "SELECT VERSION();"
```

Di DBeaver/TablePlus/HeidiSQL: host `127.0.0.1`, port `3307`, database `erp`, user `itrust`.

Agar paling andal, publish semua antarmuka di dalam WSL (bukan hanya loopback):

```yaml
# docker/mysql/docker-compose.yml
ports:
  - "3307:3306"        # sebelumnya "127.0.0.1:3307:3306"
```

**2. Pakai IP WSL** (kalau localhost forwarding tidak jalan):

```bash
# di dalam WSL
hostname -I | awk '{print $1}'     # mis. 172.24.145.10
ss -ltnp | grep 3307               # pastikan listen di 0.0.0.0:3307
```
```powershell
# di Windows
Test-NetConnection 172.24.145.10 -Port 3307
# catatan: IP ini berubah setiap WSL restart
```

**3. Mirrored networking** (IP WSL = IP Windows, paling stabil):

```ini
# C:\Users\<nama>\.wslconfig
[wsl2]
networkingMode=mirrored
```
```powershell
wsl --shutdown    # lalu masuk lagi
```

Kalau koneksi ditolak: cek (a) MySQL benar-benar listen — `docker exec itrust-db ss -ltnp | grep 3306`,
(b) user mengizinkan host asal — `SHOW GRANTS FOR 'itrust'@'%'` (koneksi dari Windows terlihat sebagai
IP gateway bridge, jadi butuh user `'%'`, bukan `'localhost'`), (c) Windows Defender Firewall tidak
memblokir vEthernet/WSL.

Perubahan kode **tidak** otomatis masuk (image di-COPY saat build), jadi rebuild seperti di atas.
Alternatif saat pengembangan — bind mount di `docker-compose.yml`:
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
| `[2002] Connection refused` | Host/port DB salah. Dari container, `localhost` = container itu sendiri. Pakai `host.docker.internal` + `AUREUS_DB_PORT=3307` (port publikasi container MySQL) dan pastikan `extra_hosts: host-gateway` ada |
| `[1045] Access denied` | User `itrust@'%'` belum dibuat / password beda (§2) |
| Container MySQL `Restarting (1)` terus | lihat `docker logs itrust-db --tail 80` lalu cocokkan: `Permission denied`/`os_file_create` → datadir berada di DrvFs (`/mnt/*`) atau izin salah → pakai named volume; `Data Dictionary version is newer/older` → datadir dibuat versi MySQL lain; `InnoDB ... corruption` → hapus volume lalu impor ulang; `Database is uninitialized and password option is not specified` → isi `MYSQL_ROOT_PASSWORD` di `docker/env/db.env`; `No space left on device` → bebaskan ruang disk |
| `[1044] Access denied for user 'itrust'@'%' to database 'erp'` | User **ada**, tapi belum diberi hak pada database itu. `MYSQL_USER`/`MYSQL_DATABASE` di `db.env` **tidak** berlaku untuk container MySQL yang sudah lama ada (Docker hanya membuatnya saat volume DB pertama kali diinisialisasi). Perbaiki lewat SQL: `GRANT ALL PRIVILEGES ON erp.* TO 'itrust'@'%'; FLUSH PRIVILEGES;` |
| `[1049] Unknown database 'erp'` | Database belum dibuat atau dump belum diimpor (§2) |
| `host.docker.internal` tidak resolve | `extra_hosts: ["host.docker.internal:host-gateway"]` belum ada |
| `404` di semua endpoint | Document root bukan `web/`. Cek `curl -I http://localhost:8888/index.php` |
| `.htaccess` error / 500 | `AllowOverride All` belum aktif (sudah di-set di Dockerfile) |
| `Class not found` setelah update kode | rebuild image (autoload di-generate saat build) |
| Healthcheck `unhealthy` | `docker compose logs api` |
| Port 8888/3307 dipakai proses lain | ganti port host di compose, mis. `"8889:80"` |
| `Failed to Setup IP tables: Unable to enable ACCEPT OUTGOING rule` / `iptables: No chain/target/match by that name` saat membuat network | Docker daemon di host kehilangan chain iptables (biasanya setelah docker di-upgrade tanpa restart, atau `firewalld`/`ufw`/`iptables -F` membersihkan rule). Perbaikan: `sudo systemctl restart docker` lalu ulangi `up -d`. Bila tetap: `sudo modprobe br_netfilter`, `sudo sysctl -w net.ipv4.ip_forward=1`, cek/ matikan `firewalld` (`sudo systemctl disable --now firewalld`), atau pakai `network_mode: host` (lihat catatan di `docker/mysql/docker-compose.yml`) |

### WSL2: crash saat build / `Input/output error`

Gejala: `unexpected fault address … SIGBUS`, `panic during panic` saat `exporting to image`, lalu
perintah apa pun gagal dengan `bash: /usr/bin/docker: Input/output error`.

Itu I/O failure pada disk WSL, bukan masalah compose/image. Urutan penanganan:

```bash
# 1) di dalam WSL — cek ruang disk
df -h /

# 2) di Windows (PowerShell) — cek ruang C: (tempat file ext4.vhdx WSL) dan E:
Get-PSDrive C,E | Select-Object Name,@{n='FreeGB';e={[math]::Round($_.Free/1GB,1)}}

# 3) matikan WSL, lalu buka lagi (pulihkan driver/9p & daemon)
wsl --shutdown
wsl --update
```

Sesudah WSL hidup lagi:

```bash
sudo service docker start        # atau: sudo systemctl start docker
docker ps
docker system df                 # kalau penuh: docker system prune -a (hati-hati: hapus image/container)
```

Penyebab paling sering di WSL:

| Penyebab | Perbaikan |
|---|---|
| Disk C: penuh (vhdx tidak bisa tumbuh) | bebaskan ruang, lalu `wsl --manage <distro> --set-sparse true` dan `Optimize-VHD`/`diskpart compact vdisk` |
| Build dijalankan dari `/mnt/e/...` (DrvFs 9p) | pindahkan proyek ke filesystem Linux, mis. `cp -a /mnt/e/laragon6/www/ERP/aureuserp-yii2 ~/aureuserp-yii2`, build dari situ |
| `/var/lib/docker` rusak setelah crash | `sudo systemctl stop docker`, `sudo rm -rf /var/lib/docker/*` (**semua image & container hilang**), start lagi |
| Perintah `docker` sendiri yang error | `sudo apt install --reinstall docker-ce-cli` (atau restart Docker Desktop dari Windows) |
| Luncurkan ke `/mnt/e` yang bermasalah | `wsl --shutdown` lalu `chkdsk E: /f` dari Windows |

Saran umum: simpan proyek Yii2 & jalankan `docker compose` dari **filesystem Linux WSL**
(`~/project`), bukan `/mnt/c` atau `/mnt/e`.

### Diagnosa `"database":"unreachable"`

Jalankan berurutan dari dalam container — ini memisahkan masalah env / jaringan / kredensial:

```bash
# 1) env yang benar-benar masuk container
docker compose exec api env | grep -E 'AUREUS_DB|YII_'

# 2) pesan error asli (health check mencatatnya)
docker compose exec api tail -n 30 runtime/logs/app.log

# 3) jalur TCP ke MySQL (host + port publish)
docker compose exec api php -r '$e=$s=null;$p=@fsockopen("host.docker.internal",3307,$e,$s,3); var_dump($p!==false,$e,$s);'

# 4) autentikasi MySQL tanpa dbname (memisahkan masalah user vs database)
docker compose exec api php -r 'try{new PDO("mysql:host=host.docker.internal;port=3307","itrust","itrust_password");echo "auth OK\n";}catch(Throwable $x){echo $x->getMessage(),"\n";}'

# 5) user & database di sisi MySQL
docker exec -it mysql-itrust mysql -uroot -p -e \
  "SELECT user,host FROM mysql.user WHERE user IN ('itrust','root'); SHOW DATABASES LIKE 'erp';"

# 6) PENENTU: cetak DSN yang dipakai aplikasi + coba konek dengan config itu
docker compose exec api php -r '
$db = require "/var/www/html/config/db.php";
echo "DSN : ", $db["dsn"], PHP_EOL, "user: ", $db["username"], PHP_EOL;
try { new PDO($db["dsn"], $db["username"], $db["password"], $db["attributes"] ?? []); echo "connect OK\n"; }
catch (Throwable $e) { echo "ERROR: ", $e->getMessage(), PHP_EOL; }'

# 7) hak akses user pada database
docker exec -it mysql-itrust mysql -uroot -p -e "SHOW GRANTS FOR 'itrust'@'%';"
```

Langkah 6 adalah yang paling menentukan: kalau DSN-nya **tidak memuat `;port=3307`**, berarti image
masih kode lama (sebelum dukungan `AUREUS_DB_PORT`) → `docker compose build --no-cache api && docker compose up -d`.
Kalau DSN benar tapi error `Access denied … to database 'erp'`, berarti grant-nya belum ada
(`GRANT ALL PRIVILEGES ON erp.* TO 'itrust'@'%'`).

Membaca hasilnya:

| Kegagalan | Artinya | Solusi |
|---|---|---|
| langkah 3 `false` | jalur ke host:3307 tertutup | pastikan MySQL publish `0.0.0.0:3307->3306` (`docker ps`), `extra_hosts: host-gateway` ada, atau pakai IP gateway: `docker compose exec api ip route \| grep default` → set `AUREUS_DB_HOST=<ip>` (mis. `172.17.0.1`) |
| langkah 4 `[2002] Connection refused` | host/port salah | samakan `AUREUS_DB_PORT` dengan port publish MySQL |
| langkah 4 `[1045] Access denied` | user belum ada untuk host container | buat `'itrust'@'%'` dengan `GRANT ALL ON erp.*` (§2) |
| langkah 4 OK, tapi health tetap degraded | database belum ada | `CREATE DATABASE IF NOT EXISTS erp …` (§2) |
| langkah 1 kosong | `env_file` tidak terbaca | pastikan `docker/env/api.env` ada, lalu `docker compose up -d --force-recreate` |

Untuk melihat error langsung di response (hanya untuk debugging), set sementara
`YII_DEBUG=true` di `docker/env/api.env`:

```bash
docker compose up -d --force-recreate
curl -s http://localhost:8888/ | python3 -m json.tool   # muncul database_error + database_config
```

Setelah selesai, kembalikan `YII_DEBUG=false` dan recreate lagi.

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
docker exec mysql-itrust env | grep -i MYSQL
```

Kalau ada `MYSQL_ROOT_PASSWORD`, itu password yang diset **saat container dibuat** (selama belum
pernah diubah). Cek juga compose/`docker run` yang dipakai membuatnya.

### B. Reset lewat `--init-file` (disarankan)

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
   ALTER USER IF EXISTS 'root'@'localhost' IDENTIFIED BY 'PasswordBaru#123';
   ALTER USER IF EXISTS 'root'@'%' IDENTIFIED BY 'PasswordBaru#123';
   FLUSH PRIVILEGES;
   SQL
   ```

4. Jalankan container sementara memakai **volume yang sama** + init-file (image **sama** dengan asli):

   ```bash
   docker run -d --name mysql-root-reset --entrypoint mysqld \
     -v <VOLUME_NAME>:/var/lib/mysql \
     -v /tmp/reset.sql:/reset.sql:ro \
     <IMAGE_ASLI> --user=mysql --init-file=/reset.sql --skip-networking
   docker logs mysql-root-reset | grep "ready for connections"
   docker stop mysql-root-reset && docker rm mysql-root-reset
   ```

5. Nyalakan container asli & uji:

   ```bash
   docker start mysql-itrust
   docker exec -e MYSQL_PWD='PasswordBaru#123' mysql-itrust mysql -uroot -e 'SELECT VERSION();'
   ```

### C. Alternatif: `--skip-grant-tables`

```bash
docker stop mysql-itrust
docker run --rm -it --name mysql-recover \
  -v <VOLUME_NAME>:/var/lib/mysql \
  <IMAGE_ASLI> --user=mysql --skip-grant-tables --skip-networking
```

Di terminal lain:

```bash
docker exec -it mysql-recover mysql -uroot
FLUSH PRIVILEGES;
ALTER USER 'root'@'localhost' IDENTIFIED BY 'PasswordBaru#123';
```

Selalu pakai `--skip-networking`; tanpa itu siapa pun bisa login tanpa password. Setelah selesai
hentikan container recovery, lalu `docker start mysql-itrust`.

### D. Kalau data MySQL boleh hilang

Container MySQL yang ada **jangan** dihapus kalau masih ada data. Bila memang boleh hilang, cara
paling rapi adalah pindah ke compose yang mengelola DB sendiri (volume `db_data`):

```bash
docker stop mysql-itrust             # bebaskan port 3307
cp docker/env/db.env.example docker/env/db.env
docker compose -f docker/mysql/docker-compose.yml up -d
```

### E. Setelah berhasil masuk

1. Perbarui `AUREUS_DB_PASSWORD` di `docker/env/api.env` (dan `docker/env/db.env` bila memakai
   MySQL sebagai service).
2. Jalankan ulang API: `docker compose up -d --force-recreate`.
3. Buat user aplikasi khusus (§2) dan hentikan pemakaian `root` untuk aplikasi.

---

## 9. Varian lain

**a) Menjalankan MySQL sendiri di Docker** (tanpa container API):

```bash
cp docker/env/db.env.example docker/env/db.env      # sesuaikan kredensial
docker compose -f docker/mysql/docker-compose.yml up -d

# cek
docker exec -it itrust-db mysql -uroot -proot_password -e "SHOW DATABASES;"
# dari host: 127.0.0.1:3307
```

Lalu arahkan API ke server itu. **Default repo sudah begitu**: `docker-compose.yml` memakai network
`${DB_NETWORK:-itrust-db-network}` (dibuat oleh compose MySQL) dan `docker/env/api.env` memakai
`AUREUS_DB_HOST=mysql`, `AUREUS_DB_PORT=3306`.

```bash
docker compose -f docker/mysql/docker-compose.yml up -d   # MySQL dulu (membuat network)
cp docker/env/api.env.example docker/env/api.env
docker compose up -d --build
```

> Urutannya penting: network dibuat oleh compose MySQL. Karena itu jangan `docker compose down` di
> folder `docker/mysql` (network ikut terhapus → API error `network ... declared as external, but
> could not be found`); pakai `stop`, atau buat network-nya sekali:
> `docker network create itrust-db-network`.
>
> Kalau MySQL-mu sudah jalan sebagai container lain dengan network sendiri, cukup set di root `.env`:
> ```dotenv
> DB_NETWORK=mysql_mysql-network
> ```
> lalu sesuaikan `AUREUS_DB_HOST`/`AUREUS_DB_PORT` di `docker/env/api.env`.

**Alternatif tanpa network bersama** (pakai host gateway):

```yaml
# docker/mysql/docker-compose.yml → publish ke semua antarmuka
ports:
  - "3307:3306"
# docker-compose.yml → tambahkan
extra_hosts:
  - "host.docker.internal:host-gateway"
```
```dotenv
# docker/env/api.env
AUREUS_DB_HOST=host.docker.internal
AUREUS_DB_PORT=3307
```

Data MySQL disimpan di named volume `itrust-mysql_db_data` (filesystem Linux). Jangan arahkan
datadir ke `/mnt/c`/`/mnt/e` (DrvFs) — MySQL bisa gagal start atau datanya rusak. Drop `*.sql` ke
`docker/mysql/init/` agar diimpor otomatis saat volume masih kosong.

```bash
docker volume ls | grep db_data                 # lihat
docker compose -f docker/mysql/docker-compose.yml down
docker volume rm itrust-mysql_db_data           # hapus = database hilang
docker compose -f docker/mysql/docker-compose.yml up -d   # init ulang + impor init/*.sql
```

**b) iTrust + modul ERP** (`OLD/itrust`, docroot `web/`):

1. Build dari folder itu (`Dockerfile` di atas bisa dipakai apa adanya).
2. Env `AUREUS_DB_*` → DB ERP (mis. `erp` di container MySQL yang ada), plus `YII_ENV`,
   `AUREUS_CORS_ORIGINS`.
3. Modul membaca `modules/erp/config/db.php` (`AUREUS_DB_*`, termasuk `AUREUS_DB_PORT`) atau
   `modules/erp/config/db.local.php` (di-gitignore).
4. URL API menjadi `http://localhost:8888/erp/api/v1`.

> Jangan menaruh kredensial produksi di `docker-compose.yml` yang di-commit. Gunakan `.env`
> (compose membacanya otomatis) atau `docker secrets`.
