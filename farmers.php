<?php
session_start();
require_once 'check_session.php';
require_once 'conn.php';
require_once 'validation_functions.php';

$pageTitle = 'Farmers Management';

// Generate a legacy-style farmer ID matching existing format: FMRYYYYMMNNNN
function generateFarmerId($conn) {
    // Build prefix for current year-month
    $prefix = 'FMR' . date('Y') . str_pad(date('n'), 2, '0', STR_PAD_LEFT);

    // Find the maximum numeric suffix for this prefix
    $sql = "SELECT farmer_id FROM farmers WHERE farmer_id LIKE ? ORDER BY farmer_id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $like = $prefix . '%';
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $existing = $row['farmer_id'];
            // Extract trailing digits
            if (preg_match('/^FMR(\d{6})(\d+)$/', $existing, $m)) {
                $num = intval($m[2]);
                $next = $num + 1;
            } else {
                // If pattern doesn't match, fallback to 1
                $next = 1;
            }
        } else {
            $next = 1;
        }
    } else {
        // If prepare failed, fallback to timestamp-based numbering
        $next = time() % 100000;
    }

    // Pad next to 5 digits for consistency with existing examples
    $suffix = str_pad((string)$next, 5, '0', STR_PAD_LEFT);
    return 'FMR' . date('Y') . str_pad(date('n'), 2, '0', STR_PAD_LEFT) . $suffix;
}

// Function to handle farmer archiving
function handleFarmerArchive($conn, $farmer_id, $archive_reason = null) {
    $archive_reason = $archive_reason ?? 'Archived by admin';
    
    try {
        // Check if farmer is already archived
        $check_stmt = $conn->prepare("SELECT archived FROM farmers WHERE farmer_id = ? AND archived = 1");
        $check_stmt->bind_param("s", $farmer_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            return ['success' => false, 'message' => 'Farmer is already archived!'];
        }
        
        // Archive the farmer by updating the archived flag and reason
        $stmt = $conn->prepare("UPDATE farmers SET archived = 1, archive_reason = ? WHERE farmer_id = ?");
        $stmt->bind_param("ss", $archive_reason, $farmer_id);
        $stmt->execute();
        
        return ['success' => true, 'message' => 'Farmer archived successfully!'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error archiving farmer: ' . $e->getMessage()];
    }
}

// Function to handle farmer editing
function handleFarmerEdit($conn, $post_data) {
    try {
        // Check for required field
        if (!isset($post_data['farmer_id']) || empty($post_data['farmer_id'])) {
            throw new Exception("Farmer ID is missing from form data");
        }
        
        // Update validation function to handle edit mode
        $validated = validateFarmerDataForEdit($post_data);
        
        // Start transaction
        $conn->autocommit(false);
        
        // Update farmers table (now includes land_area_hectares)
        $stmt = $conn->prepare("UPDATE farmers SET 
            first_name = ?, middle_name = ?, last_name = ?, suffix = ?, 
            birth_date = ?, gender = ?, contact_number = ?, barangay_id = ?, 
            address_details = ?, is_member_of_4ps = ?, is_ip = ?, is_rsbsa = ?, is_ncfrs = ?, is_boat = ?, is_fisherfolk = ?, other_income_source = ?, land_area_hectares = ?
            WHERE farmer_id = ?");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare UPDATE statement: " . $conn->error);
        }
        
        $stmt->bind_param("sssssssisiiiiiisds", 
            $validated['first_name'], $validated['middle_name'], $validated['last_name'], $validated['suffix'],
            $validated['birth_date'], $validated['gender'], $validated['contact_number'], $validated['barangay_id'],
            $validated['address_details'], $validated['is_member_of_4ps'], $validated['is_ip'], $validated['is_rsbsa'], 
            $validated['is_ncfrs'], $validated['is_boat'], $validated['is_fisherfolk'], $validated['other_income_source'], 
            $validated['land_area_hectares'], $validated['farmer_id']);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute UPDATE: " . $stmt->error);
        }
        
        $farmers_updated = $stmt->affected_rows > 0;
        
        // --- Update multiple commodities ---
        $submitted_commodities = $post_data['commodities'] ?? [];
        if (!is_array($submitted_commodities) || count($submitted_commodities) === 0) {
            throw new Exception("At least one commodity is required.");
        }
        
        // Remove all existing commodities for this farmer (will re-insert)
        $conn->query("DELETE FROM farmer_commodities WHERE farmer_id = '" . $conn->real_escape_string($validated['farmer_id']) . "'");
        
        $commodity_updated = false;
        $primary_commodity_index = isset($post_data['primary_commodity_index']) ? intval($post_data['primary_commodity_index']) : 0;
        
        foreach ($submitted_commodities as $idx => $comm) {
            $is_primary = ($primary_commodity_index == $idx) ? 1 : 0;
            $commodity_id = $comm['commodity_id'] ?? '';
            $years = $comm['years_farming'] ?? 0;
            
            if (!$commodity_id) continue;
            
            $ins_stmt = $conn->prepare("INSERT INTO farmer_commodities (farmer_id, commodity_id, years_farming, is_primary) VALUES (?, ?, ?, ?)");
            $ins_stmt->bind_param("siii", $validated['farmer_id'], $commodity_id, $years, $is_primary);
            
            if (!$ins_stmt->execute()) {
                throw new Exception("Failed to update commodity data: " . $ins_stmt->error);
            }
            $commodity_updated = true;
        }
        
        // Update or insert household_info
        $household_stmt = $conn->prepare("SELECT id FROM household_info WHERE farmer_id = ?");
        if (!$household_stmt) {
            throw new Exception("Failed to prepare household SELECT: " . $conn->error);
        }
        
        $household_stmt->bind_param("s", $validated['farmer_id']);
        $household_stmt->execute();
        $household_result = $household_stmt->get_result();
        
        if ($household_result->num_rows > 0) {
            // Update existing household info
            $update_household = $conn->prepare("UPDATE household_info SET 
                civil_status = ?, spouse_name = ?, household_size = ?, 
                education_level = ?, occupation = ? 
                WHERE farmer_id = ?");
            
            if (!$update_household) {
                throw new Exception("Failed to prepare household UPDATE: " . $conn->error);
            }
            
            $update_household->bind_param("ssisss", 
                $validated['civil_status'], $validated['spouse_name'], $validated['household_size'], 
                $validated['education_level'], $validated['occupation'], $validated['farmer_id']);
            
            if (!$update_household->execute()) {
                throw new Exception("Failed to execute household UPDATE: " . $update_household->error);
            }
            $household_updated = $update_household->affected_rows > 0;
        } else {
            // Insert new household info
            $insert_household = $conn->prepare("INSERT INTO household_info 
                (farmer_id, civil_status, spouse_name, household_size, education_level, occupation) 
                VALUES (?, ?, ?, ?, ?, ?)");
            
            if (!$insert_household) {
                throw new Exception("Failed to prepare household INSERT: " . $conn->error);
            }
            
            $insert_household->bind_param("sssiss", 
                $validated['farmer_id'], $validated['civil_status'], $validated['spouse_name'], 
                $validated['household_size'], $validated['education_level'], $validated['occupation']);
            
            if (!$insert_household->execute()) {
                throw new Exception("Failed to execute household INSERT: " . $insert_household->error);
            }
            $household_updated = $insert_household->affected_rows > 0;
        }

        // --- Photo upload logic (mirroring registration) ---
        $photo_uploaded = false;
        if (isset($_FILES['farmer_photo']) && $_FILES['farmer_photo']['error'] === UPLOAD_ERR_OK) {
            $farmer_id = $validated['farmer_id'];
            $photo_dir = 'uploads/farmer_photos/';
            $original_name = basename($_FILES['farmer_photo']['name']);
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $new_filename = $farmer_id . '_' . time() . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '', $original_name);
            $target_path = $photo_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['farmer_photo']['tmp_name'], $target_path)) {
                // Insert photo record
                $ins_photo = $conn->prepare("INSERT INTO farmer_photos (farmer_id, file_path, uploaded_at) VALUES (?, ?, NOW())");
                $ins_photo->bind_param("ss", $farmer_id, $target_path);
                if ($ins_photo->execute()) {
                    $photo_uploaded = true;
                }
            }
        }

        // Commit transaction
        $conn->commit();
        $conn->autocommit(true);
        
        // Check if any data was actually updated
        if ($farmers_updated || $commodity_updated || $household_updated || $photo_uploaded) {
            return ['success' => true, 'message' => 'Farmer updated successfully!'];
        } else {
            return ['success' => false, 'message' => 'No changes were made. The farmer data may be identical to what was already saved.'];
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $conn->autocommit(true);
        return ['success' => false, 'message' => 'Error updating farmer: ' . $e->getMessage()];
    }
}

