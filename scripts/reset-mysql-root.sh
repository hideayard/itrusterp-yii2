#!/usr/bin/env bash
#
# Reset password root MySQL pada container Docker.
#
# Pemakaian:
#   ./scripts/reset-mysql-root.sh [nama-container] [password-baru]
#
# Contoh:
#   ./scripts/reset-mysql-root.sh mysql-itrust 'PasswordBaru#123'
#   ./scripts/reset-mysql-root.sh            # password ditanya interaktif
#
# Cara kerja: container dihentikan → datadir di-backup → server sementara dijalankan
# memakai volume yang SAMA dengan --init-file (dijalankan sebagai root server, jadi
# tidak butuh password lama) → container asli dinyalakan kembali.
set -euo pipefail

CONTAINER="${1:-mysql-itrust}"
NEWPASS="${2:-}"

if [ -z "$NEWPASS" ]; then
  read -rsp "Password baru untuk root: " NEWPASS
  echo
fi
if [ -z "$NEWPASS" ]; then
  echo "error: password tidak boleh kosong" >&2
  exit 1
fi
case "$NEWPASS" in
  *"'"*) echo "error: password tidak boleh mengandung tanda kutip tunggal (')" >&2; exit 1 ;;
esac

command -v docker >/dev/null 2>&1 || { echo "error: docker tidak ditemukan" >&2; exit 1; }
docker inspect "$CONTAINER" >/dev/null 2>&1 || { echo "error: container '$CONTAINER' tidak ada" >&2; exit 1; }

IMAGE="$(docker inspect "$CONTAINER" --format '{{.Config.Image}}')"
VOLNAME="$(docker inspect "$CONTAINER" --format '{{range .Mounts}}{{if eq .Destination "/var/lib/mysql"}}{{.Name}}{{end}}{{end}}')"
SRCDIR="$(docker inspect "$CONTAINER" --format '{{range .Mounts}}{{if eq .Destination "/var/lib/mysql"}}{{.Source}}{{end}}{{end}}')"

if [ -n "$VOLNAME" ]; then
  MOUNT="$VOLNAME:/var/lib/mysql"
elif [ -n "$SRCDIR" ]; then
  MOUNT="$SRCDIR:/var/lib/mysql"
else
  echo "error: tidak menemukan datadir (/var/lib/mysql) pada container '$CONTAINER'" >&2
  exit 1
fi

echo "container : $CONTAINER"
echo "image     : $IMAGE"
echo "datadir   : $MOUNT"
echo

read -rp "Lanjut reset password root? container akan dihentikan sementara [y/N] " CONFIRM
case "$CONFIRM" in
  y|Y) ;;
  *) echo "dibatalkan"; exit 0 ;;
esac

BACKUP_DIR="./mysql-data-backup-$(date +%F-%H%M%S)"

echo "[1/5] menghentikan container..."
docker stop "$CONTAINER" >/dev/null

echo "[2/5] backup datadir -> $BACKUP_DIR"
mkdir -p "$BACKUP_DIR"
docker run --rm --entrypoint sh \
  -v "$MOUNT" \
  -v "$(cd "$BACKUP_DIR" && pwd):/backup" \
  alpine -c 'cp -a /var/lib/mysql/. /backup/' >/dev/null

echo "[3/5] menjalankan server sementara dengan --init-file"
SQL_FILE="$(mktemp)"
trap 'rm -f "$SQL_FILE"' EXIT
cat > "$SQL_FILE" <<SQL
ALTER USER IF EXISTS 'root'@'localhost' IDENTIFIED BY '${NEWPASS}';
ALTER USER IF EXISTS 'root'@'%' IDENTIFIED BY '${NEWPASS}';
FLUSH PRIVILEGES;
SQL
chmod 644 "$SQL_FILE"

docker rm -f mysql-root-reset >/dev/null 2>&1 || true
docker run -d --name mysql-root-reset \
  --entrypoint mysqld \
  -v "$MOUNT" \
  -v "$SQL_FILE:/reset.sql:ro" \
  "$IMAGE" \
  --user=mysql --init-file=/reset.sql --skip-networking >/dev/null

echo "[4/5] menunggu server selesai..."
READY=0
for _ in $(seq 1 30); do
  if docker logs mysql-root-reset 2>&1 | grep -q "ready for connections"; then
    READY=1
    break
  fi
  sleep 1
done

ERRORS="$(docker logs mysql-root-reset 2>&1 | grep -i '\[ERROR\]' || true)"
docker stop mysql-root-reset >/dev/null 2>&1 || true
docker rm mysql-root-reset >/dev/null 2>&1 || true

if [ "$READY" -ne 1 ]; then
  echo "error: server sementara tidak siap; container asli TIDAK dinyalakan." >&2
  echo "log bagus ada di $BACKUP_DIR (datadir sudah dibackup)." >&2
  echo "coba: docker run --rm -it -v $MOUNT $IMAGE --user=mysql --init-file=/reset.sql --skip-networking" >&2
  exit 1
fi
if [ -n "$ERRORS" ]; then
  echo "peringatan: ada error di log server sementara:"
  echo "$ERRORS" | sed 's/^/  /'
fi

echo "[5/5] menyalakan kembali container asli..."
docker start "$CONTAINER" >/dev/null

echo "     menguji login root dengan password baru..."
if docker exec -e MYSQL_PWD="$NEWPASS" "$CONTAINER" mysql -uroot -e 'SELECT "login-ok" AS status;' >/dev/null 2>&1; then
  echo "     OK: login root berhasil dengan password baru."
else
  echo "     PERINGATAN: login root gagal. Periksa: docker logs $CONTAINER" >&2
fi

cat <<EOF

Selesai. Password root telah direset.

Backup datadir : $BACKUP_DIR
Uji            : docker exec -it $CONTAINER mysql -uroot -p -e 'SELECT VERSION();'

Langkah lanjutan (disarankan):
  1) Buat user aplikasi, jangan pakai root:
     docker exec -it $CONTAINER mysql -uroot -p -e "
       CREATE USER IF NOT EXISTS 'itrust'@'%' IDENTIFIED BY 'password-app';
       GRANT ALL PRIVILEGES ON aureuserp.* TO 'itrust'@'%';
       FLUSH PRIVILEGES;"
  2) Perbarui AUREUS_DB_PASSWORD pada .env / docker-compose.yml, lalu: docker compose up -d
EOF
