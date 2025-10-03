<?php
// validation_functions.php - Comprehensive validation functions for NULL prevention

function validateRequired($value, $fieldName) {
    if (empty(trim($value))) {
        throw new Exception("$fieldName is required and cannot be empty.");
    }
    return trim($value);
}

function validateOptional($value, $default = '') {
    return !empty(trim($value)) ? trim($value) : $default;
}

function validateInteger($value, $fieldName, $min = null, $max = null, $default = null) {
    if (empty($value) && $default !== null) {
        return $default;
    }
    
    if (!is_numeric($value)) {
        throw new Exception("$fieldName must be a valid number.");
    }
    
    $intValue = intval($value);
    
    if ($min !== null && $intValue < $min) {
        throw new Exception("$fieldName must be at least $min.");
    }
    
    if ($max !== null && $intValue > $max) {
        throw new Exception("$fieldName cannot exceed $max.");
    }
    
    return $intValue;
}

function validateDecimal($value, $fieldName, $min = 0, $max = null) {
    if (empty($value)) {
        return null;
    }
    
    if (!is_numeric($value)) {
        throw new Exception("$fieldName must be a valid number.");
    }
    
    $floatValue = floatval($value);
    
    if ($min !== null && $floatValue < $min) {
        throw new Exception("$fieldName must be at least $min.");
    }
    
    if ($max !== null && $floatValue > $max) {
        throw new Exception("$fieldName cannot exceed $max.");
    }
    
    return $floatValue;
}

function validateDate($value, $fieldName) {
    if (empty($value)) {
        throw new Exception("$fieldName is required.");
    }
    
    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new Exception("$fieldName must be a valid date in YYYY-MM-DD format.");
    }
    
    // Check if date is reasonable (not in future, not too old)
    $currentDate = new DateTime();
    $minDate = new DateTime('1900-01-01');
    
    if ($date > $currentDate) {
        throw new Exception("$fieldName cannot be in the future.");
    }
    
    if ($date < $minDate) {
        throw new Exception("$fieldName cannot be before 1900.");
    }
    
    return $value;
}

function validateGender($value) {
    $validGenders = ['Male', 'Female'];
    if (!in_array($value, $validGenders)) {
        throw new Exception("Gender must be either 'Male' or 'Female'.");
    }
    return $value;
}

function validateCivilStatus($value) {
    $validStatuses = ['Single', 'Married', 'Widowed', 'Separated', 'Divorced'];
    if (empty($value) || !in_array($value, $validStatuses)) {
        throw new Exception("Civil status is required and must be one of: " . implode(', ', $validStatuses));
    }
    return $value;
}

function validateContactNumber($value) {
    if (empty($value)) {
        throw new Exception("Contact number is required and cannot be empty.");
    }
    
    // Remove all non-digit characters for validation
    $digits = preg_replace('/[^0-9]/', '', $value);
    
    // Check if it's a valid Philippine mobile number or landline
    if (strlen($digits) < 7 || strlen($digits) > 13) {
        throw new Exception("Contact number must be between 7-13 digits.");
    }
    
    return trim($value);
}

function validateEducationLevel($value) {
    $validLevels = ['Elementary', 'Highschool', 'College', 'Vocational', 'Graduate', 'Not Specified'];
    if (empty($value) || !in_array($value, $validLevels)) {
        throw new Exception("Education level is required and must be one of: " . implode(', ', $validLevels));
    }
    return $value;
}

function sanitizeForDatabase($value, $allowNull = false) {
    if ($allowNull && (is_null($value) || trim($value) === '')) {
        return null;
    }
    return trim($value) === '' ? '' : trim($value);
}

