-- Run once in phpMyAdmin for an existing FoodRescue database (adds verification documents).
USE foodrescue_bd;
ALTER TABLE users ADD COLUMN verification_document VARCHAR(255) NULL AFTER address;
