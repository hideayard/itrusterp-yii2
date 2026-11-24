# WSL2 — Cheat Sheet (untuk menjalankan API iTrustERP + MySQL di Docker)

Ringkasan perintah WSL yang dipakai sehari-hari saat menjalankan proyek ini. Tanda
**[Windows]** = dijalankan di PowerShell/CMD/Terminal Windows; **[WSL]** = dijalankan di dalam distro.
Lihat juga [`DOCKER.md`](./DOCKER.md) untuk alur Docker-nya.

---

## 1. Masuk / keluar WSL

| Perintah **[Windows]** | Fungsi |
|---|---|
| `wsl` | masuk ke distro default sebagai user default |
| `wsl -d Ubuntu` | masuk ke distro tertentu |
| `wsl ~` | masuk langsung ke home (`~`) |
| `wsl --cd /home/itrust/project` | masuk dan langsung berada di folder itu |
| `wsl -u root` | masuk sebagai root |
| `wsl -e "docker ps"` | jalankan satu perintah tanpa masuk (shell tetap di Windows) |
| `wsl -d Ubuntu -u root -e "service docker status"` | kombinasi distro + user + perintah |

- Keluar: `exit` atau `Ctrl+D` **[WSL]**
- Windows Terminal: dropdown ▾ → pilih `Ubuntu`
- Explorer: ketik `\\wsl.localhost\Ubuntu\home\` di address bar
- Dari dalam WSL, buka folder di Explorer: `explorer.exe .`

Cek apakah sudah di dalam WSL: prompt biasanya `user@NAMA-PC` dan `uname -r` menampilkan kernel
Microsoft (`...-microsoft-standard-WSL2`).

---

## 2. Lihat & kelola distro **[Windows]**

```powershell
wsl -l -v                        # daftar distro, versi (1/2), status
wsl --status                     # distro default + versi WSL
wsl --version                    # versi WSL (error = WSL masih versi lama → wsl --update)
wsl --set-default Ubuntu         # jadikan default
wsl --set-version Ubuntu 2
wsl --update                     # perbarui komponen WSL
wsl --install -d Ubuntu-24.04    # install distro (butuh admin)
wsl -t Ubuntu                    # terminate satu distro
wsl --shutdown                   # matikan SEMUA WSL (VM mati total)
wsl --unregister Ubuntu          # HAPUS distro + semua datanya (hati-hati)
```

---

## 3. Ruang disk & rapikan vhdx

```bash
# [WSL]
df -h /                          # ruang filesystem Linux
du -sh /var/lib/docker           # pemakaian docker
docker system df                 # rincian image/container/volume
docker system prune -a           # hapus yang tak terpakai (HATI-HATI)
```
```powershell
# [Windows]
Get-PSDrive C,E | Select-Object Name,@{n='FreeGB';e={[math]::Round($_.Free/1GB,1)}}
wsl --manage Ubuntu --set-sparse true     # vhdx sparse (auto mengempis)
wsl --shutdown
# compact manual (opsional):
#   diskpart
#   > select vdisk file="C:\Users\<nama>\AppData\Local\Packages\<Ubuntu...>\LocalState\ext4.vhdx"
#   > compact vdisk
```

Catatan: `ext4.vhdx` WSL biasanya ada di **drive C:**. Kalau C: penuh, build/`docker pull` bisa gagal
dengan error I/O (lihat §9).

---

## 4. Pindah distro & pindahkan proyek ke ext4

```powershell
# [Windows] — pindah lokasi data distro
wsl --export Ubuntu D:\wsl\ubuntu-backup.tar      # backup dulu
wsl --shutdown
wsl --manage Ubuntu --move D:\wsl\Ubuntu          # cara modern
# cara klasik bila --move tidak tersedia:
#   wsl --unregister Ubuntu
#   wsl --import Ubuntu D:\wsl\Ubuntu D:\wsl\ubuntu-backup.tar --version 2
#   wsl --manage Ubuntu --set-default-user itrust
```
```bash
# [WSL] — pindahkan PROYEK dari /mnt/e (DrvFs, lambat & rawan I/O error) ke ext4
cp -a /mnt/e/laragon6/www/ERP/aureuserp-yii2 ~/aureuserp-yii2
cd ~/aureuserp-yii2
```

Konversi path:

```bash
wslpath -u 'E:\laragon6\www\ERP'    # → /mnt/e/laragon6/www/ERP
wslpath -w "$(pwd)"                 # → E:\laragon6\www\ERP\...
```

---

## 5. Konfigurasi permanen

`/etc/wsl.conf` **[WSL]** — setelah diubah wajib `wsl --shutdown` lalu masuk lagi:

```ini
[boot]
systemd=true                       # agar `systemctl` bekerja (perlu untuk docker service)
[automount]
options = "metadata,umask=22,fmask=11"
[interop]
appendWindowsPath = false          # mempercepat shell
```

`C:\Users\<nama>\.wslconfig` **[Windows]** — resource VM:

```ini
[wsl2]
memory=4GB
processors=4
swap=2GB
sparseVhd=true
# networkingMode=mirrored           # WSL dapat IP host (Windows 11 + WSL 2.0+)
# autoMemoryReclaim=gradual
```

---

## 6. Docker di dalam WSL **[WSL]**

```bash
sudo service docker start                  # bila systemd belum aktif
sudo systemctl enable --now docker         # bila /etc/wsl.conf sudah systemd=true
sudo systemctl restart docker              # memulihkan chain iptables (DOCKER-FORWARD)
sudo usermod -aG docker "$USER" && newgrp docker
docker ps
docker info | head -20
docker context ls
```

Build & jalankan proyek:

```bash
cd ~/aureuserp-yii2
docker compose -f docker/mysql/docker-compose.yml up -d   # MySQL saja
docker compose up -d --build                              # API
```

---

## 7. Akses MySQL (Docker di WSL) dari Windows **[Windows]**

MySQL ada di dalam VM WSL2, jadi dari host ada tiga jalur:

**1) Localhost forwarding (utama)** — cukup `127.0.0.1`:

```powershell
Test-NetConnection 127.0.0.1 -Port 3307        # TcpTestSucceeded : True
& "E:\laragon6\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -h127.0.0.1 -P3307 -uitrust -pitrust_password erp -e "SELECT VERSION();"
```

DBeaver/TablePlus/HeidiSQL: host `127.0.0.1`, port `3307`, database `erp`, user `itrust`.
Agar paling andal, publish semua antarmuka di WSL: `ports: - "3307:3306"` (bukan
`"127.0.0.1:3307:3306"`).

**2) IP WSL** (bila localhost forwarding tidak jalan):

```bash
# [WSL]
hostname -I | awk '{print $1}'     # mis. 172.24.145.10 — berubah setiap restart
ss -ltnp | grep 3307               # pastikan listen 0.0.0.0:3307
```

**3) Mirrored networking** (IP WSL = IP Windows, paling stabil):

```ini
# C:\Users\<nama>\.wslconfig
[wsl2]
networkingMode=mirrored
```
```powershell
wsl --shutdown    # masuk lagi
```

Kalau ditolak: (a) cek container listen → `docker exec itrust-db ss -ltnp | grep 3306`,
(b) hak user → `SHOW GRANTS FOR 'itrust'@'%'` (koneksi dari Windows tampak sebagai IP gateway
bridge, maka butuh user `'%'`, bukan `'localhost'`), (c) Windows Defender Firewall tidak memblokir
vEthernet/WSL.

---

## 8. Networking & diagnosa **[WSL]**

```bash
ip addr show eth0                  # IP WSL
ip route | grep default            # gateway (mis. 172.24.144.1)
cat /etc/resolv.conf               # DNS
ss -ltnp                           # daftar port yang listen
free -h; nproc                     # resource yang dilihat VM
ps aux --sort=-%mem | head         # proses paling rakus memori
dmesg | tail -30                   # error kernel / I/O
journalctl -u docker --no-pager -n 50
sudo hwclock -s                    # jam WSL melenceng (bikin curl/git/SST error)
```

Akses jaringan dari container ke host Windows:

```bash
docker run --rm alpine ping -c1 host.docker.internal   # butuh extra_hosts: host-gateway
```

---

## 9. Pemulihan: crash build SIGBUS / `Input/output error`

Gejala: `unexpected fault address … SIGBUS`, `panic during panic` saat `exporting to image`, lalu
`bash: /usr/bin/docker: Input/output error` (filesystem VM yang bermasalah, bukan Dockerfile-nya).

```powershell
# [Windows]
wsl --shutdown
wsl --update
Get-PSDrive C,E | Select-Object Name,@{n='FreeGB';e={[math]::Round($_.Free/1GB,1)}}
```
```bash
# [WSL]
sudo service docker start
docker ps
docker system df                   # kalau penuh: docker system prune -a
```

| Penyebab | Perbaikan |
|---|---|
| Disk C: penuh (vhdx tak bisa tumbuh) | bebaskan ruang → `wsl --manage Ubuntu --set-sparse true` → compact vdisk |
| Build dari `/mnt/e/...` (DrvFs 9p) | pindahkan proyek ke `~` (§4) |
| `/var/lib/docker` rusak pasca-crash | `sudo systemctl stop docker && sudo rm -rf /var/lib/docker/* && sudo systemctl start docker` (image/container hilang; data di bind mount `docker/mysql/data` tetap) |
| Binary docker sendiri error | `sudo apt install --reinstall docker-ce-cli` atau restart Docker Desktop |
| Drive `E:` bermasalah | `wsl --shutdown` lalu `chkdsk E: /f` **[Windows]** |

---

## 10. Ringkasan alur harian

```powershell
# [Windows]
wsl --cd ~/aureuserp-yii2
```
```bash
# [WSL]
sudo service docker start
docker compose -f docker/mysql/docker-compose.yml up -d      # MySQL
docker compose up -d --build                                 # API
curl -s http://localhost:8888/ | grep -o '"status":"[^"]*"'  # {"status":"ok"}
```
```powershell
# [Windows] — sambungkan klien DB
Test-NetConnection 127.0.0.1 -Port 3307
```
