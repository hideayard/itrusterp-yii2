#!/usr/bin/env bash
# =============================================================================
#  Jalankan MySQL + API iTrustERP dengan URUTAN yang benar.
#
#    ./scripts/docker-up.sh            # up: network → MySQL (tunggu healthy) → API
#    ./scripts/docker-up.sh --build    # paksa rebuild image API
#    ./scripts/docker-up.sh status     # status + cek health API
#    ./scripts/docker-up.sh down       # stop API + MySQL (network TETAP ada)
#    ./scripts/docker-up.sh destroy    # stop + hapus network (data volume tetap)
#
#  Script ini menutup dua jebakan yang sering terjadi:
#   1. network bersama belum ada  → "network ... declared as external, but could not be found"
#   2. API dijalankan sebelum MySQL siap
# =============================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

NET="${DB_NETWORK:-itrust-db-network}"
MYSQL_COMPOSE="docker/mysql/docker-compose.yml"
API_URL="http://localhost:8888/"

need_env() {
  [ -f docker/env/db.env ]  || { echo "! docker/env/db.env belum ada. Jalankan: cp docker/env/db.env.example docker/env/db.env"; exit 1; }
  [ -f docker/env/api.env ] || { echo "! docker/env/api.env belum ada. Jalankan: cp docker/env/api.env.example docker/env/api.env"; exit 1; }
}

wait_mysql() {
  printf '→ menunggu MySQL siap'
  for _ in $(seq 1 60); do
    st="$(docker inspect -f '{{.State.Health.Status}}' itrust-db 2>/dev/null || echo starting)"
    if [ "$st" = "healthy" ]; then echo " ok"; return 0; fi
    printf '.'; sleep 2
  done
  echo
  echo "! MySQL tidak sehat setelah ~2 menit. Log terakhir:"
  docker logs itrust-db --tail 40 || true
  exit 1
}

case "${1:-up}" in
  up)
    need_env
    if ! docker network inspect "$NET" >/dev/null 2>&1; then
      echo "→ membuat network $NET"
      docker network create "$NET" >/dev/null
    fi
    echo "→ MySQL (docker compose -f $MYSQL_COMPOSE)"
    docker compose -f "$MYSQL_COMPOSE" up -d
    wait_mysql
    echo "→ API"
    if [ "${2:-}" = "--build" ] || ! docker image inspect itrusterp-yii2:latest >/dev/null 2>&1; then
      docker compose up -d --build
    else
      docker compose up -d
    fi
    echo
    docker compose ps
    echo
    echo "→ health:"
    curl -s "$API_URL" || true
    echo
    ;;
  status)
    docker compose -f "$MYSQL_COMPOSE" ps
    docker compose ps
    echo "→ health:"
    curl -s "$API_URL" || true
    echo
    ;;
  down)
    docker compose down || true
    docker compose -f "$MYSQL_COMPOSE" stop
    echo "→ dihentikan (network $NET dan data volume tetap ada)"
    ;;
  destroy)
    docker compose down || true
    docker compose -f "$MYSQL_COMPOSE" down
    echo "→ container + network dihapus (volume data MySQL tetap; hapus manual bila perlu:"
    echo "   docker volume rm itrust-mysql_db_data)"
    ;;
  *)
    echo "pemakaian: $0 [up|--build|status|down|destroy]"
    exit 1
    ;;
esac
