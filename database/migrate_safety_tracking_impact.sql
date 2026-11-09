-- FoodRescue Bangladesh: safety, expiry alerts, QR tracking and impact reporting
-- BACK UP foodrescue_bd before importing. MySQL 8+/MariaDB compatible.
ALTER TABLE surplus_food
  ADD COLUMN IF NOT EXISTS storage_method ENUM('hot','refrigerated','room_temperature','frozen','unknown') NOT NULL DEFAULT 'unknown',
  ADD COLUMN IF NOT EXISTS storage_temp_c DECIMAL(5,2) NULL,
  ADD COLUMN IF NOT EXISTS ambient_temp_c DECIMAL(5,2) NULL,
  ADD COLUMN IF NOT EXISTS room_temp_minutes INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS batch_token CHAR(32) NULL,
  ADD COLUMN IF NOT EXISTS last_expiry_alert_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS food_safety_alert_log (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 surplus_food_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 alert_type ENUM('expiring_soon','expired','urgent_match') NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_food_user_alert (surplus_food_id,user_id,alert_type),
 CONSTRAINT fk_alert_food FOREIGN KEY(surplus_food_id) REFERENCES surplus_food(id) ON DELETE CASCADE,
 CONSTRAINT fk_alert_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

UPDATE surplus_food SET batch_token=LOWER(SUBSTRING(MD5(CONCAT(UUID(), RAND())),1,32)) WHERE batch_token IS NULL;
-- Run this line only once; if the key already exists, skip it.
ALTER TABLE surplus_food ADD UNIQUE KEY uq_surplus_food_batch_token(batch_token);
