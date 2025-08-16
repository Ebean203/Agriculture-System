<?php
// Fixed farmer registration backend code

// Add this at the beginning of the farmer registration block after line 7
if (isset($_POST['action']) && $_POST['action'] == 'register_farmer') {
    // Start transaction
    mysqli_autocommit($conn, FALSE);
    
    try {
        // ... existing code ...
        
        // Add missing table insertions:
        
        // 1. NCFRS Registration
        if ($ncfrs_registered == 'Yes' && !empty($ncfrs_id)) {
            $ncfrs_query = "INSERT INTO ncfrs_registered_farmers (farmer_id, ncfrs_registration_number) 
                           VALUES ('$farmer_id', '$ncfrs_id')";
            $ncfrs_result = mysqli_query($conn, $ncfrs_query);
            if (!$ncfrs_result) {
                throw new Exception("Error inserting NCFRS registration: " . mysqli_error($conn));
            }
        }
        
        // 2. Fisherfolk Registration
        if ($fisherfold_registered == 'Yes' && !empty($fisherfold_id)) {
            $fisherfolk_query = "INSERT INTO fisherfolk_registered_farmers (farmer_id, fisherfolk_registration_number) 
                                VALUES ('$farmer_id', '$fisherfold_id')";
            $fisherfolk_result = mysqli_query($conn, $fisherfolk_query);
            if (!$fisherfolk_result) {
                throw new Exception("Error inserting Fisherfolk registration: " . mysqli_error($conn));
            }
        }
        
        // 3. Boat Registration
        if ($has_boat == 'Yes' && !empty($boat_name)) {
            $boat_type = 'Fishing Boat'; // Default type
            $boat_query = "INSERT INTO boats (farmer_id, boat_name, boat_type, registration_number) 
                          VALUES ('$farmer_id', '$boat_name', '$boat_type', " . 
                          ($boat_registration_no ? "'$boat_registration_no'" : "NULL") . ")";
            $boat_result = mysqli_query($conn, $boat_query);
            if (!$boat_result) {
                throw new Exception("Error inserting boat information: " . mysqli_error($conn));
            }
        }
        
        // Fix RSBSA insertion to handle the missing rsbsa_id field
        // Update the existing RSBSA block to use rsbsa_input_id instead of rsbsa_id
        
        // ... rest of existing code ...
        
        // Commit transaction at the end
        mysqli_commit($conn);
        $success_message = "Farmer registered successfully! Farmer ID: " . $farmer_id;
        
    } catch (Exception $e) {
        // Rollback transaction
        mysqli_rollback($conn);
        $error_message = $e->getMessage();
    } finally {
        // Restore autocommit
        mysqli_autocommit($conn, TRUE);
    }
}
