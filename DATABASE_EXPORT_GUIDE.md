# Cara Export Database SIGAP MPA ke .sql

Saya tidak bisa mengambil database live langsung dari server/cPanel Bapak.
Export database harus dilakukan dari phpMyAdmin.

## Nama database
Kemungkinan database aktif:
`u6066544_mpa_workforce`

Pastikan di `app/config.local.php` bagian:
`db.name`

## Langkah export via phpMyAdmin
1. Masuk cPanel.
2. Buka phpMyAdmin.
3. Pilih database SIGAP, misalnya `u6066544_mpa_workforce`.
4. Klik tab Export.
5. Pilih Quick jika ingin cepat, atau Custom jika ingin lebih aman.
6. Format: SQL.
7. Klik Export / Go.
8. Simpan file `.sql`.

## Rekomendasi export aman
Jika pakai Custom:
- centang Add DROP TABLE / VIEW / PROCEDURE / FUNCTION / EVENT statement
- centang IF NOT EXISTS jika tersedia
- format SQL
- compression: zipped/gzipped boleh

## Setelah export
Simpan dengan nama:
`sigap_mpa_database_YYYYMMDD.sql`

Contoh:
`sigap_mpa_database_20260717.sql`

## Jangan share password database
Jangan kirim `app/config.local.php` secara terbuka jika berisi password database.
