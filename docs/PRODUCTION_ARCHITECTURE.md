# Arsitektur Produksi MPA Workforce

## Keputusan utama

Aplikasi produksi tidak boleh mempercayai browser untuk menentukan waktu, status lokasi, hasil biometrik, denda, lembur, atau nominal slip gaji. Browser hanya mengirim bukti dan permintaan; server melakukan validasi, perhitungan, otorisasi, dan penyimpanan final.

## Rekomendasi deployment

- Domain: `workforce.multipowerabadi.co.id`
- DNS: dikelola di DomaiNesia
- Frontend: Hostinger pada document root subdomain
- Backend yang cocok untuk shared hosting: PHP 8.2+ REST API
- Database: MySQL/MariaDB
- File upload: hanya dokumen pendukung cuti/sakit bila diperlukan, bukan selfie
- HTTPS: wajib aktif

Alternatif backend modern: Firebase Authentication + Firestore + Cloud Functions. Bila seluruh aplikasi ingin berada di Hostinger, PHP + MySQL lebih mudah dioperasikan.

## Role minimum

- `employee`: absensi, pengajuan, dan melihat slip milik sendiri
- `supervisor`: approval tim
- `hr_admin`: master karyawan, jadwal, lokasi, dan cuti
- `payroll_finance`: struktur gaji, generate, review, dan terbitkan slip
- `super_admin`: konfigurasi dan kontrol akses, tetapi akses nominal gaji tetap dapat dibatasi

Prinsipnya adalah least privilege. Supervisor tidak otomatis boleh melihat nominal gaji.

## Tabel inti

- `users`: akun, role, status, password hash
- `employees`: NIK, data kerja, departemen, atasan, kuota cuti
- `employee_salary_structures`: komponen gaji, rekening, effective date, pembuat, approver
- `work_locations`: koordinat, radius, batas akurasi, status
- `devices`: user, credential WebAuthn, public key, status approval
- `attendance`: check-in/out, waktu server, lokasi, akurasi, device, status
- `requests`: cuti/izin/sakit/lembur, periode, alasan, status
- `business_trips`: proyek, tujuan, uang muka, realisasi, reimbursement, dan pengembalian
- `approvals`: approver, keputusan, catatan, waktu server
- `payroll_periods`: periode, status open/review/closed, locked_at
- `payroll_components`: bonus proyek, tunjangan dinas, rapel, potongan, dan reimbursement
- `payroll_runs`: hasil hitung per karyawan dan versi perhitungan
- `payslips`: snapshot penghasilan, potongan, take-home pay, status draft/published/revoked
- `payslip_access_logs`: siapa membuka, export, atau mencetak slip
- `audit_logs`: actor, action, entity, before/after, IP, user agent, waktu server
- `security_events`: percobaan ditolak, alasan, lokasi, device, IP

## Kontrol anti-kecurangan absensi

1. WebAuthn challenge dibuat server dan hanya berlaku singkat.
2. Server memverifikasi signature credential dan counter.
3. Waktu check-in/out memakai timestamp database/server.
4. Server menghitung jarak geofence dari koordinat kantor/proyek.
5. Server menolak GPS dengan akurasi di atas batas kebijakan.
6. Satu credential/device aktif per karyawan, kecuali ada approval admin.
7. Rate limit untuk login, challenge, check-in, dan check-out.
8. Token sesi pendek, rotasi refresh token, dan logout dari semua perangkat.
9. Audit log append-only; user biasa tidak dapat mengubah atau menghapus.
10. Periode payroll yang sudah closing dikunci. Koreksi memakai adjustment dan approval.
11. Data master denda/lembur memakai effective date agar perubahan tidak mengubah periode lama.
12. Export Excel dihasilkan server dengan nomor versi dan waktu pembuatan.

## Kontrol keamanan slip gaji

1. Slip hanya dapat dibaca oleh pemiliknya dan role Payroll/Finance yang berwenang.
2. Endpoint selalu memverifikasi `employee_id` dari sesi, bukan dari parameter yang dikirim browser.
3. Nominal dan rekening dienkripsi saat tersimpan; koneksi wajib HTTPS.
4. Karyawan melakukan re-authentication/biometrik sebelum membuka nominal.
5. Draft belum terlihat oleh karyawan.
6. Slip yang diterbitkan disimpan sebagai snapshot immutable dengan nomor versi.
7. Koreksi dilakukan melalui revoke, adjustment, generate ulang, dan publish kembali; tidak overwrite diam-diam.
8. Semua aksi view, export, print, publish, dan revoke masuk audit log.
9. File PDF bila disimpan harus memakai URL sementara, akses terbatas, dan tidak berada di folder publik.
10. Backup database terenkripsi dan akses database memakai user dengan hak minimum.

## Batasan web yang harus dipahami

Web tidak membaca atau menyimpan citra sidik jari. WebAuthn meminta perangkat melakukan user verification, yang dapat berupa sidik jari, wajah, Windows Hello, atau PIN perangkat. Anti-fake-GPS di browser tidak dapat dijamin 100%; device binding, anomali lokasi, QR dinamis proyek, dan audit supervisor tetap diperlukan untuk lokasi berisiko tinggi.

## Kontrol bonus dan perjalanan dinas

1. Bonus/insentif harus memiliki periode, karyawan, proyek, nominal, pembuat, dan approver.
2. Hanya komponen berstatus approved yang boleh masuk payroll.
3. Reimbursement tidak menambah penghasilan bruto; tampil terpisah pada slip dan total transfer.
4. Uang muka perjalanan dinas wajib direalisasikan dan dicocokkan dengan bukti.
5. Periode yang slipnya sudah terbit tidak dapat diubah tanpa revoke dan audit.
6. Komponen hasil realisasi perjalanan dinas harus memiliki relasi ke transaksi perjalanan asal.
