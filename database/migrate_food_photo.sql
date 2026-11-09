-- Run once in phpMyAdmin for an existing FoodRescue database (adds optional food photo).
USE foodrescue_bd;
ALTER TABLE surplus_food ADD COLUMN photo VARCHAR(255) NULL AFTER pickup_address;
