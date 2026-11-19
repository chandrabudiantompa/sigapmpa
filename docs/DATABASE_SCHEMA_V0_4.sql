-- MPA Workforce v0.4 production schema starter
-- Target: MySQL 8.0+ / MariaDB yang setara
-- Seluruh waktu final harus berasal dari server/database.

SET NAMES utf8mb4;
SET time_zone = '+07:00';

CREATE TABLE employees (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nik VARCHAR(40) NOT NULL UNIQUE,
  full_name VARCHAR(160) NOT NULL,
  position_name VARCHAR(120) NOT NULL,
  department_name VARCHAR(120) NOT NULL,
  supervisor_employee_id BIGINT UNSIGNED NULL,
  annual_leave_quota DECIMAL(5,2) NOT NULL DEFAULT 12,
  employment_status ENUM('active','inactive','terminated') NOT NULL DEFAULT 'active',
  joined_date DATE NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_employee_supervisor FOREIGN KEY (supervisor_employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id BIGINT UNSIGNED NULL,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('employee','supervisor','hr_admin','payroll_finance','super_admin') NOT NULL,
  status ENUM('active','locked','disabled') NOT NULL DEFAULT 'active',
  failed_login_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME(6) NULL,
  last_login_at DATETIME(6) NULL,
  password_changed_at DATETIME(6) NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_user_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
  INDEX idx_users_role_status (role,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE work_locations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  location_code VARCHAR(50) NOT NULL UNIQUE,
  location_name VARCHAR(160) NOT NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  radius_meters INT UNSIGNED NOT NULL DEFAULT 150,
  max_accuracy_meters INT UNSIGNED NOT NULL DEFAULT 100,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE employee_devices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id BIGINT UNSIGNED NOT NULL,
  device_uuid CHAR(36) NOT NULL,
  device_label VARCHAR(160) NULL,
  webauthn_credential_id VARBINARY(1024) NULL,
  webauthn_public_key TEXT NULL,
  signature_counter BIGINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('pending','approved','revoked') NOT NULL DEFAULT 'pending',
  approved_by_user_id BIGINT UNSIGNED NULL,
  approved_at DATETIME(6) NULL,
  last_used_at DATETIME(6) NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_device_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
  CONSTRAINT fk_device_approver FOREIGN KEY (approved_by_user_id) REFERENCES users(id),
  UNIQUE KEY uq_employee_device (employee_id,device_uuid),
  INDEX idx_device_status (employee_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendance (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id BIGINT UNSIGNED NOT NULL,
  attendance_date DATE NOT NULL,
  check_in_at DATETIME(6) NULL,
  check_out_at DATETIME(6) NULL,
  check_in_latitude DECIMAL(10,7) NULL,
  check_in_longitude DECIMAL(10,7) NULL,
  check_out_latitude DECIMAL(10,7) NULL,
  check_out_longitude DECIMAL(10,7) NULL,
  check_in_accuracy_meters DECIMAL(10,2) NULL,
  check_out_accuracy_meters DECIMAL(10,2) NULL,
  check_in_distance_meters DECIMAL(10,2) NULL,
  check_out_distance_meters DECIMAL(10,2) NULL,
  location_id BIGINT UNSIGNED NULL,
  device_id BIGINT UNSIGNED NULL,
  late_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('present','late','incomplete','corrected') NOT NULL DEFAULT 'present',
  correction_note TEXT NULL,
  corrected_by_user_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_att_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
  CONSTRAINT fk_att_location FOREIGN KEY (location_id) REFERENCES work_locations(id),
  CONSTRAINT fk_att_device FOREIGN KEY (device_id) REFERENCES employee_devices(id),
  CONSTRAINT fk_att_corrector FOREIGN KEY (corrected_by_user_id) REFERENCES users(id),
  UNIQUE KEY uq_attendance_employee_date (employee_id,attendance_date),
  INDEX idx_attendance_date (attendance_date),
  INDEX idx_attendance_late (attendance_date,late_minutes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE employee_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id BIGINT UNSIGNED NOT NULL,
  request_type ENUM('leave','permission','sick','overtime') NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  start_time TIME NULL,
  end_time TIME NULL,
  quantity DECIMAL(8,2) NOT NULL DEFAULT 0,
  reason TEXT NOT NULL,
  status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  decided_by_user_id BIGINT UNSIGNED NULL,
  decided_at DATETIME(6) NULL,
  decision_note TEXT NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_request_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
  CONSTRAINT fk_request_decider FOREIGN KEY (decided_by_user_id) REFERENCES users(id),
  INDEX idx_request_status (status,request_type),
  INDEX idx_request_employee_period (employee_id,start_date,end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_trips (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id BIGINT UNSIGNED NOT NULL,
  project_code VARCHAR(100) NULL,
  project_name VARCHAR(200) NOT NULL,
  destination VARCHAR(200) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  purpose TEXT NOT NULL,
  requested_advance DECIMAL(18,2) NOT NULL DEFAULT 0,
  estimated_expense DECIMAL(18,2) NOT NULL DEFAULT 0,
  estimated_allowance DECIMAL(18,2) NOT NULL DEFAULT 0,
  approval_status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  approved_by_user_id BIGINT UNSIGNED NULL,
  approved_at DATETIME(6) NULL,
  approval_note TEXT NULL,
  settlement_status ENUM('not_started','draft','settled') NOT NULL DEFAULT 'not_started',
  payroll_period CHAR(7) NULL,
  advance_paid DECIMAL(18,2) NOT NULL DEFAULT 0,
  actual_expense DECIMAL(18,2) NOT NULL DEFAULT 0,
  final_allowance DECIMAL(18,2) NOT NULL DEFAULT 0,
  reimbursement_payable DECIMAL(18,2) NOT NULL DEFAULT 0,
  refund_due DECIMAL(18,2) NOT NULL DEFAULT 0,
  deduct_refund_from_payroll TINYINT(1) NOT NULL DEFAULT 0,
  settlement_note TEXT NULL,
  settled_by_user_id BIGINT UNSIGNED NULL,
  settled_at DATETIME(6) NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_trip_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
  CONSTRAINT fk_trip_approver FOREIGN KEY (approved_by_user_id) REFERENCES users(id),
  CONSTRAINT fk_trip_settler FOREIGN KEY (settled_by_user_id) REFERENCES users(id),
  INDEX idx_trip_status (approval_status,settlement_status),
  INDEX idx_trip_period (start_date,end_date,payroll_period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE salary_structures (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id BIGINT UNSIGNED NOT NULL,
  effective_from DATE NOT NULL,
  effective_to DATE NULL,
  base_salary DECIMAL(18,2) NOT NULL DEFAULT 0,
  position_allowance DECIMAL(18,2) NOT NULL DEFAULT 0,
  fixed_allowance DECIMAL(18,2) NOT NULL DEFAULT 0,
  meal_transport DECIMAL(18,2) NOT NULL DEFAULT 0,
  other_income DECIMAL(18,2) NOT NULL DEFAULT 0,
  bpjs_health DECIMAL(18,2) NOT NULL DEFAULT 0,
  bpjs_employment DECIMAL(18,2) NOT NULL DEFAULT 0,
  pph21 DECIMAL(18,2) NOT NULL DEFAULT 0,
  loan_deduction DECIMAL(18,2) NOT NULL DEFAULT 0,
  other_deduction DECIMAL(18,2) NOT NULL DEFAULT 0,
  bank_name VARCHAR(100) NULL,
  bank_account_ciphertext VARBINARY(1024) NULL,
  status ENUM('draft','approved','inactive') NOT NULL DEFAULT 'draft',
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  approved_by_user_id BIGINT UNSIGNED NULL,
  approved_at DATETIME(6) NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_salary_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
  CONSTRAINT fk_salary_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id),
  CONSTRAINT fk_salary_approver FOREIGN KEY (approved_by_user_id) REFERENCES users(id),
  INDEX idx_salary_effective (employee_id,effective_from,effective_to,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payroll_periods (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  period CHAR(7) NOT NULL UNIQUE,
  status ENUM('open','review','closed') NOT NULL DEFAULT 'open',
  opened_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  closed_at DATETIME(6) NULL,
  closed_by_user_id BIGINT UNSIGNED NULL,
  CONSTRAINT fk_period_closer FOREIGN KEY (closed_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payroll_components (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payroll_period_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  business_trip_id BIGINT UNSIGNED NULL,
  project_code VARCHAR(100) NULL,
  project_name VARCHAR(200) NULL,
  category ENUM('project_bonus','project_incentive','travel_allowance','salary_adjustment','other_income','other_deduction','reimbursement') NOT NULL,
  label VARCHAR(200) NOT NULL,
  treatment ENUM('earning','deduction','reimbursement') NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  description TEXT NOT NULL,
  source ENUM('manual','business_trip','system') NOT NULL DEFAULT 'manual',
  status ENUM('draft','approved','void') NOT NULL DEFAULT 'draft',
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  approved_by_user_id BIGINT UNSIGNED NULL,
  approved_at DATETIME(6) NULL,
  voided_by_user_id BIGINT UNSIGNED NULL,
  voided_at DATETIME(6) NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_component_period FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(id),
  CONSTRAINT fk_component_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
  CONSTRAINT fk_component_trip FOREIGN KEY (business_trip_id) REFERENCES business_trips(id),
  CONSTRAINT fk_component_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id),
  CONSTRAINT fk_component_approver FOREIGN KEY (approved_by_user_id) REFERENCES users(id),
  CONSTRAINT fk_component_voider FOREIGN KEY (voided_by_user_id) REFERENCES users(id),
  INDEX idx_component_payroll (payroll_period_id,employee_id,status,treatment),
  INDEX idx_component_project (project_code,project_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payroll_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payroll_period_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  run_version INT UNSIGNED NOT NULL DEFAULT 1,
  attendance_days DECIMAL(8,2) NOT NULL DEFAULT 0,
  late_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  overtime_hours DECIMAL(8,2) NOT NULL DEFAULT 0,
  gross_income DECIMAL(18,2) NOT NULL DEFAULT 0,
  total_deductions DECIMAL(18,2) NOT NULL DEFAULT 0,
  net_salary DECIMAL(18,2) NOT NULL DEFAULT 0,
  reimbursement_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  total_transfer DECIMAL(18,2) NOT NULL DEFAULT 0,
  calculation_snapshot JSON NOT NULL,
  generated_by_user_id BIGINT UNSIGNED NOT NULL,
  generated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_run_period FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(id),
  CONSTRAINT fk_run_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
  CONSTRAINT fk_run_generator FOREIGN KEY (generated_by_user_id) REFERENCES users(id),
  UNIQUE KEY uq_run_version (payroll_period_id,employee_id,run_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payslips (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payroll_run_id BIGINT UNSIGNED NOT NULL,
  document_number VARCHAR(120) NOT NULL UNIQUE,
  status ENUM('draft','published','revoked') NOT NULL DEFAULT 'draft',
  snapshot_json JSON NOT NULL,
  published_by_user_id BIGINT UNSIGNED NULL,
  published_at DATETIME(6) NULL,
  revoked_by_user_id BIGINT UNSIGNED NULL,
  revoked_at DATETIME(6) NULL,
  revoke_reason TEXT NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_payslip_run FOREIGN KEY (payroll_run_id) REFERENCES payroll_runs(id),
  CONSTRAINT fk_payslip_publisher FOREIGN KEY (published_by_user_id) REFERENCES users(id),
  CONSTRAINT fk_payslip_revoker FOREIGN KEY (revoked_by_user_id) REFERENCES users(id),
  INDEX idx_payslip_status (status,published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_user_id BIGINT UNSIGNED NULL,
  action_name VARCHAR(160) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id VARCHAR(80) NULL,
  before_json JSON NULL,
  after_json JSON NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id),
  INDEX idx_audit_entity (entity_type,entity_id),
  INDEX idx_audit_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE security_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  employee_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(100) NOT NULL,
  severity ENUM('info','warning','danger') NOT NULL DEFAULT 'warning',
  detail TEXT NOT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_security_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_security_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
  INDEX idx_security_time (severity,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$
CREATE TRIGGER audit_logs_no_update
BEFORE UPDATE ON audit_logs
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit log tidak boleh diubah';
END$$

CREATE TRIGGER audit_logs_no_delete
BEFORE DELETE ON audit_logs
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit log tidak boleh dihapus';
END$$
DELIMITER ;

-- Added in SIGAP MPA v1.9.5: work shift settings.
-- See MIGRATION_V1_9_5_SHIFT_SETTINGS.sql for the production-safe migration.