// Function to handle new farmer registration
function handleFarmerRegistration($conn, $post_data) {
    try {
        // Validate incoming data using existing validation functions
        $validated = validateFarmerData($post_data);

        // Generate a unique farmer_id if not provided
        $farmer_id = $post_data['farmer_id'] ?? null;
        if (empty($farmer_id)) {
            // Use legacy-style IDs to match existing records: FMRYYYYMM#####
            $farmer_id = generateFarmerId($conn);
        }

        // Begin transaction
        $conn->autocommit(false);

        // Insert farmer
        $stmt = $conn->prepare("INSERT INTO farmers (farmer_id, first_name, middle_name, last_name, suffix, birth_date, gender, contact_number, barangay_id, address_details, other_income_source, registration_date, is_member_of_4ps, is_ip, is_rsbsa, is_ncfrs, is_boat, is_fisherfolk, land_area_hectares) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) throw new Exception('Failed to prepare INSERT farmer: ' . $conn->error);

        $stmt->bind_param('ssssssssissiiiiisd',
            $farmer_id,
            $validated['first_name'],
            $validated['middle_name'],
            $validated['last_name'],
            $validated['suffix'],
            $validated['birth_date'],
            $validated['gender'],
            $validated['contact_number'],
            $validated['barangay_id'],
            $validated['address_details'],
            $validated['other_income_source'],
            $validated['is_member_of_4ps'],
            $validated['is_ip'],
            $validated['is_rsbsa'],
            $validated['is_ncfrs'],
            $validated['is_boat'],
            $validated['is_fisherfolk'],
            $validated['land_area_hectares']
        );

        if (!$stmt->execute()) {
            throw new Exception('Failed to insert farmer: ' . $stmt->error);
        }

        // Insert household_info if provided
        $household_stmt = $conn->prepare("INSERT INTO household_info (farmer_id, civil_status, spouse_name, household_size, education_level, occupation) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$household_stmt) throw new Exception('Failed to prepare household INSERT: ' . $conn->error);
        $household_stmt->bind_param('sssiss', $farmer_id, $validated['civil_status'], $validated['spouse_name'], $validated['household_size'], $validated['education_level'], $validated['occupation']);
        if (!$household_stmt->execute()) {
            throw new Exception('Failed to insert household info: ' . $household_stmt->error);
        }

        // Insert farmer commodities (support new array structure)
        $submitted_commodities = $post_data['commodities'] ?? [];
        if (!is_array($submitted_commodities) || count($submitted_commodities) === 0) {
            // Fallback to single commodity fields
            $primary_comm = $post_data['commodity_id'] ?? null;
            $years = $post_data['years_farming'] ?? 0;
            if ($primary_comm) {
                $ins = $conn->prepare("INSERT INTO farmer_commodities (farmer_id, commodity_id, years_farming, is_primary) VALUES (?, ?, ?, 1)");
                if ($ins) {
                    $y = is_numeric($years) ? intval($years) : 0;
                    $ins->bind_param('sii', $farmer_id, $primary_comm, $y);
                    $ins->execute();
                }
            }
        } else {
            $primary_index = isset($post_data['primary_commodity_index']) ? intval($post_data['primary_commodity_index']) : 0;
            foreach ($submitted_commodities as $idx => $comm) {
                $comm_id = $comm['commodity_id'] ?? null;
                $yrs = isset($comm['years_farming']) ? intval($comm['years_farming']) : 0;
                if (!$comm_id) continue;
                $is_primary = ($idx === $primary_index) ? 1 : 0;
                $ins = $conn->prepare("INSERT INTO farmer_commodities (farmer_id, commodity_id, years_farming, is_primary) VALUES (?, ?, ?, ?)");
                if ($ins) {
                    $ins->bind_param('siii', $farmer_id, $comm_id, $yrs, $is_primary);
                    if (!$ins->execute()) {
                        throw new Exception('Failed to insert commodity: ' . $ins->error);
                    }
                }
            }
        }

        // Handle optional photo upload
        if (isset($_FILES['farmer_photo']) && $_FILES['farmer_photo']['error'] === UPLOAD_ERR_OK) {
            $photo_dir = 'uploads/farmer_photos/';
            if (!is_dir($photo_dir)) @mkdir($photo_dir, 0755, true);
            $original_name = basename($_FILES['farmer_photo']['name']);
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $new_filename = $farmer_id . '_' . time() . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '', $original_name);
            $target_path = $photo_dir . $new_filename;
            if (move_uploaded_file($_FILES['farmer_photo']['tmp_name'], $target_path)) {
                $ins_photo = $conn->prepare("INSERT INTO farmer_photos (farmer_id, file_path, uploaded_at) VALUES (?, ?, NOW())");
                if ($ins_photo) {
                    $ins_photo->bind_param('ss', $farmer_id, $target_path);
                    $ins_photo->execute();
                }
            }
        }

        $conn->commit();
        $conn->autocommit(true);

        return ['success' => true, 'message' => 'Farmer registered successfully!', 'farmer_id' => $farmer_id];
    } catch (Exception $e) {
        if ($conn) {
            $conn->rollback();
            $conn->autocommit(true);
        }
        return ['success' => false, 'message' => 'Error registering farmer: ' . $e->getMessage()];
    }
}

// Handle farmer actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'archive':
                $result = handleFarmerArchive($conn, $_POST['farmer_id'], $_POST['archive_reason'] ?? null);
                if ($result['success']) {
                    $_SESSION['success_message'] = $result['message'];
                } else {
                    $_SESSION['error_message'] = $result['message'];
                }
                // Redirect to prevent resubmission
                header("Location: farmers.php");
                exit();
                break;
                
            case 'edit':
                $result = handleFarmerEdit($conn, $_POST);
                if ($result['success']) {
                    $_SESSION['success_message'] = $result['message'];
                } else {
                    $_SESSION['error_message'] = $result['message'];
                }
                // Redirect to prevent resubmission
                header("Location: farmers.php");
                exit();
                break;
                
            case 'register_farmer':
                $result = handleFarmerRegistration($conn, $_POST);
                if ($result['success']) {
                    $_SESSION['success_message'] = $result['message'];
                } else {
                    $_SESSION['error_message'] = $result['message'];
                }
                // Redirect to prevent resubmission
                header("Location: farmers.php");
                exit();
                break;
        }
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    switch ($_GET['action']) {
        case 'get_farmer':
            if (isset($_GET['id'])) {
                $farmer_id = $conn->real_escape_string($_GET['id']);
                $farmer_sql = "SELECT * FROM farmers WHERE farmer_id = '$farmer_id' LIMIT 1";
                $farmer_result = $conn->query($farmer_sql);
                if ($farmer_result && $farmer_result->num_rows > 0) {
                    $farmer = $farmer_result->fetch_assoc();
                    // Fetch household info for this farmer
                    $household_sql = "SELECT civil_status, spouse_name, household_size, education_level, occupation FROM household_info WHERE farmer_id = '$farmer_id' LIMIT 1";
                    $household_result = $conn->query($household_sql);
                    if ($household_result && $household_result->num_rows > 0) {
                        $household = $household_result->fetch_assoc();
                        $farmer = array_merge($farmer, $household);
                    }
                    // Fetch all commodities for this farmer
                    $commodities = [];
                    $com_sql = "SELECT commodity_id, years_farming, is_primary FROM farmer_commodities WHERE farmer_id = '$farmer_id' ORDER BY is_primary DESC, id ASC";
                    $com_result = $conn->query($com_sql);
                    if ($com_result) {
                        while ($row = $com_result->fetch_assoc()) {
                            $commodities[] = $row;
                        }
                    }
                    $farmer['commodities'] = $commodities;
                    echo json_encode(['success' => true, 'farmer' => $farmer]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Farmer not found']);
                }
                exit;
            }
            break;
        // ...existing code...
        case 'view':
            if (!isset($_GET['id'])) {
                echo json_encode(['success' => false, 'message' => 'Farmer ID is required']);
                exit;
            }
            
            try {
                $stmt = $conn->prepare("SELECT f.*, h.*, c.commodity_name, b.barangay_name,
                                      fc.years_farming,
                                      DATE_FORMAT(f.registration_date, '%M %d, %Y at %h:%i %p') as formatted_registration_date
                                      FROM farmers f 
                                      LEFT JOIN household_info h ON f.farmer_id = h.farmer_id 
                                      LEFT JOIN farmer_commodities fc ON f.farmer_id = fc.farmer_id AND fc.is_primary = 1
                                      LEFT JOIN commodities c ON fc.commodity_id = c.commodity_id 
                                      LEFT JOIN barangays b ON f.barangay_id = b.barangay_id 
                                      WHERE f.farmer_id = ?");
                
                // Fetch farmer photos separately
                $photo_stmt = $conn->prepare("SELECT file_path, uploaded_at 
                                            FROM farmer_photos 
                                            WHERE farmer_id = ? 
                                            ORDER BY uploaded_at DESC");
                $photo_stmt->bind_param("s", $_GET['id']);
                $photo_stmt->execute();
                $photo_result = $photo_stmt->get_result();
                $photos = [];
                while ($photo = $photo_result->fetch_assoc()) {
                    $photos[] = $photo;
                }
                $stmt->bind_param("s", $_GET['id']);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($farmer = $result->fetch_assoc()) {
                    $farmer['photos'] = $photos; // Add photos to farmer data
                    // Fetch recent yield records for this farmer (most recent 5)
                    $recent_yields = [];
                    $yield_stmt = $conn->prepare("SELECT ym.yield_id, ym.yield_amount, ym.unit, ym.season, ym.record_date, c.commodity_name FROM yield_monitoring ym LEFT JOIN commodities c ON ym.commodity_id = c.commodity_id WHERE ym.farmer_id = ? ORDER BY ym.record_date DESC LIMIT 3");
                    if ($yield_stmt) {
                        $yield_stmt->bind_param('s', $_GET['id']);
                        $yield_stmt->execute();
                        $yield_res = $yield_stmt->get_result();
                        while ($yr = $yield_res->fetch_assoc()) {
                            $recent_yields[] = $yr;
                        }
                        $yield_stmt->close();
                    }
                    $farmer['recent_yields'] = $recent_yields;

                    // Fetch recent input distributions for this farmer (most recent 5)
                    $recent_inputs = [];
                    $input_stmt = $conn->prepare("SELECT mdl.log_id, mdl.date_given, mdl.quantity_distributed, mdl.visitation_date, mdl.status, ic.input_name, ic.unit FROM mao_distribution_log mdl LEFT JOIN input_categories ic ON mdl.input_id = ic.input_id WHERE mdl.farmer_id = ? ORDER BY mdl.date_given DESC LIMIT 3");
                    if ($input_stmt) {
                        $input_stmt->bind_param('s', $_GET['id']);
                        $input_stmt->execute();
                        $input_res = $input_stmt->get_result();
                        while ($ir = $input_res->fetch_assoc()) {
                            $recent_inputs[] = $ir;
                        }
                        $input_stmt->close();
                    }
                    $farmer['recent_inputs'] = $recent_inputs;
                    $html = generateFarmerViewHTML($farmer);
                    echo json_encode(['success' => true, 'html' => $html]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Farmer not found']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading farmer: ' . $e->getMessage()]);
            }
            exit;
            
        case 'get_farmer':
            if (!isset($_GET['id'])) {
                echo json_encode(['success' => false, 'message' => 'Farmer ID is required']);
                exit;
            }
            
            try {
                $stmt = $conn->prepare("SELECT f.*, h.*, fc.commodity_id, fc.years_farming, c.commodity_name 
                                      FROM farmers f 
                                      LEFT JOIN household_info h ON f.farmer_id = h.farmer_id 
                                      LEFT JOIN farmer_commodities fc ON f.farmer_id = fc.farmer_id AND fc.is_primary = 1
                                      LEFT JOIN commodities c ON fc.commodity_id = c.commodity_id
                                      WHERE f.farmer_id = ?");
                $stmt->bind_param("s", $_GET['id']);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($farmer = $result->fetch_assoc()) {
                    echo json_encode(['success' => true, 'farmer' => $farmer]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Farmer not found']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading farmer: ' . $e->getMessage()]);
            }
            exit;
    }
}

// Function to generate farmer view HTML
function generateFarmerViewHTML($farmer) {
    $fullName = trim($farmer['first_name'] . ' ' . $farmer['middle_name'] . ' ' . $farmer['last_name'] . ' ' . $farmer['suffix']);
    
    $html = '<div class="container-fluid p-0">';
    
    // Header with farmer name and ID
    $html .= '<div class="bg-gradient-primary text-white p-3 rounded-top mb-3" style="background: linear-gradient(135deg, #28a745, #20c997);">';
    $html .= '<div class="row align-items-center">';
    $html .= '<div class="col">';
    $html .= '<h4 class="mb-1"><i class="fas fa-user-circle me-2"></i>' . htmlspecialchars($fullName) . '</h4>';
    $html .= '<small class="opacity-75"><i class="fas fa-id-card me-1"></i>Farmer ID: ' . htmlspecialchars($farmer['farmer_id']) . '</small>';
    $html .= '</div>';
    if (isset($farmer['formatted_registration_date'])) {
        $html .= '<div class="col-auto text-end">';
        $html .= '<small class="opacity-75"><i class="fas fa-calendar-check me-1"></i>Registered<br>' . htmlspecialchars($farmer['formatted_registration_date']) . '</small>';
        $html .= '</div>';
    }
    $html .= '</div>';
    $html .= '</div>';
    
    // Farmer Photo Section (show only the most recent photo)
    if (isset($farmer['photos']) && !empty($farmer['photos'])) {
        $latestPhoto = $farmer['photos'][0]; // photos expected ORDER BY uploaded_at DESC
        $photoPath = $latestPhoto['file_path'];
        $uploadedAt = $latestPhoto['uploaded_at'];

        $html .= '<div class="row g-3 mb-3">';
        $html .= '<div class="col-12">';
        $html .= '<div class="card border-0 shadow-sm">';
        $html .= '<div class="card-header bg-light border-0">';
        $html .= '<h6 class="card-title mb-0 text-primary"><i class="fas fa-camera me-2"></i>Farmer Photo</h6>';
        $html .= '</div>';
        $html .= '<div class="card-body">';
        $html .= '<div class="row g-2">';
        $html .= '<div class="col-12">';
        // Center the photo and constrain its size so it matches the red-box reference
        $html .= '<div class="d-flex justify-content-center">';
        $html .= '<div class="position-relative" style="display:inline-block;">';
    // Smaller, centered image with ~300x300px size and safe JS quoting for onclick
    $html .= '<img src="' . htmlspecialchars($photoPath) . '" class="img-fluid rounded shadow-sm farmer-photo" alt="Farmer Photo" style="display:block; width:300px; height:300px; object-fit:contain; cursor:pointer; background:#fff;" onclick="viewPhotoModal(\'' . addslashes($photoPath) . '\', \'" . addslashes($uploadedAt) . "\')">';
        $html .= '<div class="position-absolute bottom-0 start-0 end-0 bg-dark bg-opacity-75 text-white p-2 rounded-bottom" style="width:100%;">';
        $html .= '<small class="d-block"><i class="fas fa-calendar me-1"></i>' . date('M j, Y', strtotime($uploadedAt)) . '</small>';
        $html .= '<span class="badge bg-success ms-2">Current Photo</span>';
        $html .= '</div>';
        $html .= '</div>'; // .position-relative
        $html .= '</div>'; // .d-flex
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
    }
    
    // Information cards
    $html .= '<div class="row g-3">';
    
    // Personal Information Card
    $html .= '<div class="col-md-6">';
    $html .= '<div class="card border-0 shadow-sm h-100">';
    $html .= '<div class="card-header bg-light border-0">';
    $html .= '<h6 class="card-title mb-0 text-success"><i class="fas fa-user me-2"></i>Personal Information</h6>';
    $html .= '</div>';
    $html .= '<div class="card-body">';
    $html .= '<div class="row g-3">';
    
    $html .= '<div class="col-6">';
    $html .= '<div class="d-flex align-items-center mb-2">';
    $html .= '<i class="fas fa-birthday-cake text-muted me-2" style="width: 16px;"></i>';
    $html .= '<small class="text-muted">Birth Date</small>';
    $html .= '</div>';
    $html .= '<div class="fw-semibold">' . htmlspecialchars($farmer['birth_date'] ?: 'Not specified') . '</div>';
    $html .= '</div>';
    
    $html .= '<div class="col-6">';
    $html .= '<div class="d-flex align-items-center mb-2">';
    $html .= '<i class="fas fa-venus-mars text-muted me-2" style="width: 16px;"></i>';
    $html .= '<small class="text-muted">Gender</small>';
    $html .= '</div>';
    $html .= '<div class="fw-semibold">' . htmlspecialchars($farmer['gender'] ?: 'Not specified') . '</div>';
    $html .= '</div>';
    
    $html .= '<div class="col-12">';
    $html .= '<div class="d-flex align-items-center mb-2">';
    $html .= '<i class="fas fa-phone text-muted me-2" style="width: 16px;"></i>';
    $html .= '<small class="text-muted">Contact Number</small>';
    $html .= '</div>';
    $html .= '<div class="fw-semibold">' . htmlspecialchars($farmer['contact_number'] ?: 'Not specified') . '</div>';
    $html .= '</div>';
    
    $html .= '<div class="col-12">';
    $html .= '<div class="d-flex align-items-center mb-2">';
    $html .= '<i class="fas fa-map-marker-alt text-muted me-2" style="width: 16px;"></i>';
    $html .= '<small class="text-muted">Address</small>';
    $html .= '</div>';
    $html .= '<div class="fw-semibold">' . htmlspecialchars($farmer['address_details'] ?: 'Not specified') . ', ' . htmlspecialchars($farmer['barangay_name'] ?: 'N/A') . '</div>';
    $html .= '</div>';
    
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Household Information Card
    $html .= '<div class="col-md-6">';
    $html .= '<div class="card border-0 shadow-sm h-100">';
    $html .= '<div class="card-header bg-light border-0">';
    $html .= '<h6 class="card-title mb-0 text-info"><i class="fas fa-home me-2"></i>Household Information</h6>';
    $html .= '</div>';
    $html .= '<div class="card-body">';
    $html .= '<div class="row g-3">';
    
    $html .= '<div class="col-6">';
    $html .= '<div class="d-flex align-items-center mb-2">';
    $html .= '<i class="fas fa-heart text-muted me-2" style="width: 16px;"></i>';
    $html .= '<small class="text-muted">Civil Status</small>';
    $html .= '</div>';
    $html .= '<div class="fw-semibold">' . htmlspecialchars($farmer['civil_status'] ?: 'Not specified') . '</div>';
    $html .= '</div>';
    
    $html .= '<div class="col-6">';
    $html .= '<div class="d-flex align-items-center mb-2">';
    $html .= '<i class="fas fa-users text-muted me-2" style="width: 16px;"></i>';
    $html .= '<small class="text-muted">Household Size</small>';
    $html .= '</div>';
    $html .= '<div class="fw-semibold">' . htmlspecialchars($farmer['household_size'] ?: 'Not specified') . '</div>';
    $html .= '</div>';
    
    if (!empty($farmer['spouse_name'])) {
        $html .= '<div class="col-12">';
        $html .= '<div class="d-flex align-items-center mb-2">';
        $html .= '<i class="fas fa-ring text-muted me-2" style="width: 16px;"></i>';
        $html .= '<small class="text-muted">Spouse</small>';
        $html .= '</div>';
        $html .= '<div class="fw-semibold">' . htmlspecialchars($farmer['spouse_name']) . '</div>';
        $html .= '</div>';
    }
    
    $html .= '<div class="col-6">';
    $html .= '<div class="d-flex align-items-center mb-2">';
    $html .= '<i class="fas fa-graduation-cap text-muted me-2" style="width: 16px;"></i>';
    $html .= '<small class="text-muted">Education</small>';
    $html .= '</div>';
    $html .= '<div class="fw-semibold">' . htmlspecialchars($farmer['education_level'] ?: 'Not specified') . '</div>';
    $html .= '</div>';
    
    $html .= '<div class="col-6">';
    $html .= '<div class="d-flex align-items-center mb-2">';
    $html .= '<i class="fas fa-briefcase text-muted me-2" style="width: 16px;"></i>';
    $html .= '<small class="text-muted">Occupation</small>';
    $html .= '</div>';
    $html .= '<div class="fw-semibold">' . htmlspecialchars($farmer['occupation'] ?: 'Not specified') . '</div>';
    $html .= '</div>';
    
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    $html .= '</div>';
    
    // Farm Information Card (Full Width)
    $html .= '<div class="row g-3 mt-2">';
    $html .= '<div class="col-12">';
    $html .= '<div class="card border-0 shadow-sm">';
    $html .= '<div class="card-header bg-light border-0">';
    $html .= '<h6 class="card-title mb-0 text-warning"><i class="fas fa-seedling me-2"></i>Farm Information</h6>';
    $html .= '</div>';
    $html .= '<div class="card-body">';
    $html .= '<div class="row g-3">';
    
    $html .= '<div class="col-md-4">';
    $html .= '<div class="d-flex align-items-center mb-2">';
    $html .= '<i class="fas fa-leaf text-muted me-2" style="width: 16px;"></i>';
    $html .= '<small class="text-muted">Primary Commodity</small>';
    $html .= '</div>';
    $html .= '<div class="fw-semibold">' . htmlspecialchars($farmer['commodity_name'] ?: 'Not specified') . '</div>';
    $html .= '</div>';
    
    $html .= '<div class="col-md-4">';
    $html .= '<div class="d-flex align-items-center mb-2">';
    $html .= '<i class="fas fa-ruler-combined text-muted me-2" style="width: 16px;"></i>';
    $html .= '<small class="text-muted">Land Area</small>';
    $html .= '</div>';
    $html .= '<div class="fw-semibold">' . htmlspecialchars($farmer['land_area_hectares'] ?: 'Not specified') . ' hectares</div>';
    $html .= '</div>';
    
    $html .= '<div class="col-md-4">';
    $html .= '<div class="d-flex align-items-center mb-2">';
    $html .= '<i class="fas fa-calendar-alt text-muted me-2" style="width: 16px;"></i>';
    $html .= '<small class="text-muted">Years Farming</small>';
    $html .= '</div>';
    $html .= '<div class="fw-semibold">' . htmlspecialchars($farmer['years_farming'] ?: 'Not specified') . ' years</div>';
    $html .= '</div>';
    
    $html .= '<div class="col-md-6">';
    $html .= '<div class="d-flex align-items-center mb-2">';
    $html .= '<i class="fas fa-dollar-sign text-muted me-2" style="width: 16px;"></i>';
    $html .= '<small class="text-muted">Other Income Source</small>';
    $html .= '</div>';
    $html .= '<div class="fw-semibold">' . htmlspecialchars($farmer['other_income_source'] ?: 'None') . '</div>';
    $html .= '</div>';
    
    $html .= '<div class="col-md-6">';
    $html .= '<div class="row g-2">';
    $html .= '<div class="col-6">';
    $html .= '<div class="text-center p-2 rounded ' . ($farmer['is_member_of_4ps'] ? 'bg-success-subtle text-success' : 'bg-light text-muted') . '">';
    $html .= '<i class="fas fa-hand-holding-heart d-block mb-1"></i>';
    $html .= '<small class="fw-semibold">4Ps Member</small><br>';
    $html .= '<span class="badge ' . ($farmer['is_member_of_4ps'] ? 'bg-success' : 'bg-secondary') . '">' . ($farmer['is_member_of_4ps'] ? 'Yes' : 'No') . '</span>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="col-6">';
    $html .= '<div class="text-center p-2 rounded ' . ($farmer['is_ip'] ? 'bg-warning-subtle text-warning' : 'bg-light text-muted') . '">';
    $html .= '<i class="fas fa-mountain d-block mb-1"></i>';
    $html .= '<small class="fw-semibold">Indigenous People</small><br>';
    $html .= '<span class="badge ' . ($farmer['is_ip'] ? 'bg-warning' : 'bg-secondary') . '">' . ($farmer['is_ip'] ? 'Yes' : 'No') . '</span>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Registration Programs Section
    $html .= '<div class="row g-3 mt-2">';
    $html .= '<div class="col-12">';
    $html .= '<div class="card border-0 shadow-sm">';
    $html .= '<div class="card-header bg-light border-0">';
    $html .= '<h6 class="card-title mb-0 text-info"><i class="fas fa-clipboard-list me-2"></i>Program Registrations</h6>';
    $html .= '</div>';
    $html .= '<div class="card-body">';
    $html .= '<div class="row g-3">';
    
    // RSBSA Registration
    $html .= '<div class="col-md-6 col-lg-3">';
    $html .= '<div class="text-center p-3 rounded ' . (!empty($farmer['is_rsbsa']) ? 'bg-primary-subtle text-primary' : 'bg-light text-muted') . '">';
    $html .= '<i class="fas fa-file-contract d-block mb-2 fs-4"></i>';
    $html .= '<div class="fw-semibold mb-1">RSBSA</div>';
    $html .= '<span class="badge ' . (!empty($farmer['is_rsbsa']) ? 'bg-primary' : 'bg-secondary') . '">' . (!empty($farmer['is_rsbsa']) ? 'Registered' : 'Not Registered') . '</span>';
    $html .= '</div>';
    $html .= '</div>';
    
    // NCFRS Registration
    $html .= '<div class="col-md-6 col-lg-3">';
    $html .= '<div class="text-center p-3 rounded ' . (!empty($farmer['is_ncfrs']) ? 'bg-success-subtle text-success' : 'bg-light text-muted') . '">';
    $html .= '<i class="fas fa-database d-block mb-2 fs-4"></i>';
    $html .= '<div class="fw-semibold mb-1">NCFRS</div>';
    $html .= '<span class="badge ' . (!empty($farmer['is_ncfrs']) ? 'bg-success' : 'bg-secondary') . '">' . (!empty($farmer['is_ncfrs']) ? 'Registered' : 'Not Registered') . '</span>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Fisherfolk Registration
    $html .= '<div class="col-md-6 col-lg-3">';
    $html .= '<div class="text-center p-3 rounded ' . (!empty($farmer['is_fisherfolk']) ? 'bg-info-subtle text-info' : 'bg-light text-muted') . '">';
    $html .= '<i class="fas fa-fish d-block mb-2 fs-4"></i>';
    $html .= '<div class="fw-semibold mb-1">Fisherfolk</div>';
    $html .= '<span class="badge ' . (!empty($farmer['is_fisherfolk']) ? 'bg-info' : 'bg-secondary') . '">' . (!empty($farmer['is_fisherfolk']) ? 'Registered' : 'Not Registered') . '</span>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Vessel Information
    $html .= '<div class="col-md-6 col-lg-3">';
    $html .= '<div class="text-center p-3 rounded ' . (!empty($farmer['is_boat']) ? 'bg-warning-subtle text-warning' : 'bg-light text-muted') . '">';
    $html .= '<i class="fas fa-ship d-block mb-2 fs-4"></i>';
    $html .= '<div class="fw-semibold mb-1">Vessel</div>';
    $html .= '<span class="badge ' . (!empty($farmer['is_boat']) ? 'bg-warning' : 'bg-secondary') . '">' . (!empty($farmer['is_boat']) ? 'Has Boat' : 'No Boat') . '</span>';
    $html .= '</div>';
    $html .= '</div>';
    
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Recent Yields and Inputs Section
    $html .= '<div class="row g-3 mt-3">';

    // Recent Yields Card
    $html .= '<div class="col-md-6">';
    $html .= '<div class="card border-0 shadow-sm h-100">';
    $html .= '<div class="card-header bg-light border-0">';
    $html .= '<h6 class="card-title mb-0 text-primary"><i class="fas fa-boxes-stacked me-2"></i>Recent Yields</h6>';
    $html .= '</div>';
    $html .= '<div class="card-body">';
    if (!empty($farmer['recent_yields'])) {
        $html .= '<ul class="list-group list-group-flush">';
        foreach ($farmer['recent_yields'] as $ry) {
            $commodity = htmlspecialchars($ry['commodity_name'] ?: 'Unspecified');
            $amount = htmlspecialchars($ry['yield_amount'] ?: '');
            $unit = htmlspecialchars($ry['unit'] ?: '');
            $season = htmlspecialchars($ry['season'] ?: '');
            $date = $ry['record_date'] ? date('M d, Y', strtotime($ry['record_date'])) : '-';
            $html .= '<li class="list-group-item d-flex justify-content-between align-items-start">';
            $html .= '<div class="ms-2 me-auto">';
            $html .= '<div class="fw-semibold">' . $commodity . '</div>';
            $html .= '<small class="text-muted">' . $season . ' &middot; ' . $date . '</small>';
            $html .= '</div>';
            $html .= '<span class="badge bg-success rounded-pill">' . ($amount !== '' ? number_format($amount) . ' ' . $unit : '—') . '</span>';
            $html .= '</li>';
        }
        $html .= '</ul>';
    } else {
        $html .= '<div class="text-muted">No recent yield records found.</div>';
    }
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';

    // Recent Inputs Card
    $html .= '<div class="col-md-6">';
    $html .= '<div class="card border-0 shadow-sm h-100">';
    $html .= '<div class="card-header bg-light border-0">';
    $html .= '<h6 class="card-title mb-0 text-primary"><i class="fas fa-truck-field me-2"></i>Recent Inputs Received</h6>';
    $html .= '</div>';
    $html .= '<div class="card-body">';
    if (!empty($farmer['recent_inputs'])) {
        $html .= '<ul class="list-group list-group-flush">';
        foreach ($farmer['recent_inputs'] as $ri) {
            $inputName = htmlspecialchars($ri['input_name'] ?: 'Unspecified');
            $qty = htmlspecialchars($ri['quantity_distributed'] ?: '');
            $unit = htmlspecialchars($ri['unit'] ?: '');
            $dateGiven = $ri['date_given'] ? date('M d, Y', strtotime($ri['date_given'])) : '-';
            $status = htmlspecialchars(ucfirst($ri['status'] ?: ''));
            $html .= '<li class="list-group-item d-flex justify-content-between align-items-start">';
            $html .= '<div class="ms-2 me-auto">';
            $html .= '<div class="fw-semibold">' . $inputName . '</div>';
            $html .= '<small class="text-muted">Given: ' . $dateGiven . '</small>';
            $html .= '</div>';
            $html .= '<div class="text-end">';
            $html .= '<div class="fw-semibold">' . ($qty !== '' ? number_format($qty) . ' ' . $unit : '—') . '</div>';
            $html .= '<small class="text-muted">' . $status . '</small>';
            $html .= '</div>';
            $html .= '</li>';
        }
        $html .= '</ul>';
    } else {
        $html .= '<div class="text-muted">No recent input distributions found.</div>';
    }
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

// Toasts will be handled globally by includes/toast_flash.php

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $records_per_page;

// Get barangays for filter dropdown
$barangays_sql = "SELECT barangay_id, barangay_name FROM barangays ORDER BY barangay_name";
$barangays_result = $conn->query($barangays_sql);
$barangays = [];
while ($row = $barangays_result->fetch_assoc()) {
    $barangays[] = $row;
}

// Search and filter functionality - GET parameters take priority over POST
$search = isset($_GET['search']) ? trim($_GET['search']) : (isset($_POST['search']) ? trim($_POST['search']) : '');
$farmer_id_filter = isset($_GET['farmer_id']) ? trim($_GET['farmer_id']) : '';
$barangay_filter = isset($_GET['barangay']) ? trim($_GET['barangay']) : (isset($_POST['barangay']) ? trim($_POST['barangay']) : '');

// Handle clear all - reset search and filter
if (isset($_POST['clear_all'])) {
    $search = '';
    $farmer_id_filter = '';
    $barangay_filter = '';
}
$search_condition = 'WHERE f.archived = 0';
$search_params = [];

// Prioritize farmer_id for exact matching if available
if (!empty($farmer_id_filter)) {
    $search_condition .= " AND f.farmer_id = ?";
    $search_params[] = $farmer_id_filter;
} elseif (!empty($search)) {
    $search_condition .= " AND (f.first_name LIKE ? OR f.middle_name LIKE ? OR f.last_name LIKE ? OR f.contact_number LIKE ? OR CONCAT(f.first_name, ' ', COALESCE(f.middle_name, ''), ' ', f.last_name) LIKE ?)";
    $search_term = "%$search%";
    $search_params = [$search_term, $search_term, $search_term, $search_term, $search_term];
}

if (!empty($barangay_filter)) {
    $search_condition .= " AND f.barangay_id = ?";
    $search_params[] = $barangay_filter;
}

// Get total records for pagination
$count_sql = "SELECT COUNT(*) as total FROM farmers f $search_condition";
if (!empty($search_params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param(str_repeat('s', count($search_params)), ...$search_params);
} else {
    $count_stmt = $conn->prepare($count_sql);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get farmers data with commodity and household information from junction table
$sql = "SELECT f.*, c.commodity_name, b.barangay_name, h.civil_status, h.spouse_name, 
               h.household_size, h.education_level, h.occupation,
               GROUP_CONCAT(DISTINCT c.commodity_name SEPARATOR ', ') as commodities_info
        FROM farmers f 
    LEFT JOIN farmer_commodities fc ON f.farmer_id = fc.farmer_id
    LEFT JOIN commodities c ON fc.commodity_id = c.commodity_id
        LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
        LEFT JOIN household_info h ON f.farmer_id = h.farmer_id
        $search_condition 
    GROUP BY f.farmer_id
    ORDER BY f.registration_date DESC, f.farmer_id DESC 
    LIMIT ? OFFSET ?";

if (!empty($search_params)) {
    $stmt = $conn->prepare($sql);
    $all_params = array_merge($search_params, [$records_per_page, $offset]);
    $stmt->bind_param(str_repeat('s', count($search_params)) . 'ii', ...$all_params);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $records_per_page, $offset);
}

$stmt->execute();
$farmers = $stmt->get_result();

// Get commodities for edit form
$commodities_result = $conn->query("SELECT * FROM commodities ORDER BY commodity_name");

// Get barangays for form
$barangays_result = $conn->query("SELECT * FROM barangays ORDER BY barangay_name");
?>

<?php include 'includes/layout_start.php'; ?>
    <style>
        /* Custom dropdown styles */
        #dropdownMenu {
            display: none;
            z-index: 50;
            transition: all 0.2s ease-in-out;
            transform-origin: top right;
        }

        #dropdownMenu.show {
            display: block !important;
            z-index: 999999 !important;
            position: absolute !important;
            top: 100% !important;
            right: 0 !important;
            margin-top: 0.5rem !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4) !important;
            border: 1px solid rgba(0, 0, 0, 0.1) !important;
        }

        #dropdownArrow.rotate {
            transform: rotate(180deg);
        }

        /* Ensure dropdown is above other content */
        .relative {
            z-index: 40;
        }

        /* Edit Modal Styles - Match Registration Modal */
        .modal-lg {
            max-width: 800px;
        }

        .card-header {
            font-weight: 600;
        }

        .form-label {
            font-weight: 500;
        }

        .text-danger {
            font-weight: bold;
        }

        .alert-info {
            border-left: 4px solid #17a2b8;
        }
    </style>
    <script>
        // Function to handle dropdown toggle
        function toggleDropdown() {
            const dropdownMenu = document.getElementById('dropdownMenu');
            const dropdownArrow = document.getElementById('dropdownArrow');
            dropdownMenu.classList.toggle('show');
            dropdownArrow.classList.toggle('rotate');
        }

        // Function to handle navigation dropdown toggle
        function toggleNavigationDropdown() {
            const dropdown = document.getElementById('navigationDropdown');
            const arrow = document.getElementById('navigationArrow');
            
            dropdown.classList.toggle('hidden');
            arrow.classList.toggle('rotate-180');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userMenu = document.getElementById('userMenu');
            const dropdownMenu = document.getElementById('dropdownMenu');
            const dropdownArrow = document.getElementById('dropdownArrow');
            
            // Handle user dropdown
            if (!userMenu.contains(event.target) && dropdownMenu.classList.contains('show')) {
                dropdownMenu.classList.remove('show');
                dropdownArrow.classList.remove('rotate');
            }
            
            // Handle navigation dropdown
            const navigationButton = event.target.closest('button');
            const isNavigationButton = navigationButton && navigationButton.onclick && navigationButton.onclick.toString().includes('toggleNavigationDropdown');
            const navigationDropdown = document.getElementById('navigationDropdown');
            
            if (!isNavigationButton && navigationDropdown && !navigationDropdown.contains(event.target)) {
                navigationDropdown.classList.add('hidden');
                const navigationArrow = document.getElementById('navigationArrow');
                if (navigationArrow) {
                    navigationArrow.classList.remove('rotate-180');
                }
            }
        });
    </script>
