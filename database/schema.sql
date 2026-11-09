CREATE DATABASE IF NOT EXISTS foodrescue_bd CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE foodrescue_bd;
SET time_zone = '+06:00';

CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 phone VARCHAR(20) NOT NULL,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('admin','restaurant','event_organizer','ngo','demand_reporter','volunteer') NOT NULL,
 address VARCHAR(500) NOT NULL,
 verification_document VARCHAR(255) NULL,
 verification_status ENUM('pending','verified','rejected','suspended') NOT NULL DEFAULT 'pending',
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 last_login_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_users_role_status(role,verification_status)
) ENGINE=InnoDB;

CREATE TABLE surplus_food (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 restaurant_id BIGINT UNSIGNED NOT NULL,
 food_name VARCHAR(180) NOT NULL,
 category VARCHAR(80) NOT NULL,
 description TEXT NULL,
 quantity_kg DECIMAL(10,2) NOT NULL,
 source_type ENUM('cancelled_order','extra_batch','buffet_catering_leftover','event_leftover') NOT NULL,
 event_name VARCHAR(180) NULL,
 event_type VARCHAR(100) NULL,
 event_date DATETIME NULL,
 cooked_time DATETIME NULL,
 safety_deadline DATETIME NOT NULL,
 storage_condition VARCHAR(255) NOT NULL,
 allergens VARCHAR(500) NULL,
 pickup_address VARCHAR(500) NOT NULL,
 photo VARCHAR(255) NULL,
 priority_score TINYINT UNSIGNED NOT NULL DEFAULT 1,
 status ENUM('available','reserved','picked_up','delivered','completed','expired','cancelled','rejected') NOT NULL DEFAULT 'available',
 safety_declared TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_food_restaurant FOREIGN KEY(restaurant_id) REFERENCES users(id),
 INDEX idx_food_status_deadline(status,safety_deadline),
 INDEX idx_food_category(category),
 CHECK(quantity_kg > 0),
 CHECK(priority_score <= 100)
) ENGINE=InnoDB;

CREATE TABLE food_safety_checks (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 surplus_food_id BIGINT UNSIGNED NOT NULL,
 checklist_result JSON NULL,
 photo VARCHAR(255) NULL,
 reviewer_id BIGINT UNSIGNED NULL,
 result ENUM('pending','safe','unsafe','flagged') NOT NULL DEFAULT 'pending',
 reviewer_notes TEXT NULL,
 checked_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_safety_food FOREIGN KEY(surplus_food_id) REFERENCES surplus_food(id) ON DELETE CASCADE,
 CONSTRAINT fk_safety_reviewer FOREIGN KEY(reviewer_id) REFERENCES users(id),
 INDEX idx_safety_result(result)
) ENGINE=InnoDB;

CREATE TABLE demand_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 created_by BIGINT UNSIGNED NOT NULL,
 area VARCHAR(180) NOT NULL,
 district VARCHAR(100) NULL,
 upazila VARCHAR(100) NULL,
 ward VARCHAR(80) NULL,
 people_count INT UNSIGNED NOT NULL,
 required_meals INT UNSIGNED NOT NULL,
 fulfilled_meals INT UNSIGNED NOT NULL DEFAULT 0,
 needed_by DATETIME NOT NULL,
 urgency ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
 status ENUM('pending','validated','partially_fulfilled','fulfilled','rejected','cancelled') NOT NULL DEFAULT 'pending',
 notes TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_demand_creator FOREIGN KEY(created_by) REFERENCES users(id),
 INDEX idx_demand_area_status(area,status),
 INDEX idx_demand_district_area(district,upazila,ward),
 INDEX idx_demand_needed(needed_by),
 CHECK(required_meals > 0)
) ENGINE=InnoDB;

