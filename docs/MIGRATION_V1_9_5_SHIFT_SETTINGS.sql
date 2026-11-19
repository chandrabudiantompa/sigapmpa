-- SIGAP MPA v1.9.5 - Master Shift dan Pengaturan Jadwal
-- Jalankan hanya bila user database hosting tidak memiliki izin CREATE TABLE otomatis.
-- Backup database sebelum menjalankan.

SET NAMES utf8mb4;
SET time_zone = '+07:00';

CREATE TABLE IF NOT EXISTS work_shifts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shift_code VARCHAR(40) NOT NULL,
  shift_name VARCHAR(120) NOT NULL,
  work_start TIME NOT NULL,
  work_end TIME NOT NULL,
  late_tolerance_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  work_days_json LONGTEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by_user_id BIGINT UNSIGNED NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uniq_work_shift_code (shift_code),
  INDEX idx_work_shifts_active (is_active),
  CONSTRAINT fk_work_shift_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_work_shift_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS position_shift_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  position_name VARCHAR(120) NOT NULL,
  department_name VARCHAR(120) NOT NULL DEFAULT '',
  shift_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uniq_position_shift (position_name,department_name),
  INDEX idx_position_shift_shift (shift_id),
  CONSTRAINT fk_position_shift FOREIGN KEY (shift_id) REFERENCES work_shifts(id),
  CONSTRAINT fk_position_shift_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_shift_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id BIGINT UNSIGNED NOT NULL,
  shift_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uniq_employee_shift (employee_id),
  INDEX idx_employee_shift_shift (shift_id),
  CONSTRAINT fk_employee_shift_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  CONSTRAINT fk_employee_shift FOREIGN KEY (shift_id) REFERENCES work_shifts(id),
  CONSTRAINT fk_employee_shift_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO work_shifts
  (shift_code,shift_name,work_start,work_end,late_tolerance_minutes,work_days_json,is_active)
SELECT
  'DEFAULT','Jam Kerja Default','08:00:00','17:00:00',15,'[1,2,3,4,5,6]',1
WHERE NOT EXISTS (SELECT 1 FROM work_shifts LIMIT 1);
