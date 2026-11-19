-- SIGAP MPA v1.10.2
-- Menambahkan jadwal jam masuk/pulang berbeda untuk setiap hari dalam satu shift.
ALTER TABLE work_shifts
  ADD COLUMN daily_schedule_json LONGTEXT NULL AFTER work_days_json;

-- Contoh Sabtu (6) pulang pukul 13:00 untuk shift DEFAULT.
-- UPDATE work_shifts
-- SET daily_schedule_json = '{"1":{"work_start":"08:00","work_end":"17:00"},"2":{"work_start":"08:00","work_end":"17:00"},"3":{"work_start":"08:00","work_end":"17:00"},"4":{"work_start":"08:00","work_end":"17:00"},"5":{"work_start":"08:00","work_end":"17:00"},"6":{"work_start":"08:00","work_end":"13:00"}}'
-- WHERE shift_code='DEFAULT';