CREATE TABLE donation_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 surplus_food_id BIGINT UNSIGNED NOT NULL,
 requested_by BIGINT UNSIGNED NOT NULL,
 quantity_kg DECIMAL(10,2) NOT NULL,
 status ENUM('requested','accepted','rejected','scheduled','picked_up','in_transit','delivered','completed','cancelled') NOT NULL DEFAULT 'requested',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_request_food FOREIGN KEY(surplus_food_id) REFERENCES surplus_food(id),
 CONSTRAINT fk_request_user FOREIGN KEY(requested_by) REFERENCES users(id),
 INDEX idx_donation_status(status),
 INDEX idx_donation_food_user(surplus_food_id,requested_by)
) ENGINE=InnoDB;

CREATE TABLE deliveries (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 donation_request_id BIGINT UNSIGNED NOT NULL UNIQUE,
 volunteer_id BIGINT UNSIGNED NULL,
 status ENUM('scheduled','picked_up','in_transit','delivered','completed','cancelled') NOT NULL DEFAULT 'scheduled',
 pickup_time DATETIME NULL,
 delivery_time DATETIME NULL,
 proof_photo VARCHAR(255) NULL,
 condition_photo VARCHAR(255) NULL,
 gps_lat DECIMAL(10,7) NULL,
 gps_lng DECIMAL(10,7) NULL,
 gps_verified TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_delivery_request FOREIGN KEY(donation_request_id) REFERENCES donation_requests(id) ON DELETE CASCADE,
 CONSTRAINT fk_delivery_volunteer FOREIGN KEY(volunteer_id) REFERENCES users(id),
 INDEX idx_delivery_status(status)
) ENGINE=InnoDB;

CREATE TABLE delivery_status_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 delivery_id BIGINT UNSIGNED NOT NULL,
 changed_by BIGINT UNSIGNED NOT NULL,
 status VARCHAR(40) NOT NULL,
 note VARCHAR(500) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_history_delivery FOREIGN KEY(delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
 CONSTRAINT fk_history_user FOREIGN KEY(changed_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE distributions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 donation_request_id BIGINT UNSIGNED NOT NULL,
 recorded_by BIGINT UNSIGNED NOT NULL,
 meals_distributed INT UNSIGNED NOT NULL DEFAULT 0,
 beneficiary_count INT UNSIGNED NOT NULL DEFAULT 0,
 notes TEXT NULL,
 proof_photo VARCHAR(255) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_distribution_request FOREIGN KEY(donation_request_id) REFERENCES donation_requests(id),
 CONSTRAINT fk_distribution_user FOREIGN KEY(recorded_by) REFERENCES users(id),
 CHECK(meals_distributed >= 0),
 CHECK(beneficiary_count >= 0)
) ENGINE=InnoDB;

CREATE TABLE complaints (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reported_by BIGINT UNSIGNED NOT NULL,
 related_user_id BIGINT UNSIGNED NULL,
 surplus_food_id BIGINT UNSIGNED NULL,
 subject VARCHAR(180) NOT NULL,
 details TEXT NOT NULL,
 status ENUM('open','under_review','resolved','dismissed') NOT NULL DEFAULT 'open',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_complaint_reporter FOREIGN KEY(reported_by) REFERENCES users(id),
 CONSTRAINT fk_complaint_related_user FOREIGN KEY(related_user_id) REFERENCES users(id),
 CONSTRAINT fk_complaint_food FOREIGN KEY(surplus_food_id) REFERENCES surplus_food(id)
) ENGINE=InnoDB;

CREATE TABLE notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(180) NOT NULL,
 message TEXT NOT NULL,
 is_read TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_notification_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX idx_notification_user_read(user_id,is_read)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 actor_id BIGINT UNSIGNED NOT NULL,
 action VARCHAR(120) NOT NULL,
 entity_type VARCHAR(80) NOT NULL,
 entity_id BIGINT UNSIGNED NOT NULL,
 details TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_audit_actor FOREIGN KEY(actor_id) REFERENCES users(id),
 INDEX idx_audit_entity(entity_type,entity_id),
 INDEX idx_audit_created(created_at)
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 email VARCHAR(190) NOT NULL,
 ip VARCHAR(45) NOT NULL,
 attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_login_attempts(email,ip,attempted_at)
) ENGINE=InnoDB;
