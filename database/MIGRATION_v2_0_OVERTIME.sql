-- SIGAP MPA v2.0.0 - Modul Lembur
-- Jalankan di phpMyAdmin jika ingin membuat tabel manual.
-- API juga membuat tabel ini otomatis saat menu Lembur dibuka.

CREATE TABLE IF NOT EXISTS overtime_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  position_name VARCHAR(120) NOT NULL,
  department_name VARCHAR(120) NOT NULL DEFAULT '',
  eligible TINYINT(1) NOT NULL DEFAULT 1,
  rate_per_hour DECIMAL(15,2) NOT NULL DEFAULT 0,
  max_hours_per_day DECIMAL(6,2) NOT NULL DEFAULT 3.00,
  requires_approval TINYINT(1) NOT NULL DEFAULT 1,
  payroll_treatment VARCHAR(20) NOT NULL DEFAULT 'earning',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uniq_overtime_rule_position_department (position_name, department_name),
  INDEX idx_overtime_rules_active (is_active),
  INDEX idx_overtime_rules_position (position_name, department_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS overtime_approval_matrix (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  position_name VARCHAR(120) NOT NULL,
  department_name VARCHAR(120) NOT NULL DEFAULT '',
  approval1_position_name VARCHAR(120) NULL,
  approval1_department_name VARCHAR(120) NOT NULL DEFAULT '',
  approval2_position_name VARCHAR(120) NULL,
  approval2_department_name VARCHAR(120) NOT NULL DEFAULT '',
  hr_validator_role VARCHAR(40) NOT NULL DEFAULT 'hr_admin',
  payroll_validator_role VARCHAR(40) NOT NULL DEFAULT 'payroll_finance',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uniq_overtime_matrix_position_department (position_name, department_name),
  INDEX idx_overtime_matrix_active (is_active),
  INDEX idx_overtime_matrix_approval1 (approval1_position_name, approval1_department_name),
  INDEX idx_overtime_matrix_approval2 (approval2_position_name, approval2_department_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS overtime_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id BIGINT UNSIGNED NOT NULL,
  work_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  break_minutes INT NOT NULL DEFAULT 0,
  requested_hours DECIMAL(6,2) NOT NULL DEFAULT 0,
  approved_hours DECIMAL(6,2) NULL,
  rate_per_hour DECIMAL(15,2) NOT NULL DEFAULT 0,
  amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  project_code VARCHAR(100) NULL,
  project_name VARCHAR(200) NULL,
  reason TEXT NOT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'pending_approval',
  approval1_by_user_id BIGINT UNSIGNED NULL,
  approval1_at DATETIME(6) NULL,
  approval1_note TEXT NULL,
  approval2_by_user_id BIGINT UNSIGNED NULL,
  approval2_at DATETIME(6) NULL,
  approval2_note TEXT NULL,
  hr_validated_by_user_id BIGINT UNSIGNED NULL,
  hr_validated_at DATETIME(6) NULL,
  hr_note TEXT NULL,
  payroll_period VARCHAR(7) NULL,
  payroll_note TEXT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  INDEX idx_overtime_requests_employee_date (employee_id, work_date),
  INDEX idx_overtime_requests_status (status),
  INDEX idx_overtime_requests_period (payroll_period),
  INDEX idx_overtime_requests_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
