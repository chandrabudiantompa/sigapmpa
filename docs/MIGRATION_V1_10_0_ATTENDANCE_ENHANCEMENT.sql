ALTER TABLE employee_requests
  MODIFY COLUMN request_type ENUM('leave','permission','sick','overtime','late_permission') NOT NULL;

ALTER TABLE attendance
  MODIFY COLUMN status ENUM('present','late','late_permitted','incomplete','corrected') NOT NULL DEFAULT 'present';

ALTER TABLE attendance
  ADD COLUMN IF NOT EXISTS check_out_outside_geofence TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS check_out_reason VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS admin_note VARCHAR(1000) NULL,
  ADD COLUMN IF NOT EXISTS updated_by_user_id BIGINT UNSIGNED NULL;
