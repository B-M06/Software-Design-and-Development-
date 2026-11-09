-- FoodRescue Bangladesh: additional advanced modules (additive migration)
-- Back up foodrescue_bd first. Import after schema.sql and migrate_six_new_modules.sql.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS emergency_broadcasts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(180) NOT NULL,
 description TEXT NULL,
 area_code VARCHAR(180) NOT NULL,
 urgency ENUM('low','normal','high','critical') NOT NULL DEFAULT 'high',
 target_quantity_kg DECIMAL(12,2) NOT NULL DEFAULT 0,
 status ENUM('open','paused','closed','expired') NOT NULL DEFAULT 'open',
 expires_at DATETIME NOT NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_emergency_creator FOREIGN KEY(created_by) REFERENCES users(id),
 INDEX idx_emergency_status_expiry(status,expires_at),
 INDEX idx_emergency_area(area_code),
 CHECK(target_quantity_kg >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS broadcast_responses (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 broadcast_id BIGINT UNSIGNED NOT NULL,
 organisation_id BIGINT UNSIGNED NOT NULL,
 response_status ENUM('available','accepted','unable','completed') NOT NULL DEFAULT 'available',
 available_quantity_kg DECIMAL(12,2) NOT NULL DEFAULT 0,
 message VARCHAR(1000) NULL,
 responded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_broadcast_response_broadcast FOREIGN KEY(broadcast_id) REFERENCES emergency_broadcasts(id) ON DELETE CASCADE,
 CONSTRAINT fk_broadcast_response_org FOREIGN KEY(organisation_id) REFERENCES users(id),
 UNIQUE KEY uq_broadcast_response_org(broadcast_id,organisation_id),
 CHECK(available_quantity_kg >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chain_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 delivery_id BIGINT UNSIGNED NOT NULL,
 event_type VARCHAR(50) NOT NULL,
 actor_id BIGINT UNSIGNED NOT NULL,
 event_note VARCHAR(500) NULL,
 previous_hash CHAR(64) NULL,
 event_hash CHAR(64) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_chain_delivery FOREIGN KEY(delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
 CONSTRAINT fk_chain_actor FOREIGN KEY(actor_id) REFERENCES users(id),
 INDEX idx_chain_delivery_time(delivery_id,created_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS partner_api_keys (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 partner_id BIGINT UNSIGNED NOT NULL,
 label VARCHAR(120) NOT NULL,
 key_prefix VARCHAR(16) NOT NULL,
 key_hash CHAR(64) NOT NULL,
 scopes_json JSON NOT NULL,
 expires_at DATETIME NULL,
 revoked_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 last_used_at DATETIME NULL,
 CONSTRAINT fk_partner_key_user FOREIGN KEY(partner_id) REFERENCES users(id) ON DELETE CASCADE,
 UNIQUE KEY uq_partner_key_hash(key_hash),
 INDEX idx_partner_key_partner(partner_id,revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS webhook_subscriptions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 partner_id BIGINT UNSIGNED NOT NULL,
 endpoint_url VARCHAR(1000) NOT NULL,
 event_types_json JSON NOT NULL,
 secret_hash CHAR(64) NOT NULL,
 status ENUM('active','paused','revoked') NOT NULL DEFAULT 'active',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_webhook_partner FOREIGN KEY(partner_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX idx_webhook_partner_status(partner_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS webhook_deliveries (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 subscription_id BIGINT UNSIGNED NOT NULL,
 event_uuid CHAR(36) NOT NULL,
 payload_json JSON NOT NULL,
 attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
 response_code SMALLINT UNSIGNED NULL,
 delivery_status ENUM('queued','delivered','retry','failed') NOT NULL DEFAULT 'queued',
 next_attempt_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 delivered_at DATETIME NULL,
 CONSTRAINT fk_webhook_delivery_subscription FOREIGN KEY(subscription_id) REFERENCES webhook_subscriptions(id) ON DELETE CASCADE,
 UNIQUE KEY uq_webhook_event(subscription_id,event_uuid),
 INDEX idx_webhook_retry(delivery_status,next_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS generated_reports (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 created_by BIGINT UNSIGNED NOT NULL,
 report_type VARCHAR(60) NOT NULL,
 period_start DATE NOT NULL,
 period_end DATE NOT NULL,
 file_path VARCHAR(255) NULL,
 status ENUM('generated','failed') NOT NULL DEFAULT 'generated',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_report_creator FOREIGN KEY(created_by) REFERENCES users(id),
 INDEX idx_generated_report_owner(created_by,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS partner_api_request_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 api_key_id BIGINT UNSIGNED NOT NULL,
 endpoint VARCHAR(180) NOT NULL,
 http_status SMALLINT UNSIGNED NOT NULL,
 requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_partner_api_log_key FOREIGN KEY(api_key_id) REFERENCES partner_api_keys(id) ON DELETE CASCADE,
 INDEX idx_partner_api_rate(api_key_id,requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
