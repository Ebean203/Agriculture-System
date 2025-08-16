-- Fix missing columns in farmers table
ALTER TABLE `farmers` 
ADD COLUMN `primary_commodity` INT(11) DEFAULT NULL,
ADD COLUMN `land_area_hectares` DECIMAL(10,2) DEFAULT NULL,
ADD COLUMN `years_farming` INT(11) DEFAULT NULL;

-- Add foreign key constraint for primary_commodity
ALTER TABLE `farmers` 
ADD CONSTRAINT `fk_farmers_primary_commodity` 
FOREIGN KEY (`primary_commodity`) REFERENCES `commodities`(`commodity_id`) 
ON DELETE SET NULL ON UPDATE CASCADE;

-- Fix RSBSA table to handle both rsbsa_id input and registration number
ALTER TABLE `rsbsa_registered_farmers` 
ADD COLUMN `rsbsa_input_id` VARCHAR(100) DEFAULT NULL;

-- Update activity_logs action_type enum to include farmer_registration
ALTER TABLE `activity_logs` 
MODIFY COLUMN `action_type` ENUM('login','farmer','rsbsa','yield','commodity','input','farmer_registration') NOT NULL;
