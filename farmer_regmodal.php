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
            <form id="farmerRegistrationForm" method="POST" action="farmers.php" enctype="multipart/form-data">
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
                                <div class="col-md-3 mb-3">
                                    <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="middle_name" class="form-label">Middle Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="middle_name" name="middle_name" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="suffix" class="form-label">Suffix <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="suffix" name="suffix" placeholder="Jr., Sr., III, etc." required>
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
                                                <input type="text" class="form-control" id="rsbsa_registration_number" name="rsbsa_registration_number" placeholder="RSBSA Registration Number">
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
                                        <label for="ncfrs_registered" class="form-label">NCFRS Registered? <span class="text-danger">*</span></label>
                                        <select class="form-select" id="ncfrs_registered" name="ncfrs_registered" required onchange="toggleNCFRSFields()">
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
                                        <label for="fisherfold_registered" class="form-label">FishR Registered? <span class="text-danger">*</span></label>
                                        <select class="form-select" id="fisherfold_registered" name="fisherfold_registered" required onchange="toggleFisherfoldFields()">
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
                                        <label for="has_boat" class="form-label">Has Boat? <span class="text-danger">*</span></label>
                                        <select class="form-select" id="has_boat" name="has_boat" required onchange="toggleBoatFields()">
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
                                <label for="other_income_source" class="form-label">Other Income Source <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="other_income_source" name="other_income_source" rows="2" placeholder="e.g., fishing, vending, etc." required></textarea>
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
                                    <label for="civil_status" class="form-label">Civil Status <span class="text-danger">*</span></label>
                                    <select class="form-select" id="civil_status" name="civil_status" required onchange="toggleSpouseField()">
                                        <option value="">Select</option>
                                        <option value="Single">Single</option>
                                        <option value="Married">Married</option>
                                        <option value="Widowed">Widowed</option>
                                        <option value="Separated">Separated</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3" id="spouse_field">
                                    <label for="spouse_name" class="form-label">Spouse Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="spouse_name" name="spouse_name" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="household_size" class="form-label">Household Size</label>
                                    <input type="number" min="0" class="form-control" id="household_size" name="household_size">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="education_level" class="form-label">Highest Education Attained <span class="text-danger">*</span></label>
                                    <select class="form-select" id="education_level" name="education_level" required>
                                        <option value="">Select</option>
                                        <option value="Elementary">Elementary</option>
                                        <option value="High School">High School</option>
                                        <option value="College">College</option>
                                        <option value="Vocational">Vocational</option>
                                        <option value="Graduate">Graduate</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="occupation" class="form-label">Primary Occupation <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="occupation" name="occupation" placeholder="e.g., Farmer, Fisherman" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Farming Details Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="fas fa-tractor me-2"></i>Farming Details</h6>
                            <button type="button" class="btn btn-sm btn-success" id="addCommodityBtn">
                                <i class="fas fa-plus me-1"></i>Add Commodity
                            </button>
                        </div>
                        <div class="card-body">
                            <!-- Column Headers -->
                            <div class="row mb-2 border-bottom pb-2">
                                <div class="col-md-4">
                                    <strong>Commodity</strong>
                                </div>
                                <div class="col-md-2 text-center">
                                    <strong>Land Area (ha)</strong>
                                </div>
                                <div class="col-md-2 text-center">
                                    <strong>Years Experience</strong>
                                </div>
                                <div class="col-md-2 text-center">
                                    <strong>Primary</strong>
                                </div>
                                <div class="col-md-2 text-center">
                                    <strong>Action</strong>
                                </div>
                            </div>
                            
                            <div id="commoditiesContainer">
                                <!-- Primary commodity row (required) -->
                                <div class="commodity-row mb-3 p-3 border rounded bg-light" data-commodity-index="0">
                                    <div class="row align-items-end">
                                        <div class="col-md-4 mb-3">
                                            <select class="form-select commodity-select" name="commodities[0][commodity_id]" required>
                                                <option value="">Select Commodity</option>
                                                <?php foreach ($commodities as $c): ?>
                                                    <option value="<?php echo htmlspecialchars($c['commodity_id']); ?>"><?php echo htmlspecialchars($c['commodity_name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2 mb-3">
                                            <input type="number" step="0.01" min="0" class="form-control text-center" name="commodities[0][land_area_hectares]" placeholder="0.00" required>
                                        </div>
                                        <div class="col-md-2 mb-3">
                                            <input type="number" min="0" max="100" class="form-control text-center" name="commodities[0][years_farming]" placeholder="0" required>
                                        </div>
                                        <div class="col-md-2 mb-3">
                                            <div class="form-check mt-2 text-center">
                                                <input class="form-check-input primary-commodity-radio" type="radio" name="primary_commodity_index" value="0" checked required>
                                                <label class="form-check-label text-success fw-bold d-block">
                                                    <i class="fas fa-star me-1"></i>Primary
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-2 mb-3">
                                            <div class="mt-2 text-center">
                                                <span class="text-muted small">
                                                    <i class="fas fa-lock me-1"></i>Primary
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="alert alert-info py-2 mb-0">
                                                <small>
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    This is the primary commodity and cannot be removed. At least one commodity is required.
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-success mt-3">
                                <small>
                                    <i class="fas fa-lightbulb me-1"></i>
                                    <strong>Tip:</strong> You can add multiple commodities if the farmer grows different crops. Select which one is the primary commodity for reporting purposes.
                                </small>
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
    const rsbsaRegistrationField = document.getElementById('rsbsa_registration_number');
    
    if (rsbsaRegistered === 'Yes') {
        rsbsaDetails.style.display = 'block';
        rsbsaRegistrationField.setAttribute('required', 'required');
    } else {
        rsbsaDetails.style.display = 'none';
        rsbsaRegistrationField.removeAttribute('required');
        // Clear RSBSA fields when hiding
        document.getElementById('rsbsa_registration_number').value = '';
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

function toggleSpouseField() {
    const civilStatus = document.getElementById('civil_status').value;
    const spouseField = document.getElementById('spouse_field');
    const spouseInput = document.getElementById('spouse_name');
    
    if (civilStatus === 'Single') {
        // Hide spouse field and remove required attribute
        spouseField.style.display = 'none';
        spouseInput.removeAttribute('required');
        spouseInput.value = ''; // Clear the value
    } else if (civilStatus === 'Married') {
        // Show spouse field and make it required
        spouseField.style.display = 'block';
        spouseInput.setAttribute('required', 'required');
    } else {
        // For Widowed, Separated, show field but not required
        spouseField.style.display = 'block';
        spouseInput.removeAttribute('required');
    }
}

// Initialize spouse field visibility on page load
document.addEventListener('DOMContentLoaded', function() {
    // Hide spouse field by default
    const spouseField = document.getElementById('spouse_field');
    if (spouseField) {
        spouseField.style.display = 'none';
    }
});

// Commodity Management Functions
let commodityIndex = 1; // Start from 1 since 0 is the primary commodity

function addCommodityRow() {
    const container = document.getElementById('commoditiesContainer');
    const commodityOptions = `<?php foreach ($commodities as $c): ?>
        <option value="<?php echo htmlspecialchars($c['commodity_id']); ?>"><?php echo htmlspecialchars($c['commodity_name']); ?></option>
    <?php endforeach; ?>`;
    
    const newRow = document.createElement('div');
    newRow.className = 'commodity-row mb-3 p-3 border rounded';
    newRow.setAttribute('data-commodity-index', commodityIndex);
    
    newRow.innerHTML = `
        <div class="row align-items-end">
            <div class="col-md-4 mb-3">
                <select class="form-select commodity-select" name="commodities[${commodityIndex}][commodity_id]" required>
                    <option value="">Select Commodity</option>
                    ${commodityOptions}
                </select>
            </div>
            <div class="col-md-2 mb-3">
                <input type="number" step="0.01" min="0" class="form-control text-center" name="commodities[${commodityIndex}][land_area_hectares]" placeholder="0.00" required>
            </div>
            <div class="col-md-2 mb-3">
                <input type="number" min="0" max="100" class="form-control text-center" name="commodities[${commodityIndex}][years_farming]" placeholder="0" required>
            </div>
            <div class="col-md-2 mb-3">
                <div class="form-check mt-2 text-center">
                    <input class="form-check-input primary-commodity-radio" type="radio" name="primary_commodity_index" value="${commodityIndex}">
                    <label class="form-check-label d-block">
                        <i class="fas fa-star me-1"></i>Primary
                    </label>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="mt-2 text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-commodity-btn">
                        <i class="fas fa-trash me-1"></i>Remove
                    </button>
                </div>
            </div>
        </div>
    `;
    
    container.appendChild(newRow);
    commodityIndex++;
    
    // Add event listener for remove button
    newRow.querySelector('.remove-commodity-btn').addEventListener('click', function() {
        removeCommodityRow(newRow);
    });
    
    // Add validation for duplicate commodities
    newRow.querySelector('.commodity-select').addEventListener('change', function() {
        validateDuplicateCommodities();
    });
}

function removeCommodityRow(row) {
    const container = document.getElementById('commoditiesContainer');
    const commodityRows = container.querySelectorAll('.commodity-row');
    
    // Prevent removal if it's the only row
    if (commodityRows.length <= 1) {
        alert('At least one commodity is required.');
        return;
    }
    
    // Check if removing the primary commodity
    const primaryRadio = row.querySelector('.primary-commodity-radio');
    if (primaryRadio && primaryRadio.checked) {
        // Set the first remaining commodity as primary
        const firstRow = container.querySelector('.commodity-row:not([data-commodity-index="' + row.getAttribute('data-commodity-index') + '"])');
        if (firstRow) {
            firstRow.querySelector('.primary-commodity-radio').checked = true;
        }
    }
    
    row.remove();
    validateDuplicateCommodities();
}

function validateDuplicateCommodities() {
    const selects = document.querySelectorAll('.commodity-select');
    const selectedValues = [];
    let hasDuplicates = false;
    
    selects.forEach(select => {
        const value = select.value;
        if (value && selectedValues.includes(value)) {
            hasDuplicates = true;
            select.classList.add('is-invalid');
        } else {
            select.classList.remove('is-invalid');
            if (value) selectedValues.push(value);
        }
    });
    
    // Show/hide duplicate warning
    let warningDiv = document.getElementById('duplicateWarning');
    if (hasDuplicates) {
        if (!warningDiv) {
            warningDiv = document.createElement('div');
            warningDiv.id = 'duplicateWarning';
            warningDiv.className = 'alert alert-warning mt-2';
            warningDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Duplicate commodities are not allowed.';
            document.getElementById('commoditiesContainer').appendChild(warningDiv);
        }
    } else if (warningDiv) {
        warningDiv.remove();
    }
    
    return !hasDuplicates;
}

// Add event listeners
document.getElementById('addCommodityBtn').addEventListener('click', addCommodityRow);

// Add validation for existing commodity select
document.querySelector('.commodity-select').addEventListener('change', function() {
    validateDuplicateCommodities();
});

// Form validation and submission
document.getElementById('farmerRegistrationForm').addEventListener('submit', function(e) {
    // Validate commodities first
    if (!validateDuplicateCommodities()) {
        e.preventDefault();
        alert('Please remove duplicate commodities before submitting.');
        return false;
    }
    
    // Check if at least one commodity is selected
    const commoditySelects = document.querySelectorAll('.commodity-select');
    let hasValidCommodity = false;
    commoditySelects.forEach(select => {
        if (select.value) hasValidCommodity = true;
    });
    
    if (!hasValidCommodity) {
        e.preventDefault();
        alert('At least one commodity must be selected.');
        return false;
    }
    
    // Check if a primary commodity is selected
    const primaryRadios = document.querySelectorAll('.primary-commodity-radio');
    let hasPrimary = false;
    primaryRadios.forEach(radio => {
        if (radio.checked) hasPrimary = true;
    });
    
    if (!hasPrimary) {
        e.preventDefault();
        alert('Please select which commodity is the primary one.');
        return false;
    }
    
    const rsbsaRegistered = document.getElementById('rsbsa_registered').value;
    const rsbsaRegistrationNumber = document.getElementById('rsbsa_registration_number').value;
    const ncfrsRegistered = document.getElementById('ncfrs_registered') ? document.getElementById('ncfrs_registered').value : '';
    const ncfrsId = document.getElementById('ncfrs_id') ? document.getElementById('ncfrs_id').value : '';
    const fisherRegistered = document.getElementById('fisherfold_registered') ? document.getElementById('fisherfold_registered').value : '';
    const fisherId = document.getElementById('fisherfold_id') ? document.getElementById('fisherfold_id').value : '';
    const hasBoat = document.getElementById('has_boat') ? document.getElementById('has_boat').value : '';
    const boatReg = document.getElementById('boat_registration_no') ? document.getElementById('boat_registration_no').value : '';
    
    if (rsbsaRegistered === 'Yes' && !rsbsaRegistrationNumber.trim()) {
        e.preventDefault();
        alert('RSBSA Registration Number is required when farmer is RSBSA registered.');
        document.getElementById('rsbsa_registration_number').focus();
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