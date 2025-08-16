<?php
require_once 'check_session.php';
require_once 'conn.php';

// Fetch barangays for the barangay select
$barangays = [];
$bq = mysqli_query($conn, "SELECT barangay_id, barangay_name FROM barangays ORDER BY barangay_name");
if ($bq) {
    while ($row = mysqli_fetch_assoc($bq)) {
        $barangays[] = $row;
    }
}

// Fetch commodities for primary commodity select (if table exists)
$commodities = [];
$cq = mysqli_query($conn, "SELECT commodity_id, commodity_name FROM commodities ORDER BY commodity_name");
if ($cq) {
    while ($row = mysqli_fetch_assoc($cq)) {
        $commodities[] = $row;
    }
}

if ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
?>
<!-- Farmer Registration Modal -->
<div class="modal fade" id="farmerRegistrationModal" tabindex="-1" aria-labelledby="farmerRegistrationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="farmerRegistrationModalLabel">
                    <i class="fas fa-user-plus me-2"></i>Register New Farmer
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="farmerRegistrationForm" method="POST" action="index.php" enctype="multipart/form-data">
                <div class="modal-body">
            <input type="hidden" name="action" value="register_farmer">
            <!-- Hidden field for farmer_id (backend may populate/generate) -->
            <input type="hidden" name="farmer_id" id="farmer_id" value="">
                    
                    <!-- Personal Information Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-user me-2"></i>Personal Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="middle_name" class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" id="middle_name" name="middle_name">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="birth_date" class="form-label">Birth Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="birth_date" name="birth_date" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <!-- Registrations: RSBSA, NCFRS, FISHERfold, Boat grouped -->
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-certificate me-2"></i>Registrations</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <!-- RSBSA -->
                                    <div class="col-md-3 mb-3">
                                        <label for="rsbsa_registered" class="form-label">RSBSA Registered? <span class="text-danger">*</span></label>
                                        <select class="form-select" id="rsbsa_registered" name="rsbsa_registered" required onchange="toggleRSBSAFields()">
                                            <option value="">Select</option>
                                            <option value="Yes">Yes</option>
                                            <option value="No">No</option>
                                        </select>
                                        <div id="rsbsa_details" style="display:none; margin-top:10px;">
                                            <div class="mb-2">
                                                <input type="text" class="form-control" id="rsbsa_id" name="rsbsa_id" placeholder="RSBSA ID">
                                            </div>
                                            <div class="mb-2">
                                                <input type="text" class="form-control" id="rsbsa_registration_number" name="rsbsa_registration_number" placeholder="RSBSA Registration Number">
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label small">Proof of Registration (optional)</label>
                                                <input type="file" class="form-control" id="proof_of_registration" name="proof_of_registration" accept="image/*,application/pdf">
                                            </div>
                                            <div class="mb-2">
                                                <select class="form-select" id="geo_reference_status" name="geo_reference_status">
                                                    <option value="">Geo-reference status</option>
                                                    <option value="Geo-referenced">Geo-referenced</option>
                                                    <option value="Not Geo-referenced">Not Geo-referenced</option>
                                                    <option value="Pending">Pending</option>
                                                </select>
                                            </div>
                                            <div class="mb-2">
                                                <input type="date" class="form-control" id="date_of_registration" name="date_of_registration" placeholder="Date of registration">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- NCFRS -->
                                    <div class="col-md-3 mb-3">
                                        <label for="ncfrs_registered" class="form-label">NCFRS Registered?</label>
                                        <select class="form-select" id="ncfrs_registered" name="ncfrs_registered" onchange="toggleNCFRSFields()">
                                            <option value="">Select</option>
                                            <option value="Yes">Yes</option>
                                            <option value="No">No</option>
                                        </select>
                                        <div id="ncfrs_details" style="display:none; margin-top:10px;">
                                            <input type="text" class="form-control" id="ncfrs_id" name="ncfrs_id" placeholder="NCFRS ID">
                                        </div>
                                    </div>

                                    <!-- FISHERfold -->
                                    <div class="col-md-3 mb-3">
                                        <label for="fisherfold_registered" class="form-label">FishR Registered?</label>
                                        <select class="form-select" id="fisherfold_registered" name="fisherfold_registered" onchange="toggleFisherfoldFields()">
                                            <option value="">Select</option>
                                            <option value="Yes">Yes</option>
                                            <option value="No">No</option>
                                        </select>
                                        <div id="fisherfold_details" style="display:none; margin-top:10px;">
                                            <input type="text" class="form-control mb-2" id="fisherfold_id" name="fisherfold_id" placeholder="Fisherfolk ID">
                                            <input type="text" class="form-control" id="membership_group" name="membership_group" placeholder="Organization">
                                        </div>
                                    </div>

                                    <!-- Boat -->
                                    <div class="col-md-3 mb-3">
                                        <label for="has_boat" class="form-label">Has Boat?</label>
                                        <select class="form-select" id="has_boat" name="has_boat" onchange="toggleBoatFields()">
                                            <option value="">Select</option>
                                            <option value="Yes">Yes</option>
                                            <option value="No">No</option>
                                        </select>
                                        <div id="boat_details" style="display:none; margin-top:10px;">
                                            <input type="text" class="form-control mb-2" id="boat_name" name="boat_name" placeholder="Boat name">
                                            <input type="text" class="form-control mb-2" id="boat_registration_no" name="boat_registration_no" placeholder="Registration #">
                                            <input type="number" step="0.1" min="0" class="form-control" id="engine_hp" name="engine_hp" placeholder="Engine HP">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-address-book me-2"></i>Contact Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="contact_number" class="form-label">Contact Number <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="contact_number" name="contact_number" required placeholder="09xxxxxxxxx">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="barangay" class="form-label">Barangay <span class="text-danger">*</span></label>
                                    <select class="form-select" id="barangay" name="barangay_id" required>
                                        <option value="">Select Barangay</option>
                                        <?php foreach ($barangays as $br): ?>
                                            <option value="<?php echo htmlspecialchars($br['barangay_id']); ?>"><?php echo htmlspecialchars($br['barangay_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="address_details" class="form-label">Full Address <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="address_details" name="address_details" rows="2" required placeholder="Complete residential address"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" value="1" id="is_member_of_4ps" name="is_member_of_4ps">
                                        <label class="form-check-label" for="is_member_of_4ps">Member of 4Ps</label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" value="1" id="is_ip" name="is_ip">
                                        <label class="form-check-label" for="is_ip">Indigenous People (IP)</label>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="other_income_source" class="form-label">Other Income Source (optional)</label>
                                <textarea class="form-control" id="other_income_source" name="other_income_source" rows="2" placeholder="e.g., fishing, vending, etc."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- RSBSA Registration Section -->
                    <!-- Household Information Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-home me-2"></i>Household Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="civil_status" class="form-label">Civil Status</label>
                                    <select class="form-select" id="civil_status" name="civil_status">
                                        <option value="">Select</option>
                                        <option value="Single">Single</option>
                                        <option value="Married">Married</option>
                                        <option value="Widowed">Widowed</option>
                                        <option value="Separated">Separated</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="spouse_name" class="form-label">Spouse Name</label>
                                    <input type="text" class="form-control" id="spouse_name" name="spouse_name">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="household_size" class="form-label">Household Size</label>
                                    <input type="number" min="0" class="form-control" id="household_size" name="household_size">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="education_level" class="form-label">Highest Education Attained</label>
                                    <input type="text" class="form-control" id="education_level" name="education_level" placeholder="e.g., High School, College">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="occupation" class="form-label">Primary Occupation</label>
                                    <input type="text" class="form-control" id="occupation" name="occupation" placeholder="e.g., Farmer, Fisherman">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Farming Details Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-tractor me-2"></i>Farming Details</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="primary_commodity" class="form-label">Primary Commodity</label>
                                    <select class="form-select" id="primary_commodity" name="primary_commodity">
                                        <option value="">Select Commodity</option>
                                        <?php foreach ($commodities as $c): ?>
                                            <option value="<?php echo htmlspecialchars($c['commodity_name']); ?>"><?php echo htmlspecialchars($c['commodity_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="land_area_hectares" class="form-label">Land Area (hectares)</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="land_area_hectares" name="land_area_hectares">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="years_farming" class="form-label">Years Farming</label>
                                    <input type="number" min="0" class="form-control" id="years_farming" name="years_farming">
                                </div>
                            </div>
                        </div>
                    </div>
                    

                    <div class="alert alert-info">
                        <small><i class="fas fa-info-circle me-1"></i>Fields marked with <span class="text-danger">*</span> are required.</small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i>Register Farmer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleRSBSAFields() {
    const rsbsaRegistered = document.getElementById('rsbsa_registered').value;
    const rsbsaDetails = document.getElementById('rsbsa_details');
    const rsbsaIdField = document.getElementById('rsbsa_id');
    
    if (rsbsaRegistered === 'Yes') {
        rsbsaDetails.style.display = 'block';
        rsbsaIdField.setAttribute('required', 'required');
    } else {
        rsbsaDetails.style.display = 'none';
        rsbsaIdField.removeAttribute('required');
        // Clear RSBSA fields when hiding
        document.getElementById('rsbsa_id').value = '';
    }
}

function toggleNCFRSFields() {
    const val = document.getElementById('ncfrs_registered').value;
    const details = document.getElementById('ncfrs_details');
    const idField = document.getElementById('ncfrs_id');
    if (val === 'Yes') {
        details.style.display = 'block';
        idField.setAttribute('required', 'required');
    } else {
        details.style.display = 'none';
        idField.removeAttribute('required');
        idField.value = '';
    }
}

function toggleFisherfoldFields() {
    const val = document.getElementById('fisherfold_registered').value;
    const details = document.getElementById('fisherfold_details');
    const idField = document.getElementById('fisherfold_id');
    if (val === 'Yes') {
        details.style.display = 'block';
        idField.setAttribute('required', 'required');
    } else {
        details.style.display = 'none';
        idField.removeAttribute('required');
        idField.value = '';
        document.getElementById('membership_group').value = '';
    }
}

function toggleBoatFields() {
    const val = document.getElementById('has_boat').value;
    const details = document.getElementById('boat_details');
    const regField = document.getElementById('boat_registration_no');
    if (val === 'Yes') {
        details.style.display = 'block';
        regField.setAttribute('required', 'required');
    } else {
        details.style.display = 'none';
        regField.removeAttribute('required');
        document.getElementById('boat_name').value = '';
        regField.value = '';
        document.getElementById('engine_hp').value = '';
    }
}

// Form validation and submission
document.getElementById('farmerRegistrationForm').addEventListener('submit', function(e) {
    const rsbsaRegistered = document.getElementById('rsbsa_registered').value;
    const rsbsaId = document.getElementById('rsbsa_id').value;
    const ncfrsRegistered = document.getElementById('ncfrs_registered') ? document.getElementById('ncfrs_registered').value : '';
    const ncfrsId = document.getElementById('ncfrs_id') ? document.getElementById('ncfrs_id').value : '';
    const fisherRegistered = document.getElementById('fisherfold_registered') ? document.getElementById('fisherfold_registered').value : '';
    const fisherId = document.getElementById('fisherfold_id') ? document.getElementById('fisherfold_id').value : '';
    const hasBoat = document.getElementById('has_boat') ? document.getElementById('has_boat').value : '';
    const boatReg = document.getElementById('boat_registration_no') ? document.getElementById('boat_registration_no').value : '';
    
    if (rsbsaRegistered === 'Yes' && !rsbsaId.trim()) {
        e.preventDefault();
        alert('RSBSA ID is required when farmer is RSBSA registered.');
        document.getElementById('rsbsa_id').focus();
        return false;
    }
    if (ncfrsRegistered === 'Yes' && !ncfrsId.trim()) {
        e.preventDefault();
        alert('NCFRS ID is required when farmer is NCFRS registered.');
        document.getElementById('ncfrs_id').focus();
        return false;
    }
    if (fisherRegistered === 'Yes' && !fisherId.trim()) {
        e.preventDefault();
        alert('Fisherfolk ID is required when farmer is FISHERfold registered.');
        document.getElementById('fisherfold_id').focus();
        return false;
    }
    if (hasBoat === 'Yes' && !boatReg.trim()) {
        e.preventDefault();
        alert('Boat registration number is required when Has Boat is Yes.');
        document.getElementById('boat_registration_no').focus();
        return false;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Registering...';
    submitBtn.disabled = true;
    
    // Re-enable button after a delay (in case of validation errors)
    setTimeout(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }, 3000);
});
</script>

<style>
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

#rsbsa_details {
    border-left: 3px solid #28a745;
    padding-left: 15px;
    margin-top: 15px;
    background-color: #f8f9fa;
    border-radius: 5px;
    padding: 15px;
}

.alert-info {
    border-left: 4px solid #17a2b8;
}
</style>