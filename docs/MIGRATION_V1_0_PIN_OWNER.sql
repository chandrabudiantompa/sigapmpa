-- SIGAP MPA v1.0.0
-- Jalankan SATU KALI melalui phpMyAdmin pada database u6066544_mpa_workforce
-- Backup database sebelum menjalankan.

ALTER TABLE users
  MODIFY COLUMN role ENUM('employee','supervisor','hr_admin','payroll_finance','owner','super_admin') NOT NULL;

ALTER TABLE employee_devices
  ADD COLUMN attendance_pin_hash VARCHAR(255) NULL AFTER signature_counter,
  ADD COLUMN pin_failed_count SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER attendance_pin_hash,
  ADD COLUMN pin_locked_until DATETIME(6) NULL AFTER pin_failed_count,
  ADD COLUMN pin_updated_at DATETIME(6) NULL AFTER pin_locked_until;

ALTER TABLE attendance
  ADD COLUMN check_in_auth_method VARCHAR(20) NULL AFTER device_id,
  ADD COLUMN check_out_auth_method VARCHAR(20) NULL AFTER check_in_auth_method;
