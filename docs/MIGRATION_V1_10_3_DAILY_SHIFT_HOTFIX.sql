-- SIGAP MPA v1.10.3 - Daily Shift Database Hotfix
-- Jalankan sekali melalui phpMyAdmin bila penyimpanan jadwal per hari masih gagal.

SET @column_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'work_shifts'
    AND COLUMN_NAME = 'daily_schedule_json'
);

SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE work_shifts ADD COLUMN daily_schedule_json LONGTEXT NULL AFTER work_days_json',
  'SELECT "daily_schedule_json already exists" AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Opsional: set Sabtu 08:00-13:00 untuk shift DEFAULT.
-- UPDATE work_shifts
-- SET daily_schedule_json = '{"1":{"work_start":"08:00","work_end":"17:00"},"2":{"work_start":"08:00","work_end":"17:00"},"3":{"work_start":"08:00","work_end":"17:00"},"4":{"work_start":"08:00","work_end":"17:00"},"5":{"work_start":"08:00","work_end":"17:00"},"6":{"work_start":"08:00","work_end":"13:00"}}'
-- WHERE shift_code = 'DEFAULT';
