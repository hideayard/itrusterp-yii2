# Taruh dump database di folder ini (mis. erp.sql) untuk diimpor OTOMATIS
# saat container MySQL pertama kali diinisialisasi (volume masih kosong).
#
#   docker compose down -v     # hapus volume  -> init ulang + impor ulang
#   docker compose up -d
#
# Urutan impor mengikuti nama berkas (01-..., 02-...).
