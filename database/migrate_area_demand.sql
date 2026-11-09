-- Run once in phpMyAdmin for an existing FoodRescue database.
USE foodrescue_bd;
ALTER TABLE demand_requests
  ADD COLUMN district VARCHAR(100) NULL AFTER area,
  ADD COLUMN upazila VARCHAR(100) NULL AFTER district,
  ADD COLUMN ward VARCHAR(80) NULL AFTER upazila,
  ADD INDEX idx_demand_district_area (district, upazila, ward);
