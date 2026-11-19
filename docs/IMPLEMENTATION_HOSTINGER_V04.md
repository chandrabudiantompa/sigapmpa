# Implementasi MPA Workforce di Hostinger + DomaiNesia

## Target alamat

`https://workforce.multipowerabadi.co.id/`

## Tahap 1 — Preview UI

1. Buat subdomain `workforce` pada Hostinger.
2. Arahkan DNS subdomain dari DomaiNesia ke Hostinger.
3. Aktifkan SSL/HTTPS.
4. Upload seluruh isi folder aplikasi ke document root subdomain.
5. Pastikan `index.html` berada langsung pada document root.
6. Uji login demo, tampilan HP, export Excel, dan PWA.

Tahap ini hanya untuk review alur dan desain. Jangan memasukkan data gaji asli.

## Tahap 2 — Database produksi

1. Buat database MySQL dan user database khusus.
2. Import `DATABASE_SCHEMA_V0_4.sql` melalui phpMyAdmin.
3. Gunakan user database dengan hak minimum, bukan user utama hosting.
4. Simpan konfigurasi database di file PHP di luar document root bila paket hosting mendukung.
5. Jangan menaruh password database di JavaScript atau file publik.

## Tahap 3 — Backend PHP/API

Endpoint minimum:

- `POST /api/auth/login`
- `POST /api/auth/logout`
- `POST /api/webauthn/register/options`
- `POST /api/webauthn/register/verify`
- `POST /api/webauthn/auth/options`
- `POST /api/webauthn/auth/verify`
- `POST /api/attendance/check-in`
- `POST /api/attendance/check-out`
- `GET /api/attendance/history`
- `POST /api/requests`
- `POST /api/requests/{id}/approve`
- `POST /api/business-trips/{id}/settle`
- `POST /api/payroll-components`
- `POST /api/payroll-components/{id}/approve`
- `POST /api/payroll-runs/generate`
- `POST /api/payslips/{id}/publish`
- `GET /api/payslips/me`
- `GET /api/exports/payroll.xlsx`

Semua endpoint write wajib menggunakan CSRF protection, session server, validasi role, dan timestamp server.

## Tahap 4 — Role dan pemisahan tugas

- Employee: absensi, pengajuan, melihat slip sendiri.
- Supervisor: approval anggota tim, tanpa akses nominal gaji.
- HR Admin: master karyawan, jadwal, lokasi, cuti.
- Payroll Finance: struktur gaji, komponen variabel, payroll, slip.
- Super Admin: user dan konfigurasi teknis.

Pihak yang input bonus sebaiknya berbeda dari pihak yang menyetujui bila jumlah karyawan dan struktur organisasi memungkinkan.

## Tahap 5 — UAT

Uji minimal:

- akun tidak dapat melihat slip milik orang lain;
- absensi ditolak di luar radius;
- jam HP yang diubah tidak memengaruhi waktu server;
- perangkat baru memerlukan approval;
- bonus draft tidak masuk payroll;
- reimbursement tidak menambah bruto gaji;
- slip terbit mengunci periode;
- revoke dan generate ulang tercatat di audit log;
- export Excel sesuai total pada slip;
- perjalanan dinas menghasilkan komponen yang benar.

## Tahap 6 — Go-live

1. Backup database kosong setelah konfigurasi.
2. Import master karyawan.
3. Registrasi perangkat karyawan.
4. Jalankan parallel test satu periode bersama rekap manual.
5. Cocokkan payroll sistem dengan perhitungan Finance.
6. Go-live setelah selisih nol dan approval manajemen.
