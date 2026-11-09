-- FoodRescue Bangladesh: six new advanced modules
-- Backup foodrescue_bd first. Run once after reviewing the current schema.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS ngo_inventory (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 ngo_id BIGINT UNSIGNED NOT NULL,
 food_name VARCHAR(180) NOT NULL,
 category VARCHAR(80) NOT NULL DEFAULT 'Other',
 unit VARCHAR(30) NOT NULL DEFAULT 'kg',
 current_quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
 source_surplus_food_id BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_inventory_ngo FOREIGN KEY (ngo_id) REFERENCES users(id),
 CONSTRAINT fk_inventory_food FOREIGN KEY (source_surplus_food_id) REFERENCES surplus_food(id) ON DELETE SET NULL,
 UNIQUE KEY uq_inventory_ngo_source (ngo_id, source_surplus_food_id),
 INDEX idx_inventory_ngo_name (ngo_id, food_name),
 CHECK (current_quantity >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_transactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 inventory_id BIGINT UNSIGNED NOT NULL,
 transaction_type ENUM('received','distributed','expired','damaged','adjustment') NOT NULL,
 quantity DECIMAL(12,2) NOT NULL,
 reference_type VARCHAR(40) NULL,
 reference_id BIGINT UNSIGNED NULL,
 idempotency_key VARCHAR(120) NULL,
 notes VARCHAR(500) NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_invtx_inventory FOREIGN KEY (inventory_id) REFERENCES ngo_inventory(id) ON DELETE CASCADE,
 CONSTRAINT fk_invtx_user FOREIGN KEY (created_by) REFERENCES users(id),
 UNIQUE KEY uq_invtx_idempotency (idempotency_key),
 INDEX idx_invtx_inventory_date (inventory_id, created_at),
 CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS relief_campaigns (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(180) NOT NULL,
 description TEXT NULL,
 emergency_type VARCHAR(60) NOT NULL,
 area VARCHAR(180) NOT NULL,
 target_quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
 unit VARCHAR(30) NOT NULL DEFAULT 'kg',
 start_at DATETIME NOT NULL,
 end_at DATETIME NOT NULL,
 status ENUM('draft','active','paused','completed','cancelled') NOT NULL DEFAULT 'draft',
 created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_campaign_creator FOREIGN KEY (created_by) REFERENCES users(id),
 INDEX idx_campaign_status_dates (status,start_at,end_at),
 CHECK (target_quantity >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS campaign_participants (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 campaign_id BIGINT UNSIGNED NOT NULL,
 ngo_id BIGINT UNSIGNED NOT NULL,
 status ENUM('joined','declined','completed') NOT NULL DEFAULT 'joined',
 joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_cp_campaign FOREIGN KEY (campaign_id) REFERENCES relief_campaigns(id) ON DELETE CASCADE,
 CONSTRAINT fk_cp_ngo FOREIGN KEY (ngo_id) REFERENCES users(id),
 UNIQUE KEY uq_campaign_ngo (campaign_id,ngo_id),
 INDEX idx_cp_ngo (ngo_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS campaign_allocations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 campaign_id BIGINT UNSIGNED NOT NULL,
 surplus_food_id BIGINT UNSIGNED NOT NULL,
 allocated_quantity DECIMAL(10,2) NOT NULL,
 status ENUM('reserved','fulfilled','released','cancelled') NOT NULL DEFAULT 'reserved',
 created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_ca_campaign FOREIGN KEY (campaign_id) REFERENCES relief_campaigns(id) ON DELETE CASCADE,
 CONSTRAINT fk_ca_food FOREIGN KEY (surplus_food_id) REFERENCES surplus_food(id),
 CONSTRAINT fk_ca_user FOREIGN KEY (created_by) REFERENCES users(id),
 INDEX idx_ca_campaign_status (campaign_id,status),
 INDEX idx_ca_food_status (surplus_food_id,status),
 CHECK (allocated_quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transaction_feedback (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 delivery_id BIGINT UNSIGNED NOT NULL,
 reviewer_id BIGINT UNSIGNED NOT NULL,
 reviewee_id BIGINT UNSIGNED NOT NULL,
 rating TINYINT UNSIGNED NOT NULL,
 comment VARCHAR(1000) NULL,
 moderation_status ENUM('pending','published','hidden') NOT NULL DEFAULT 'published',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_feedback_delivery FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
 CONSTRAINT fk_feedback_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id),
 CONSTRAINT fk_feedback_reviewee FOREIGN KEY (reviewee_id) REFERENCES users(id),
 UNIQUE KEY uq_feedback_delivery_reviewer (delivery_id,reviewer_id),
 INDEX idx_feedback_reviewee (reviewee_id,moderation_status),
 CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS route_plans (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 volunteer_id BIGINT UNSIGNED NOT NULL,
 status ENUM('proposed','accepted','in_progress','completed','cancelled') NOT NULL DEFAULT 'proposed',
 planned_distance_km DECIMAL(10,2) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_route_volunteer FOREIGN KEY (volunteer_id) REFERENCES users(id),
 INDEX idx_route_volunteer_status (volunteer_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS route_stops (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 route_id BIGINT UNSIGNED NOT NULL,
 delivery_id BIGINT UNSIGNED NOT NULL,
 sequence_no INT UNSIGNED NOT NULL,
 latitude DECIMAL(10,7) NOT NULL,
 longitude DECIMAL(10,7) NOT NULL,
 status ENUM('pending','completed','skipped') NOT NULL DEFAULT 'pending',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_route_stop_plan FOREIGN KEY (route_id) REFERENCES route_plans(id) ON DELETE CASCADE,
 CONSTRAINT fk_route_stop_delivery FOREIGN KEY (delivery_id) REFERENCES deliveries(id),
 UNIQUE KEY uq_route_stop_sequence (route_id,sequence_no),
 UNIQUE KEY uq_route_stop_delivery (route_id,delivery_id),
 INDEX idx_route_stop_delivery (delivery_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS beneficiaries (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 ngo_id BIGINT UNSIGNED NOT NULL,
 display_name VARCHAR(150) NOT NULL,
 household_size INT UNSIGNED NOT NULL DEFAULT 1,
 area VARCHAR(180) NOT NULL,
 contact_phone VARCHAR(20) NULL,
 notes TEXT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_beneficiary_ngo FOREIGN KEY (ngo_id) REFERENCES users(id),
 INDEX idx_beneficiary_ngo_status (ngo_id,status),
 CHECK (household_size > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS beneficiary_needs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 beneficiary_id BIGINT UNSIGNED NOT NULL,
 food_category VARCHAR(100) NOT NULL,
 required_quantity DECIMAL(10,2) NOT NULL,
 unit VARCHAR(30) NOT NULL DEFAULT 'kg',
 priority_level ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
 status ENUM('open','partially_fulfilled','fulfilled','closed') NOT NULL DEFAULT 'open',
 assessed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_bneed_beneficiary FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(id) ON DELETE CASCADE,
 INDEX idx_bneed_beneficiary_status (beneficiary_id,status),
 CHECK (required_quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS beneficiary_distribution_links (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 beneficiary_id BIGINT UNSIGNED NOT NULL,
 distribution_id BIGINT UNSIGNED NOT NULL,
 meals_allocated INT UNSIGNED NOT NULL DEFAULT 0,
 notes VARCHAR(500) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_bdl_beneficiary FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(id),
 CONSTRAINT fk_bdl_distribution FOREIGN KEY (distribution_id) REFERENCES distributions(id) ON DELETE CASCADE,
 UNIQUE KEY uq_beneficiary_distribution (beneficiary_id,distribution_id),
 CHECK (meals_allocated >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