function validateFarmerData($postData) {
    $validated = [];
    
    // For registration, farmer_id is auto-generated and not included in validation
    
    // All other fields are REQUIRED (NOT NULL in database)
    $validated['first_name'] = validateRequired($postData['first_name'] ?? '', 'First Name');
    $validated['middle_name'] = validateRequired($postData['middle_name'] ?? '', 'Middle Name');
    $validated['last_name'] = validateRequired($postData['last_name'] ?? '', 'Last Name');
    $validated['suffix'] = validateOptional($postData['suffix'] ?? '', ''); // Suffix can be empty
    $validated['birth_date'] = validateDate($postData['birth_date'] ?? '', 'Birth Date');
    $validated['gender'] = validateGender($postData['gender'] ?? '');
    $validated['address_details'] = validateRequired($postData['address_details'] ?? '', 'Address');
    $validated['contact_number'] = validateContactNumber($postData['contact_number'] ?? '');
    $validated['other_income_source'] = validateOptional($postData['other_income_source'] ?? '', ''); // Can be empty
    
    // Integer fields - now all required
    $validated['barangay_id'] = validateInteger($postData['barangay_id'] ?? '', 'Barangay', 1);
    $validated['household_size'] = validateInteger($postData['household_size'] ?? '', 'Household Size', 1, 50);
    
    // Commodity validation - handle new array structure
    if (isset($postData['commodities']) && is_array($postData['commodities']) && !empty($postData['commodities'])) {
        // New multi-commodity structure - validate at least one commodity exists
        $hasValidCommodity = false;
        foreach ($postData['commodities'] as $commodity) {
            if (!empty($commodity['commodity_id']) && isset($commodity['years_farming'])) {
                $hasValidCommodity = true;
                break;
            }
        }
        if (!$hasValidCommodity) {
            throw new Exception('At least one commodity with valid data is required.');
        }
        // Set a placeholder for primary_commodity - will be handled in the backend
        $validated['primary_commodity'] = 1;
        
        // Land area is now handled at farmer level, not commodity level
        $validated['land_area_hectares'] = validateDecimal($postData['land_area_hectares'] ?? '', 'Land Area', 0);
        if ($validated['land_area_hectares'] === null) {
            throw new Exception("Land Area is required and cannot be empty.");
        }
        
        $validated['years_farming'] = 0; // Placeholder - handled in backend
    } else {
        // Fallback validation for old single commodity structure (backward compatibility)
        $validated['primary_commodity'] = validateInteger($postData['commodity_id'] ?? '', 'Primary Commodity', 1);
        $validated['years_farming'] = validateInteger($postData['years_farming'] ?? '', 'Years Farming', 0, 100);
        
        // Decimal field - now required
        $validated['land_area_hectares'] = validateDecimal($postData['land_area_hectares'] ?? '', 'Land Area', 0);
        if ($validated['land_area_hectares'] === null) {
            throw new Exception("Land Area is required and cannot be empty.");
        }
    }
    
    // Household info - validate civil status first
    $validated['civil_status'] = validateCivilStatus($postData['civil_status'] ?? '');
    
    // Then validate spouse name based on civil status
    if ($validated['civil_status'] === 'Married') {
        $validated['spouse_name'] = validateRequired($postData['spouse_name'] ?? '', 'Spouse Name');
    } else {
        // For non-married statuses, use N/A if empty (since DB column is NOT NULL)
        $validated['spouse_name'] = validateOptional($postData['spouse_name'] ?? '', 'N/A');
    }
    
    $validated['education_level'] = validateEducationLevel($postData['education_level'] ?? '');
    $validated['occupation'] = validateRequired($postData['occupation'] ?? '', 'Occupation');
    
    // Boolean fields
    $validated['is_member_of_4ps'] = isset($postData['is_member_of_4ps']) ? 1 : 0;
    $validated['is_ip'] = isset($postData['is_ip']) ? 1 : 0;
    $validated['is_rsbsa'] = isset($postData['is_rsbsa']) ? 1 : 0;
    $validated['is_ncfrs'] = isset($postData['is_ncfrs']) ? 1 : 0;
    $validated['is_boat'] = isset($postData['is_boat']) ? 1 : 0;
    $validated['is_fisherfolk'] = isset($postData['is_fisherfolk']) ? 1 : 0;
    
    return $validated;
}

