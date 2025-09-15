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

// Fetch commodities for primary commodity select
$commodities = [];
$cq = mysqli_query($conn, "SELECT commodity_id, commodity_name FROM commodities ORDER BY commodity_name");
if ($cq) {
    while ($row = mysqli_fetch_assoc($cq)) {
        $commodities[] = $row;
    }
}

// Fetch commodities for the selected farmer (for edit)
$farmer_commodities = [];
if (!empty($_GET['farmer_id'])) {
    $farmer_id = $conn->real_escape_string($_GET['farmer_id']);
    $cq = $conn->query("SELECT commodity_id, land_area_hectares, years_farming, is_primary FROM farmer_commodities WHERE farmer_id = '$farmer_id' ORDER BY is_primary DESC, id ASC");
    if ($cq) {
        while ($row = $cq->fetch_assoc()) {
            $farmer_commodities[] = $row;
        }
    }
}

if ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
?>

<!-- Edit Farmer Modal -->
<div class="modal fade" id="editFarmerModal" tabindex="-1" aria-labelledby="editFarmerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-agri-green text-white">
                <h5 class="modal-title" id="editFarmerModalLabel">
                    <i class="fas fa-user-edit me-2"></i>Edit Farmer Information
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editFarmerForm" method="POST" action="farmers.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="farmer_id" id="edit_farmer_id" value="">
                    
                    <!-- Personal Information Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-user me-2"></i>Personal Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="edit_first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="edit_middle_name" class="form-label">Middle Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_middle_name" name="middle_name" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="edit_last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="edit_suffix" class="form-label">Suffix</label>
                                    <input type="text" class="form-control" id="edit_suffix" name="suffix" placeholder="Jr., Sr., III, etc.">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="edit_birth_date" class="form-label">Birth Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="edit_birth_date" name="birth_date" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="edit_gender" class="form-label">Gender <span class="text-danger">*</span></label>
                                    <select class="form-select" id="edit_gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
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
                                    <label for="edit_contact_number" class="form-label">Contact Number <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="edit_contact_number" name="contact_number" required placeholder="09xxxxxxxxx">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="edit_barangay_id" class="form-label">Barangay <span class="text-danger">*</span></label>
                                    <select class="form-select" id="edit_barangay_id" name="barangay_id" required>
                                        <option value="">Select Barangay</option>
                                        <?php foreach ($barangays as $barangay): ?>
                                            <option value="<?php echo htmlspecialchars($barangay['barangay_id']); ?>">
                                                <?php echo htmlspecialchars($barangay['barangay_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="edit_address_details" class="form-label">Full Address <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="edit_address_details" name="address_details" rows="2" required placeholder="Complete residential address"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Household Information Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-home me-2"></i>Household Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="edit_civil_status" class="form-label">Civil Status <span class="text-danger">*</span></label>
                                    <select class="form-select" id="edit_civil_status" name="civil_status" required onchange="toggleEditSpouseField()">
                                        <option value="">Select</option>
                                        <option value="Single">Single</option>
                                        <option value="Married">Married</option>
                                        <option value="Widowed">Widowed</option>
                                        <option value="Separated">Separated</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3" id="edit_spouse_field">
                                    <label for="edit_spouse_name" class="form-label">Spouse Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_spouse_name" name="spouse_name" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="edit_household_size" class="form-label">Household Size <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="edit_household_size" name="household_size" min="1" value="1" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="edit_education_level" class="form-label">Education Level <span class="text-danger">*</span></label>
                                    <select class="form-select" id="edit_education_level" name="education_level" required>
                                        <option value="">Select Education Level</option>
                                        <option value="Elementary">Elementary</option>
                                        <option value="Highschool">High School</option>
                                        <option value="College">College</option>
                                        <option value="Vocational">Vocational</option>
                                        <option value="Graduate">Graduate</option>
                                        <option value="Not Specified">Not Specified</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="edit_occupation" class="form-label">Occupation <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_occupation" name="occupation" value="Farmer" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="edit_other_income_source" class="form-label">Other Income Source</label>
                                    <input type="text" class="form-control" id="edit_other_income_source" name="other_income_source" placeholder="e.g., OFW allotment, business">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="edit_is_member_of_4ps" name="is_member_of_4ps">
                                        <label class="form-check-label" for="edit_is_member_of_4ps">Member of 4Ps</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_is_ip" name="is_ip">
                                        <label class="form-check-label" for="edit_is_ip">Indigenous Peoples (IP)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_is_rsbsa" name="is_rsbsa">
                                        <label class="form-check-label" for="edit_is_rsbsa">RSBSA Registered</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_is_ncfrs" name="is_ncfrs">
                                        <label class="form-check-label" for="edit_is_ncfrs">NCFRS Registered</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_is_fisherfolk" name="is_fisherfolk">
                                        <label class="form-check-label" for="edit_is_fisherfolk">Fisherfolk Registered</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_is_boat" name="is_boat">
                                        <label class="form-check-label" for="edit_is_boat">Has Boat</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Farming Details Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="fas fa-tractor me-2"></i>Farming Details</h6>
                            <button type="button" class="btn btn-sm btn-success" id="editAddCommodityBtn">
                                <i class="fas fa-plus me-1"></i>Add Commodity
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="row mb-2 border-bottom pb-2">
                                <div class="col-md-4"><strong>Commodity</strong></div>
                                <div class="col-md-2 text-center"><strong>Land Area (ha)</strong></div>
                                <div class="col-md-2 text-center"><strong>Years Experience</strong></div>
                                <div class="col-md-2 text-center"><strong>Primary</strong></div>
                                <div class="col-md-2 text-center"><strong>Action</strong></div>
                            </div>
                            <div id="editCommoditiesContainer">
                                <!-- Commodity rows will be dynamically inserted here by JS -->
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
                        <i class="fas fa-save me-1"></i>Update Farmer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// --- Commodity Management for Edit Modal ---
let editCommodityIndex = 0;
function renderEditCommodities(commodities) {
    const container = document.getElementById('editCommoditiesContainer');
    container.innerHTML = '';
    commodities.forEach((commodity, idx) => {
        const isPrimary = commodity.is_primary ? 'checked' : '';
        const locked = idx === 0 ? 'disabled' : '';
        container.innerHTML += `
            <div class="commodity-row mb-3 p-3 border rounded bg-light" data-commodity-index="${idx}">
                <div class="row align-items-end">
                    <div class="col-md-4 mb-3">
                        <select class="form-select commodity-select" name="commodities[${idx}][commodity_id]" required>
                            <option value="">Select Commodity</option>
                            ${window.editCommodityOptions}
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <input type="number" step="0.01" min="0" class="form-control text-center" name="commodities[${idx}][land_area_hectares]" value="${commodity.land_area_hectares}" placeholder="0.00" required>
                    </div>
                    <div class="col-md-2 mb-3">
                        <input type="number" min="0" max="100" class="form-control text-center" name="commodities[${idx}][years_farming]" value="${commodity.years_farming}" placeholder="0" required>
                    </div>
                    <div class="col-md-2 mb-3">
                        <div class="form-check mt-2 text-center">
                            <input class="form-check-input primary-commodity-radio" type="radio" name="primary_commodity_index" value="${idx}" ${isPrimary} required>
                            <label class="form-check-label text-success fw-bold d-block">
                                <i class="fas fa-star me-1"></i>Primary
                            </label>
                        </div>
                    </div>
                    <div class="col-md-2 mb-3">
                        <div class="mt-2 text-center">
                            ${idx === 0 ? '<span class="text-muted small"><i class="fas fa-lock me-1"></i>Primary</span>' : `<button type="button" class="btn btn-sm btn-danger" onclick="removeEditCommodity(${idx})"><i class="fas fa-trash"></i></button>`}
                        </div>
                    </div>
                </div>
                ${idx === 0 ? `<div class="row"><div class="col-12"><div class="alert alert-info py-2 mb-0"><small><i class="fas fa-info-circle me-1"></i>This is the primary commodity and cannot be removed. At least one commodity is required.</small></div></div></div>` : ''}
            </div>
        `;
    });
    // Set selected values for commodity selects
    commodities.forEach((commodity, idx) => {
        const select = container.querySelector(`[name='commodities[${idx}][commodity_id]']`);
        if (select) select.value = commodity.commodity_id;
    });
}

function removeEditCommodity(idx) {
    window.editCommodities.splice(idx, 1);
    // Always keep at least one commodity
    if (window.editCommodities.length === 0) {
        window.editCommodities.push({commodity_id: '', land_area_hectares: '', years_farming: '', is_primary: true});
    }
    // Ensure only one is primary
    window.editCommodities.forEach((c, i) => c.is_primary = i === 0);
    renderEditCommodities(window.editCommodities);
}

document.getElementById('editAddCommodityBtn').addEventListener('click', function() {
    window.editCommodities.push({commodity_id: '', land_area_hectares: '', years_farming: '', is_primary: false});
    renderEditCommodities(window.editCommodities);
});

// Prepare commodity options for JS
window.editCommodityOptions = `<?php foreach ($commodities as $c): ?><option value="<?php echo htmlspecialchars($c['commodity_id']); ?>"><?php echo htmlspecialchars($c['commodity_name']); ?></option><?php endforeach; ?>`;

// Initialize commodities from PHP (replace with actual farmer data)
window.editCommodities = <?php echo json_encode($farmer_commodities && count($farmer_commodities) ? $farmer_commodities : [['commodity_id'=>'','land_area_hectares'=>'','years_farming'=>'','is_primary'=>true]]); ?>;
renderEditCommodities(window.editCommodities);
function toggleEditSpouseField() {
    const civilStatus = document.getElementById('edit_civil_status').value;
    const spouseField = document.getElementById('edit_spouse_field');
    const spouseInput = document.getElementById('edit_spouse_name');
    
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

function validateEditForm() {
    const errors = [];
    
    // Required field validations
    const firstName = document.getElementById('edit_first_name').value.trim();
    const lastName = document.getElementById('edit_last_name').value.trim();
    const birthDate = document.getElementById('edit_birth_date').value;
    const gender = document.getElementById('edit_gender').value;
    const contactNumber = document.getElementById('edit_contact_number').value.trim();
    const address = document.getElementById('edit_address_details').value.trim();
    const barangay = document.getElementById('edit_barangay_id').value;
    
    // Check required fields
    if (!firstName) errors.push('First Name is required');
    if (!lastName) errors.push('Last Name is required');
    if (!birthDate) errors.push('Birth Date is required');
    if (!gender) errors.push('Gender is required');
    if (!contactNumber) errors.push('Contact Number is required');
    if (!address) errors.push('Address is required');
    if (!barangay) errors.push('Barangay is required');
    
    // Validate birth date
    if (birthDate) {
        const birth = new Date(birthDate);
        const today = new Date();
        const minDate = new Date('1900-01-01');
        
        if (birth > today) {
            errors.push('Birth date cannot be in the future');
        }
        if (birth < minDate) {
            errors.push('Birth date cannot be before 1900');
        }
    }
    
    // Validate contact number
    if (contactNumber) {
        const digits = contactNumber.replace(/[^0-9]/g, '');
        if (digits.length < 7 || digits.length > 13) {
            errors.push('Contact number must be between 7-13 digits');
        }
    }
    
    // Validate household size
    const householdSize = document.getElementById('edit_household_size').value;
    if (householdSize && (parseInt(householdSize) < 1 || parseInt(householdSize) > 50)) {
        errors.push('Household size must be between 1 and 50');
    }
    
    // Validate years farming
    const yearsFarming = document.getElementById('edit_years_farming').value;
    if (yearsFarming && (parseInt(yearsFarming) < 0 || parseInt(yearsFarming) > 100)) {
        errors.push('Years farming must be between 0 and 100');
    }
    
    // Validate land area
    const landArea = document.getElementById('edit_land_area_hectares').value;
    if (landArea && parseFloat(landArea) < 0) {
        errors.push('Land area cannot be negative');
    }
    
    // Check spouse name if married
    const civilStatus = document.getElementById('edit_civil_status').value;
    const spouseName = document.getElementById('edit_spouse_name').value.trim();
    if (civilStatus === 'Married' && !spouseName) {
        errors.push('Spouse name is required when civil status is Married');
    }
    
    if (errors.length > 0) {
        alert('Please fix the following errors:\n\n' + errors.join('\n'));
        return false;
    }
    
    return true;
}

function editFarmer(farmerId) {
    // Fetch farmer data using the same endpoint as in farmers.php
    fetch(`farmers.php?action=get_farmer&id=${farmerId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const farmer = data.farmer;
                // Populate the form fields
                document.getElementById('edit_farmer_id').value = farmer.farmer_id || '';
                document.getElementById('edit_first_name').value = farmer.first_name || '';
                document.getElementById('edit_middle_name').value = farmer.middle_name || '';
                document.getElementById('edit_last_name').value = farmer.last_name || '';
                document.getElementById('edit_suffix').value = farmer.suffix || '';
                document.getElementById('edit_birth_date').value = farmer.birth_date || '';
                document.getElementById('edit_gender').value = farmer.gender || '';
                // --- PATCH: Populate household info fields from backend ---
                document.getElementById('edit_civil_status').value = farmer.civil_status || '';
                document.getElementById('edit_spouse_name').value = farmer.spouse_name || '';
                document.getElementById('edit_household_size').value = farmer.household_size || '1';
                document.getElementById('edit_education_level').value = farmer.education_level || '';
                document.getElementById('edit_occupation').value = farmer.occupation || 'Farmer';
                document.getElementById('edit_contact_number').value = farmer.contact_number || '';
                document.getElementById('edit_barangay_id').value = farmer.barangay_id || '';
                document.getElementById('edit_address_details').value = farmer.address_details || '';
                document.getElementById('edit_other_income_source').value = farmer.other_income_source || '';
                // Handle checkboxes
                document.getElementById('edit_is_member_of_4ps').checked = farmer.is_member_of_4ps == '1';
                document.getElementById('edit_is_ip').checked = farmer.is_ip == '1';
                document.getElementById('edit_is_rsbsa').checked = farmer.is_rsbsa == '1';
                document.getElementById('edit_is_ncfrs').checked = farmer.is_ncfrs == '1';
                document.getElementById('edit_is_fisherfolk').checked = farmer.is_fisherfolk == '1';
                document.getElementById('edit_is_boat').checked = farmer.is_boat == '1';
                // Trigger spouse field visibility
                toggleEditSpouseField();
                // --- PATCH: Multi-commodity support ---
                // Set window.editCommodities from backend data (array of commodities)
                window.editCommodities = Array.isArray(farmer.commodities) && farmer.commodities.length ? farmer.commodities : [{commodity_id:'',land_area_hectares:'',years_farming:'',is_primary:true}];
                renderEditCommodities(window.editCommodities);
                // Show the modal
                new bootstrap.Modal(document.getElementById('editFarmerModal')).show();
            } else {
                alert('Error loading farmer data: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading farmer data');
        });
}

// Handle edit form submission
document.addEventListener('DOMContentLoaded', function() {
    const editForm = document.getElementById('editFarmerForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            if (!validateEditForm()) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Updating...';
            submitBtn.disabled = true;
            
            // Re-enable button after a delay (in case of validation errors)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        });
    }
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

.alert-info {
    border-left: 4px solid #17a2b8;
}
</style>
