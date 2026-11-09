-- FoodRescue Bangladesh: Advanced feature migration (run once on existing DB)
USE foodrescue_bd;

-- Location fields are opt-in; leave NULL until the account owner supplies coordinates.
ALTER TABLE users
  ADD COLUMN latitude DECIMAL(10,7) NULL,
  ADD COLUMN longitude DECIMAL(10,7) NULL,
  ADD COLUMN location_consent TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN organization_type VARCHAR(80) NULL,
  ADD COLUMN preferred_language ENUM('en','bn') NOT NULL DEFAULT 'en';

ALTER TABLE surplus_food
  ADD COLUMN latitude DECIMAL(10,7) NULL,
  ADD COLUMN longitude DECIMAL(10,7) NULL,
  ADD COLUMN reserved_until DATETIME NULL,
  ADD COLUMN reservation_version INT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE demand_requests
  ADD COLUMN forecast_tag ENUM('low','medium','high') NULL;

CREATE TABLE IF NOT EXISTS volunteer_profiles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL UNIQUE,
 skills VARCHAR(500) NULL,
 availability ENUM('available','busy','unavailable') NOT NULL DEFAULT 'available',
 max_distance_km DECIMAL(6,2) NOT NULL DEFAULT 10,
 completed_deliveries INT UNSIGNED NOT NULL DEFAULT 0,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_volunteer_profile_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fraud_flags (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NULL,
 food_id BIGINT UNSIGNED NULL,
 request_id BIGINT UNSIGNED NULL,
 flag_type VARCHAR(80) NOT NULL,
 severity ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
 details VARCHAR(1000) NOT NULL,
 status ENUM('open','reviewing','cleared','action_taken') NOT NULL DEFAULT 'open',
 reviewed_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reviewed_at DATETIME NULL,
 CONSTRAINT fk_fraud_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_fraud_food FOREIGN KEY(food_id) REFERENCES surplus_food(id) ON DELETE SET NULL,
 CONSTRAINT fk_fraud_request FOREIGN KEY(request_id) REFERENCES donation_requests(id) ON DELETE SET NULL,
 CONSTRAINT fk_fraud_reviewer FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_fraud_status_severity(status,severity,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS demand_forecasts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 area VARCHAR(180) NOT NULL,
 forecast_date DATE NOT NULL,
 predicted_meals INT UNSIGNED NOT NULL DEFAULT 0,
 confidence ENUM('low','medium','high') NOT NULL DEFAULT 'low',
 method VARCHAR(100) NOT NULL DEFAULT 'historical moving average',
 generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_forecast_area_date(area,forecast_date),
 INDEX idx_forecast_date(forecast_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS impact_awards (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 award_month CHAR(7) NOT NULL,
 completed_donations INT UNSIGNED NOT NULL DEFAULT 0,
 rescued_kg DECIMAL(12,2) NOT NULL DEFAULT 0,
 certificate_code CHAR(24) NOT NULL UNIQUE,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_award_user_month(user_id,award_month),
 CONSTRAINT fk_impact_award_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Indexes support common verified-user and matching queries.
CREATE INDEX idx_users_role_verified_location ON users(role,verification_status,latitude,longitude);
CREATE INDEX idx_food_location_deadline ON surplus_food(status,safety_deadline,latitude,longitude);
