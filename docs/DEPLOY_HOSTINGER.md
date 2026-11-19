# Rencana Deploy ke Hostinger + DomaiNesia

## Subdomain yang disarankan

`workforce.multipowerabadi.co.id`

## Tahap prototype frontend

1. Buat subdomain `workforce` di Hostinger dan catat document root-nya.
2. Di DNS DomaiNesia, arahkan record subdomain ke target/IP Hostinger sesuai data hosting.
3. Aktifkan SSL/HTTPS pada subdomain.
4. Upload seluruh isi folder aplikasi ke document root subdomain, bukan folder pembungkusnya.
5. Buka subdomain dan lakukan hard refresh.
6. Uji dari HP: izin lokasi, biometrik, pemasangan PWA, export Excel, dan preview slip.

## Tahap produksi

Prototype `localStorage` tidak boleh langsung dipakai operasional, terutama karena memuat data gaji. Setelah UI disetujui, tambahkan:

- API PHP dan database MySQL
- autentikasi server
- role terpisah HR, Supervisor, Payroll/Finance, dan Super Admin
- challenge/verification WebAuthn di server
- server timestamp
- approval workflow
- payroll closing
- generate dan publish slip dari server
- enkripsi data gaji dan rekening
- backup database otomatis dan terenkripsi
- log keamanan dan rate limiting

## Struktur folder produksi yang disarankan

- `/public` — frontend publik
- `/api` — endpoint PHP
- `/config` — konfigurasi di luar public document root bila hosting mendukung
- `/storage/logs` — log dengan akses web diblokir
- `/storage/payslips` — bila PDF disimpan, akses langsung dari web wajib diblokir
- database MySQL terpisah dengan user berhak minimum

Jangan menyimpan file slip gaji pada URL publik permanen. Lebih aman membuat PDF saat diminta atau memberi signed URL yang cepat kedaluwarsa.
