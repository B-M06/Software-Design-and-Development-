-- Run this once in phpMyAdmin if you already imported an older FoodRescue schema.
USE foodrescue_bd;
ALTER TABLE users
  MODIFY role ENUM('admin','restaurant','event_organizer','ngo','demand_reporter','volunteer') NOT NULL;
ALTER TABLE surplus_food
  MODIFY source_type ENUM('cancelled_order','extra_batch','buffet_catering_leftover','event_leftover') NOT NULL,
  ADD COLUMN event_name VARCHAR(180) NULL AFTER source_type,
  ADD COLUMN event_type VARCHAR(100) NULL AFTER event_name,
  ADD COLUMN event_date DATETIME NULL AFTER event_type;
