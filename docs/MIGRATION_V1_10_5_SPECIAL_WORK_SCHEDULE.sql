-- SIGAP MPA v1.10.5 - Jadwal Khusus / Tukar Hari Kerja
CREATE TABLE IF NOT EXISTS employee_schedule_exceptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id BIGINT UNSIGNED NOT NULL,
  work_date DATE NOT NULL,
  schedule_type ENUM('work','off') NOT NULL,
  work_start TIME NULL,
  work_end TIME NULL,
  replacement_date DATE NULL,
  reason VARCHAR(500) NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uniq_employee_exception_date (employee_id,work_date),
  INDEX idx_exception_date (work_date),
  CONSTRAINT fk_exception_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  CONSTRAINT fk_exception_user FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
