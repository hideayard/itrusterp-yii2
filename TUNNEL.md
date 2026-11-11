# Menjalankan API iTrustERP dari Laptop via Cloudflare Tunnel

Alternatif hosting: API (Yii2 + MySQL) jalan **di laptop** dan diekspos ke internet lewat
**Cloudflare Tunnel** — gratis, HTTPS, tanpa port-forwarding, dan tidak kena kuota shared hosting.

```
Internet → Cloudflare (DNS + WAF) → cloudflared (outbound dari laptop) → http://erp-api.local
                                                                      → Yii2 → MySQL lokal
```

## Kapan cocok / tidak

| Cocok | Tidak cocok |
|---|---|
| Demo & staging, uji POS sebelum sewa server | Produksi yang harus hidup 24/7 (laptop tidur/mati = API mati) |
| Kasir di toko dengan mini-PC/laptop sebagai server | Trafik publik bervolume besar (bandwidth rumah) |
| Menghindari kuota & batas proses shared hosting | Kebutuhan SLA/backup otomatis |

## 1. Siapkan API lokal (sekali)

1. Vhost Laragon dengan **document root = folder `web/`** (mis. `erp-api.local`) — jangan pakai
   root proyek, agar `.env` tidak bisa diunduh:

   ```apache
   <VirtualHost *:80>
       DocumentRoot "E:/laragon6/www/ERP/aureuserp-yii2/web"
       ServerName erp-api.local
       <Directory "E:/laragon6/www/ERP/aureuserp-yii2/web">
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

   Tambahkan `127.0.0.1 erp-api.local` di `C:\Windows\System32\drivers\etc\hosts`, lalu Reload Apache.

2. `.env` (di root proyek) — mode produksi supaya stack trace tidak bocor:

   ```dotenv
   YII_ENV=prod
   YII_DEBUG=false
   AUREUS_DB_HOST=127.0.0.1
   AUREUS_DB=aureuserp
   AUREUS_DB_USER=root
   AUREUS_DB_PASSWORD=
   AUREUS_COOKIE_VALIDATION_KEY=<64 karakter acak>
   AUREUS_CORS_ORIGINS=https://itrust-erp.pages.dev
   AUREUS_TOKEN_TTL_DAYS=90
   ```

3. Uji lokal: `http://erp-api.local/admin/api/v1` → harus balas JSON `status: ok`.

## 2. Pasang & jalankan Cloudflare Tunnel

```powershell
winget install --id Cloudflare.cloudflared     # atau unduh MSI dari Cloudflare
cloudflared tunnel login                       # login via browser, pilih domain kamu
cloudflared tunnel create itrust-api           # catat Tunnel ID yang ditampilkan
cloudflared tunnel route dns itrust-api api.itrust-tech.id
```

> Pakai subdomain **baru** (`api.itrust-tech.id`) agar `erp.itrust-tech.id` tetap bebas untuk
> keperluan lain. DNS record-nya akan dibuat otomatis oleh perintah `route dns`.

Berkas `%USERPROFILE%\.cloudflared\config.yml`:

```yaml
tunnel: itrust-api
credentials-file: C:\Users\<NAMA-USER>\.cloudflared\<TUNNEL-ID>.json

ingress:
  - hostname: api.itrust-tech.id
    service: http://erp-api.local
    originRequest:
      httpHostHeader: erp-api.local   # penting: agar vhost Laragon dikenali
      connectTimeout: 10s
  - service: http_status:404
```

Jalankan:

```powershell
cloudflared tunnel run itrust-api        # uji manual
cloudflared service install              # (PowerShell as Admin) jalan otomatis saat Windows nyala
```

Tes cepat tanpa konfigurasi (URL acak `trycloudflare.com`, cocok untuk mencoba 2 menit):

```powershell
cloudflared tunnel --url http://erp-api.local
```

## 3. Setelan di Cloudflare

- **SSL/TLS**: mode **Full**.
- **Security → Bot Fight Mode**: ON (menyaring pemindai otomatis).
- **Security → WAF → Rate limiting rule**: batasi `POST /admin/api/v1/login`
  (mis. 10 permintaan/menit per IP) — melindungi dari brute force.
- **WAF → Custom rule** (opsional, memperkecil permukaan): block jika
  `http.request.uri.path` **tidak** mengandung `/admin/api/v1`.
- **Zero Trust Access**: jangan dipasang untuk seluruh API, karena pemanggilnya adalah Worker
  Cloudflare Pages (tanpa login). Bila ingin dilindungi, gunakan **Service Token** +
  header khusus dari sisi Astro.

## 4. Sisi frontend (Cloudflare Pages)

Di project `itrust-erp` → *Settings → Variables*:

```
AUREUS_API_URL = https://api.itrust-tech.id/admin/api/v1
```

Deploy ulang Pages, lalu login di `https://itrust-erp.pages.dev`.

## 5. Agar laptop tetap melayani

- Power Options: **jangan sleep/hibernate** saat tercolok listrik.
- Aktifkan **Laragon auto-start** + `cloudflared service` (jalan sebagai service Windows).
- Matikan Windows Update auto-restart pada jam kerja.
- Pertimbangkan mini-PC/NUC yang menyala 24/7 bila dipakai untuk toko sungguhan.

## 6. Keamanan wajib (karena origin ada di jaringan rumah)

- `YII_ENV=prod` + `YII_DEBUG=false` — tanpa ini, halaman error Yii bisa membocorkan detail.
- MySQL hanya listen `127.0.0.1` (default) — jangan buka ke LAN/internet.
- Password admin API kuat; `AUREUS_TOKEN_TTL_DAYS` wajar (90 hari); cabut token yang tidak dipakai
  (`DELETE FROM personal_access_tokens WHERE expires_at < NOW();`).
- Jangan pernah memakai root proyek sebagai document root (`web/` selalu).
- Batasi laju login di Cloudflare (langkah 3) dan perbarui dependensi (`composer update`) berkala.
- Backup DB terjadwal (mis. Task Scheduler harian menjalankan `mysqldump`).

## 7. Checklist

- [ ] `http://erp-api.local/admin/api/v1` balas JSON `status: ok` (lokal)
- [ ] `cloudflared tunnel run` tanpa error; `https://api.itrust-tech.id/admin/api/v1` balas JSON
- [ ] `https://api.itrust-tech.id/.env` → 404, `/wp-login.php` → 404 cepat
- [ ] Handler PHP lokal tidak menampilkan stack trace (YII_ENV=prod)
- [ ] `AUREUS_API_URL` di Cloudflare Pages sudah menunjuk ke tunnel
- [ ] Rate limit login aktif; Bot Fight Mode ON
- [ ] Laragon + cloudflared auto-start; laptop tidak sleep saat tercolok
