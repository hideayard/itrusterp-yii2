#!/bin/bash
# =============================================================================
#  Init MySQL: pastikan user aplikasi memakai mysql_native_password + punya hak.
#
#  Dijalankan OTOMATIS sekali saat datadir pertama kali dibuat (volume kosong).
#  Password TIDAK ditulis di berkas ini — diambil dari environment container
#  (docker/env/db.env: MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE).
#
#  Kenapa native_password: klien JDBC/ODBC (DBeaver, TablePlus, Excel) dan
#  library lama menolak caching_sha2_password dengan pesan
#  "Public Key Retrieval is not allowed" bila tidak diset allowPublicKeyRetrieval.
#  PHP 8 + mysqlnd sendiri mendukung keduanya.
#
#  Catatan: pada MySQL 8.4+ plugin ini tidak aktif secara default; tambahkan
#  --mysql-native-password=ON pada `command:` di docker-compose.yml.
# =============================================================================
set -u

if [ -z "${MYSQL_USER:-}" ] || [ -z "${MYSQL_PASSWORD:-}" ]; then
  echo "[init] MYSQL_USER/MYSQL_PASSWORD kosong — lewati pengaturan autentikasi."
  exit 0
fi

DB="${MYSQL_DATABASE:-mysql}"

ROOT_ARGS=(--protocol=socket -uroot)
if [ -n "${MYSQL_ROOT_PASSWORD:-}" ]; then
  ROOT_ARGS+=("-p${MYSQL_ROOT_PASSWORD}")
fi

echo "[init] mengatur ${MYSQL_USER}@'%' → mysql_native_password pada database ${DB}"

mysql "${ROOT_ARGS[@]}" <<SQL || echo "[init] peringatan: gagal mengatur mysql_native_password (plugin mungkin tidak tersedia di versi ini)"
ALTER USER '${MYSQL_USER}'@'%' IDENTIFIED WITH mysql_native_password BY '${MYSQL_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB}\`.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
SQL
