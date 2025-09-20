-- Create yield_visits table for Agriculture System
-- This table stores yield monitoring visit records

CREATE TABLE IF NOT EXISTS `yield_visits` (
  `visit_id` int(11) NOT NULL AUTO_INCREMENT,
  `visit_date` date NOT NULL,
  `farmer_id` varchar(50) NOT NULL,
  `visit_type` varchar(50) NOT NULL,
  `visit_purpose` varchar(100) NOT NULL,
  `commodity_type` varchar(50) NOT NULL,
  `yield_amount` decimal(10,2) NOT NULL,
  `yield_unit` varchar(20) NOT NULL,
  `land_area` decimal(8,2) DEFAULT NULL,
  `weather_condition` varchar(20) DEFAULT NULL,
  `yield_quality` varchar(20) DEFAULT NULL,
  `observations` text DEFAULT NULL,
  `recommendations` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`visit_id`),
  KEY `idx_farmer_id` (`farmer_id`),
  KEY `idx_visit_date` (`visit_date`),
  KEY `idx_commodity_type` (`commodity_type`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `fk_yield_visits_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `farmers` (`farmer_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_yield_visits_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some sample data (optional)
-- INSERT INTO `yield_visits` (`visit_date`, `farmer_id`, `visit_type`, `visit_purpose`, `commodity_type`, `yield_amount`, `yield_unit`, `land_area`, `weather_condition`, `yield_quality`, `observations`, `recommendations`, `created_by`) VALUES
-- ('2024-01-15', 'FMR20240115001', 'Monitoring', 'Yield Assessment', 'Rice', 25.50, 'sacks', 1.25, 'Sunny', 'Good', 'Rice plants are healthy and growing well', 'Continue current farming practices', 1),
-- ('2024-01-20', 'FMR20240115002', 'Follow-up', 'Input Distribution', 'Corn', 15.75, 'kg', 0.75, 'Cloudy', 'Excellent', 'Corn yield exceeded expectations', 'Consider expanding corn production', 1);
