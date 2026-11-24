# Taruh dump database di folder ini (mis. erp.sql) untuk diimpor OTOMATIS
# saat container MySQL pertama kali diinisialisasi (data masih kosong).
#
#   cp erp.sql docker/mysql/init/01-erp.sql
#   docker compose -f docker/mysql/docker-compose.yml down
#   rm -rf docker/mysql/data/*          # hati-hati: data lama terhapus
#   docker compose -f docker/mysql/docker-compose.yml up -d
#
# Urutan impor mengikuti nama berkas (01-..., 02-...). Berkas *.sh juga dijalankan.
#
# Untuk database yang SUDAH berjalan, impor manual:
#   docker exec -i itrust-db mysql -uitrust -pitrust_password erp < erp.sql
