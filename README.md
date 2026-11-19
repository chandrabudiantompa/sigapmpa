# MPA Workforce v0.5 — Production Core

Versi ini mengganti penyimpanan `localStorage` pada prototipe v0.4 dengan backend PHP + MySQL untuk modul inti:

- login dan role server-side;
- master karyawan dan lokasi;
- device binding dengan approval admin;
- absensi masuk/pulang memakai waktu server, GPS, geofence, dan akurasi;
- pengajuan serta approval cuti, izin, sakit, dan lembur;
- perjalanan dinas, approval, realisasi, tunjangan, reimbursement, dan pengembalian uang muka;
- struktur gaji, komponen variabel bulanan, generate payroll, slip gaji, dan export Excel;
- audit log append-only dan pencatatan security event.

## Batasan yang belum boleh disalahpahami

- WebAuthn/passkey biometrik **belum diaktifkan** pada v0.5. Device binding dan GPS sudah server-side, tetapi biometrik memerlukan SSL valid dan implementasi verifikasi signature server.
- Jangan gunakan data asli selama sertifikat masih `Self-signed`.
- Uji formula denda, lembur, BPJS, dan PPh 21 pada data dummy sebelum payroll resmi.
- Backup database sebelum setiap pembaruan.

## Instalasi pada `workforce.multipowerabadi.co.id`

### 1. Database

Skema `docs/DATABASE_SCHEMA_V0_4.sql` harus sudah diimpor ke:

- Database: `u6066544_mpa_workforce`
- User: `u6066544_mpawf_app`

### 2. Upload file

Unggah dan extract seluruh isi paket langsung ke:

`/public_html/workforce.multipowerabadi.co.id`

Pastikan `index.html`, folder `api`, `app`, `assets`, `setup`, dan file `.htaccess` berada langsung di document root.

### 3. Konfigurasi database

Di folder `app`:

1. Copy atau rename `config.local.example.php` menjadi `config.local.php`.
2. Edit `config.local.php`.
3. Isi password database yang benar.
4. Selama SSL belum valid, biarkan:
   `force_https => false`
5. Setelah SSL valid, ubah menjadi:
   `force_https => true`

Jangan membagikan atau menaruh password database di chat/screenshot.

### 4. Tes koneksi

Buka:

`https://workforce.multipowerabadi.co.id/api/health`

Hasil yang benar berupa JSON dengan:

- `"ok": true`
- `"database": true`
- `"installed": false` sebelum akun pertama dibuat.

Jika SSL masih self-signed, tes sementara melalui HTTP hanya untuk koneksi backend. GPS biasanya tidak bekerja pada HTTP biasa.

### 5. Buat Super Admin

Buka:

`https://workforce.multipowerabadi.co.id/setup/`

Buat akun Super Admin pertama. Setelah berhasil, hapus folder `setup` melalui File Manager.

### 6. Setup operasional awal

Urutan yang disarankan:

1. Login sebagai Super Admin.
2. Tambahkan lokasi kantor/proyek dan koordinat sebenarnya.
3. Tambahkan karyawan dan role.
4. Karyawan login dari HP lalu klik **Daftarkan HP**.
5. HR/Super Admin menyetujui perangkat tersebut.
6. Uji check-in dan check-out dengan data dummy.
7. Tambahkan struktur gaji draft, approve, lalu uji komponen variabel dan generate payroll.
8. Cocokkan hasil Excel dan slip dengan perhitungan manual.

## Kontrol keamanan yang aktif

- password menggunakan `password_hash()`;
- session cookie HttpOnly dan SameSite Strict;
- session rotation setelah login;
- CSRF token untuk transaksi;
- pembatasan percobaan login;
- role-based access control;
- waktu absensi dari database/server;
- geofence dan batas akurasi GPS dihitung server;
- satu perangkat approved per karyawan;
- audit log tidak dapat di-update atau dihapus karena trigger database;
- periode dan slip memakai snapshot/versioning.

## Setelah SSL valid

- aktifkan redirect HTTPS di `.htaccess` atau `force_https` pada konfigurasi;
- aktifkan PWA/service worker;
- lanjutkan implementasi WebAuthn/passkey server-side;
- lakukan penetration test dasar dan UAT sebelum go-live.
