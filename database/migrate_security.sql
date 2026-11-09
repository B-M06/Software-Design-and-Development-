-- Run once in phpMyAdmin for an existing FoodRescue database (adds login throttling).
USE foodrescue_bd;
CREATE TABLE IF NOT EXISTS login_attempts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 email VARCHAR(190) NOT NULL,
 ip VARCHAR(45) NOT NULL,
 attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_login_attempts(email,ip,attempted_at)
) ENGINE=InnoDB;