</head>
    <!-- Toasts are emitted globally by includes/toast_flash.php -->

        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <!-- Header Section -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex flex-col lg:flex-row lg:items-center gap-6">
                    <div class="flex-1">
                        <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-users text-agri-green mr-3"></i>
                            Farmers Management
                        </h1>
                        <p class="text-gray-600 mt-2">Manage and monitor all registered farmers</p>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3 min-w-fit">
                        <div class="flex gap-3 whitespace-nowrap">
                        <button onclick="exportToPDF()" 
                                class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors flex items-center">
                            <i class="fas fa-file-pdf mr-2"></i>Export to PDF
                        </button>
                        
                        <button data-bs-toggle="modal" data-bs-target="#farmerRegistrationModal"
                                class="bg-agri-green text-white px-4 py-2 rounded-lg hover:bg-agri-dark transition-colors flex items-center">
                            <i class="fas fa-plus mr-2"></i>Add New Farmer
                        </button>
                        
                        <button data-bs-toggle="modal" data-bs-target="#geotaggingModal"
                                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors flex items-center">
                            <i class="fas fa-map-marker-alt mr-2"></i>Geo-tag Farmer
                        </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="POST" class="flex flex-col gap-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Search Farmers</label>
                            <div class="relative">
                                <input type="text" name="search" id="farmer_search" value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by name, mobile, or email..." 
                                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green"
                                       onkeyup="searchFarmersAutoSuggest(this.value)"
                                       onfocus="showFarmerSuggestions()"
                                       onblur="hideFarmerSuggestions()">
                                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                                
                                <!-- Auto-suggest dropdown -->
                                <div id="farmer_suggestions" class="absolute z-50 w-full bg-white border border-gray-300 rounded-lg shadow-lg mt-1 max-h-60 overflow-y-auto hidden">
                                    <!-- Suggestions will be populated here -->
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Barangay</label>
                            <select name="barangay" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green">
                                <option value="">All Barangays</option>
                                <?php foreach ($barangays as $barangay): ?>
                                    <option value="<?php echo $barangay['barangay_id']; ?>" 
                                            <?php echo ($barangay_filter == $barangay['barangay_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($barangay['barangay_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="bg-agri-green text-white px-6 py-2 rounded-lg hover:bg-agri-dark transition-colors">
                            <i class="fas fa-search mr-2"></i>Search
                        </button>
                        <?php if (!empty($search) || !empty($farmer_id_filter) || !empty($barangay_filter)): ?>
                            <button type="submit" name="clear_all" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                                <i class="fas fa-times mr-2"></i>Clear All
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Farmers Table -->
            <div class=Error loading farmer data"bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Farmers List 
                        <?php if (!empty($search) || !empty($farmer_id_filter) || !empty($barangay_filter)): ?>
                            <span class="text-sm font-normal text-gray-600">
                                - Filtered by: 
                                <?php if (!empty($search)): ?>
                                    Search "<?php echo htmlspecialchars($search); ?>"
                                <?php endif; ?>
                                <?php if (!empty($search) && !empty($barangay_filter)): ?> & <?php endif; ?>
                                <?php if (!empty($barangay_filter)): ?>
                                    <?php 
                                    $selected_barangay = '';
                                    foreach ($barangays as $barangay) {
                                        if ($barangay['barangay_id'] == $barangay_filter) {
                                            $selected_barangay = $barangay['barangay_name'];
                                            break;
                                        }
                                    }
                                    ?>
                                    Barangay "<?php echo htmlspecialchars($selected_barangay); ?>"
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </h3>
                </div>

                <div class="overflow-hidden">
                    <table class="w-full divide-y divide-gray-200">
                        <thead class="bg-agri-green">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-medium text-white uppercase tracking-wider w-1/4">Farmer</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-white uppercase tracking-wider w-1/6">Contact</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-white uppercase tracking-wider w-1/4">Location</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-white uppercase tracking-wider w-1/6">Commodity</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-white uppercase tracking-wider w-1/8">RegDate</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-white uppercase tracking-wider w-1/8">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($farmers->num_rows > 0): ?>
                                <?php while ($farmer = $farmers->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-3 py-4">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-8 w-8">
                                                    <div class="h-8 w-8 rounded-full bg-agri-green flex items-center justify-center">
                                                        <span class="text-white font-medium text-sm">
                                                            <?php echo strtoupper(substr($farmer['first_name'], 0, 1) . substr($farmer['last_name'], 0, 1)); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="ml-3">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php
                                                            $name = htmlspecialchars($farmer['last_name'] . ', ' . $farmer['first_name']);
                                                            if (!empty($farmer['suffix']) && strtolower($farmer['suffix']) !== 'n/a') {
                                                                $name .= ' ' . htmlspecialchars($farmer['suffix']);
                                                            }
                                                            echo $name;
                                                        ?>
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        <?php echo ucfirst($farmer['gender']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-4">
                                            <div class="text-sm text-gray-900">
                                                <?php if (!empty($farmer['contact_number'])): ?>
                                                    <div class="flex items-center"><i class="fas fa-mobile-alt mr-1 text-gray-400 text-xs"></i><span class="text-xs"><?php echo htmlspecialchars($farmer['contact_number']); ?></span></div>
                                                <?php else: ?>
                                                    <span class="text-xs text-gray-400">No contact</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-3 py-4">
                                            <div class="text-sm text-gray-900 font-medium">
                                                <?php echo htmlspecialchars($farmer['barangay_name'] ?? 'Not specified'); ?>
                                            </div>
                                            <div class="text-xs text-gray-500 break-words max-w-xs">
                                                <?php echo htmlspecialchars($farmer['address_details'] ?? ''); ?>
                                            </div>
                                        </td>
                                        <td class="px-3 py-4">
                                            <?php 
                                                if (!empty($farmer['commodities_info'])) {
                                                    $commodities = explode(', ', $farmer['commodities_info']);
                                                    echo '<div class="flex flex-col gap-1">';
                                                    foreach ($commodities as $commodity) {
                                                        echo '<div class="bg-green-100 text-green-800 rounded px-2 py-1 text-xs flex items-center"><i class="fas fa-leaf mr-1"></i>' . htmlspecialchars($commodity) . '</div>';
                                                    }
                                                    echo '</div>';
                                                } else {
                                                    echo '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-agri-light text-agri-dark">Not specified</span>';
                                                }
                                            ?>
                                        </td>
                                        <td class="px-3 py-4 text-xs text-gray-500">
                                            <?php 
                                            if (!empty($farmer['registration_date'])) {
                                                echo date('M j, Y', strtotime($farmer['registration_date'])) . '<br>';
                                                echo '<span class="text-gray-400">' . date('g:i A', strtotime($farmer['registration_date'])) . '</span>';
                                            } else {
                                                echo 'Not available';
                                            }
                                            ?>
                                        </td>
                                        <td class="px-3 py-4 text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <button onclick="viewFarmer('<?php echo $farmer['farmer_id']; ?>')" 
                                                        class="text-blue-600 hover:text-blue-900 transition-colors p-2 rounded hover:bg-blue-50" title="View">
                                                    <i class="fas fa-eye text-sm"></i>
                                                </button>
                                                <button onclick="editFarmer('<?php echo $farmer['farmer_id']; ?>')" 
                                                        class="text-agri-green hover:text-agri-dark transition-colors p-2 rounded hover:bg-green-50" title="Edit">
                                                    <i class="fas fa-edit text-sm"></i>
                                                </button>
                                                <button onclick="archiveFarmer('<?php echo $farmer['farmer_id']; ?>', '<?php echo htmlspecialchars($farmer['first_name'] . ' ' . $farmer['last_name']); ?>')" 
                                                        class="text-orange-600 hover:text-orange-900 transition-colors p-2 rounded hover:bg-orange-50" title="Archive">
                                                    <i class="fas fa-archive text-sm"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-3 py-8 text-center text-gray-500">
                                        <div class="flex flex-col items-center py-8">
                                            <i class="fas fa-users text-4xl text-gray-300 mb-4"></i>
                                            <p class="text-lg">No farmers found</p>
                                            <?php if (!empty($search)): ?>
                                                <p class="text-sm">Try adjusting your search criteria</p>
                                            <?php else: ?>
                                                <p class="text-sm">Get started by adding your first farmer</p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <?php
                    // Function to build URL parameters
                    function buildUrlParams($page, $search = '', $barangay = '') {
                        $params = "?page=$page";
                        if (!empty($search)) {
                            $params .= "&search=" . urlencode($search);
                        }
                        if (!empty($barangay)) {
                            $params .= "&barangay=" . urlencode($barangay);
                        }
                        return $params;
                    }
                    ?>
                    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-700">
                                Showing <?php echo min(($page - 1) * $records_per_page + 1, $total_records); ?> to 
                                <?php echo min($page * $records_per_page, $total_records); ?> of <?php echo $total_records; ?> results
                            </div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <?php if ($page > 1): ?>
                                    <a href="<?php echo buildUrlParams($page - 1, $search, $barangay_filter); ?>" 
                                       class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <a href="<?php echo buildUrlParams($i, $search, $barangay_filter); ?>" 
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium <?php echo $i == $page ? 'bg-agri-green text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="<?php echo buildUrlParams($page + 1, $search, $barangay_filter); ?>" 
                                       class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <!-- View Farmer Modal -->
    <div class="modal fade" id="viewFarmerModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 bg-light">
                    <h5 class="modal-title text-success"><i class="fas fa-user-circle me-2"></i>Farmer Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" id="viewFarmerContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <!-- Photo View Modal -->
    <div class="modal fade" id="photoViewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 bg-light">
                    <h5 class="modal-title text-primary"><i class="fas fa-camera me-2"></i>Farmer Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-0">
                    <img id="modalPhotoImage" src="" class="img-fluid" alt="Farmer Photo" style="max-height: 70vh;">
                    <div class="p-3 bg-light">
                        <small class="text-muted" id="modalPhotoDate"></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Archive Confirmation Modal -->
    <div class="modal fade" id="archiveConfirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Archive</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to archive farmer <strong id="farmerNameToArchive"></strong>?</p>
                    <p class="text-info"><small>The farmer will be moved to archives but all data will be preserved. You can restore them later if needed.</small></p>
                    
                    <div class="mb-3">
                        <label for="archiveReason" class="form-label">Reason for archiving (optional):</label>
                        <textarea class="form-control" id="archiveReason" name="archive_reason" rows="3" placeholder="Enter reason for archiving this farmer..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="archive">
                        <input type="hidden" name="farmer_id" id="farmerIdToArchive">
                        <input type="hidden" name="archive_reason" id="archiveReasonHidden">
                        <button type="submit" class="btn btn-warning" onclick="document.getElementById('archiveReasonHidden').value = document.getElementById('archiveReason').value;">Archive Farmer</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    
    <script>
        // Function to view farmer details
        function viewFarmer(farmerId) {
            fetch(`farmers.php?action=view&id=${farmerId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('viewFarmerContent').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('viewFarmerModal')).show();
                    } else {
                        if (window.AgriToast) { AgriToast.error('Error loading farmer details: ' + (data.message || 'Unknown error')); }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (window.AgriToast) { AgriToast.error('Error loading farmer details'); }
                });
        }

        // Listen for custom event to refresh view modal after photo update
        // Listen for custom event to refresh view modal after photo update
        document.addEventListener('refreshFarmerView', function(e) {
            if (e.detail && e.detail.farmerId) {
                // Always reload the modal content to show the latest photo
                viewFarmer(e.detail.farmerId);
            }
        });

        // Function to view photo in modal
        function viewPhotoModal(photoPath, uploadDate) {
            document.getElementById('modalPhotoImage').src = photoPath;
            document.getElementById('modalPhotoDate').textContent = 'Uploaded: ' + new Date(uploadDate).toLocaleDateString();
            new bootstrap.Modal(document.getElementById('photoViewModal')).show();
        }

        // Function to show archive confirmation
        function archiveFarmer(farmerId, farmerName) {
            document.getElementById('farmerIdToArchive').value = farmerId;
            document.getElementById('farmerNameToArchive').textContent = farmerName;
            new bootstrap.Modal(document.getElementById('archiveConfirmModal')).show();
        }

        // Function to export farmers data to PDF
        function exportToPDF() {
            const search = new URLSearchParams(window.location.search).get('search') || '';
            const barangay = new URLSearchParams(window.location.search).get('barangay') || '';
            
            let exportUrl = 'pdf_export.php?action=export_pdf';
            if (search) exportUrl += `&search=${encodeURIComponent(search)}`;
            if (barangay) exportUrl += `&barangay=${encodeURIComponent(barangay)}`;
            
            // Show loading indicator
            const exportBtn = document.querySelector('button[onclick="exportToPDF()"]');
            const originalText = exportBtn.innerHTML;
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating PDF...';
            exportBtn.disabled = true;
            
            // Open in new window for PDF download
            window.open(exportUrl, '_blank');
            
            // Reset button after delay
            setTimeout(() => {
                exportBtn.innerHTML = originalText;
                exportBtn.disabled = false;
            }, 3000);
        }

        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });

        // Auto-suggest functionality for farmer search
        function searchFarmersAutoSuggest(query) {
            const suggestions = document.getElementById('farmer_suggestions');
            
            if (!suggestions) return; // Exit if element doesn't exist

            if (query.length < 1) {
                suggestions.innerHTML = '';
                suggestions.classList.add('hidden');
                return;
            }

            // Show loading indicator
            suggestions.innerHTML = '<div class="px-3 py-2 text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Searching...</div>';
            suggestions.classList.remove('hidden');

            // Make AJAX request to get farmer suggestions
            fetch('get_farmers.php?action=search&include_archived=false&query=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.farmers && data.farmers.length > 0) {
                        let html = '';
                        data.farmers.forEach(farmer => {
                            html += `
                                <div class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b last:border-b-0 farmer-suggestion-item" 
                                     onclick="selectFarmerSuggestion('${farmer.farmer_id}', '${farmer.full_name.replace(/'/g, "\\'")}', '${farmer.contact_number}')">
                                    <div class="font-medium text-gray-900">${farmer.full_name}</div>
                                    <div class="text-sm text-gray-600">ID: ${farmer.farmer_id} | Contact: ${farmer.contact_number}</div>
                                    <div class="text-xs text-gray-500">${farmer.barangay_name}</div>
                                </div>
                            `;
                        });
                        suggestions.innerHTML = html;
                        suggestions.classList.remove('hidden');
                    } else {
                        suggestions.innerHTML = '<div class="px-3 py-2 text-gray-500">No farmers found matching your search</div>';
                        suggestions.classList.remove('hidden');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    suggestions.innerHTML = '<div class="px-3 py-2 text-red-500">Error loading suggestions</div>';
                    suggestions.classList.remove('hidden');
                });
        }

        function selectFarmerSuggestion(farmerId, farmerName, contactNumber) {
            const searchInput = document.getElementById('farmer_search');
            searchInput.value = farmerName;
            hideFarmerSuggestions();
            
            // Trigger form submission or update the URL to filter results
            const form = searchInput.closest('form');
            if (form) {
                form.submit();
            }
        }

        function showFarmerSuggestions() {
            const searchInput = document.getElementById('farmer_search');
            if (searchInput.value.length >= 1) {
                searchFarmersAutoSuggest(searchInput.value);
            }
        }

        function hideFarmerSuggestions() {
            const suggestions = document.getElementById('farmer_suggestions');
            if (suggestions) {
                setTimeout(() => {
                    suggestions.classList.add('hidden');
                }, 200); // Delay to allow click events on suggestions
            }
        }
    </script>

    <!-- Include Farmer Registration Modal -->
    <?php include 'farmer_regmodal.php'; ?>
    
    <!-- Include Farmer Edit Modal -->
    <?php include 'farmer_editmodal.php'; ?>
    
    <!-- Include Geo-tagging Modal -->
    <?php include 'geotagging_modal.php'; ?>
<?php include 'includes/notification_complete.php'; ?>