function validateFarmerDataForEdit($postData) {
    $validated = [];
    
    // For editing, farmer_id is required
    $validated['farmer_id'] = validateRequired($postData['farmer_id'] ?? '', 'Farmer ID');
    
    // All other fields are REQUIRED (NOT NULL in database)
    $validated['first_name'] = validateRequired($postData['first_name'] ?? '', 'First Name');
    $validated['middle_name'] = validateRequired($postData['middle_name'] ?? '', 'Middle Name');
    $validated['last_name'] = validateRequired($postData['last_name'] ?? '', 'Last Name');
    $validated['suffix'] = validateOptional($postData['suffix'] ?? '', ''); // Suffix can be empty
    $validated['birth_date'] = validateDate($postData['birth_date'] ?? '', 'Birth Date');
    $validated['gender'] = validateGender($postData['gender'] ?? '');
    $validated['address_details'] = validateRequired($postData['address_details'] ?? '', 'Address');
    $validated['contact_number'] = validateContactNumber($postData['contact_number'] ?? '');
    $validated['other_income_source'] = validateOptional($postData['other_income_source'] ?? '', ''); // Can be empty
    
    // Integer fields - now all required
    $validated['barangay_id'] = validateInteger($postData['barangay_id'] ?? '', 'Barangay', 1);
    $validated['household_size'] = validateInteger($postData['household_size'] ?? '', 'Household Size', 1, 50);
    
    // Commodity validation - handle new array structure
    if (isset($postData['commodities']) && is_array($postData['commodities']) && !empty($postData['commodities'])) {
        // New multi-commodity structure - validate at least one commodity exists
        $hasValidCommodity = false;
        foreach ($postData['commodities'] as $commodity) {
            if (!empty($commodity['commodity_id']) && isset($commodity['years_farming'])) {
                $hasValidCommodity = true;
                break;
            }
        }
        if (!$hasValidCommodity) {
            throw new Exception('At least one commodity with valid data is required.');
        }
        // Set a placeholder for primary_commodity - will be handled in the backend
        $validated['primary_commodity'] = 1;
        
        // Land area is now handled at farmer level, not commodity level
        $validated['land_area_hectares'] = validateDecimal($postData['land_area_hectares'] ?? '', 'Land Area', 0);
        if ($validated['land_area_hectares'] === null) {
            throw new Exception("Land Area is required and cannot be empty.");
        }
        
        $validated['years_farming'] = 0; // Placeholder - handled in backend
    } else {
        // Fallback validation for old single commodity structure (backward compatibility)
        $validated['primary_commodity'] = validateInteger($postData['commodity_id'] ?? '', 'Primary Commodity', 1);
        $validated['years_farming'] = validateInteger($postData['years_farming'] ?? '', 'Years Farming', 0, 100);
        
        // Decimal field - now required
        $validated['land_area_hectares'] = validateDecimal($postData['land_area_hectares'] ?? '', 'Land Area', 0);
        if ($validated['land_area_hectares'] === null) {
            throw new Exception("Land Area is required and cannot be empty.");
        }
    }
    
    // Household info - validate civil status first
    $validated['civil_status'] = validateCivilStatus($postData['civil_status'] ?? '');
    
    // Then validate spouse name based on civil status
    if ($validated['civil_status'] === 'Married') {
        $validated['spouse_name'] = validateRequired($postData['spouse_name'] ?? '', 'Spouse Name');
    } else {
        // For non-married statuses, use N/A if empty (since DB column is NOT NULL)
        $validated['spouse_name'] = validateOptional($postData['spouse_name'] ?? '', 'N/A');
    }
    
    $validated['education_level'] = validateEducationLevel($postData['education_level'] ?? '');
    $validated['occupation'] = validateRequired($postData['occupation'] ?? '', 'Occupation');
    
    // Boolean fields
    $validated['is_member_of_4ps'] = isset($postData['is_member_of_4ps']) ? 1 : 0;
    $validated['is_ip'] = isset($postData['is_ip']) ? 1 : 0;
    $validated['is_rsbsa'] = isset($postData['is_rsbsa']) ? 1 : 0;
    $validated['is_ncfrs'] = isset($postData['is_ncfrs']) ? 1 : 0;
    $validated['is_boat'] = isset($postData['is_boat']) ? 1 : 0;
    $validated['is_fisherfolk'] = isset($postData['is_fisherfolk']) ? 1 : 0;
    
    return $validated;
}
?>
