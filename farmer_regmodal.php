<?php
require_once 'check_session.php';
require_once 'conn.php';

// Fetch barangays for the barangay select
$barangays = [];
$query = "SELECT barangay_id, barangay_name FROM barangays ORDER BY barangay_name";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_execute($stmt);
$bq = mysqli_stmt_get_result($stmt);
if ($bq) {
    while ($row = mysqli_fetch_assoc($bq)) {
        $barangays[] = $row;
    }
}
mysqli_stmt_close($stmt);

// Fetch commodities for autosuggest (with category if available)
$commodities = [];
$query = "SELECT c.commodity_id, c.commodity_name, c.category_id, cc.category_name FROM commodities c LEFT JOIN commodity_categories cc ON c.category_id = cc.category_id ORDER BY cc.category_name, c.commodity_name";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_execute($stmt);
$cq = mysqli_stmt_get_result($stmt);
if ($cq) {
    while ($row = mysqli_fetch_assoc($cq)) {
        $commodities[] = $row;
    }
}
mysqli_stmt_close($stmt);

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
                                    <label for="middle_name" class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" id="middle_name" name="middle_name">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="suffix" class="form-label">Suffix</label>
                                    <input type="text" class="form-control" id="suffix" name="suffix" placeholder="Jr., Sr., III, etc.">
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
                                <div class="row align-items-center mb-2">
                                    <div class="col-md-3 mb-3">
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" id="checkAllRegistrations" onclick="toggleAllRegistrations(this)">
                                            <label class="form-check-label fw-bold" for="checkAllRegistrations">Check All</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <!-- RSBSA -->
                                    <div class="col-md-3 mb-3">
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" value="1" id="is_rsbsa" name="is_rsbsa">
                                            <label class="form-check-label" for="is_rsbsa">RSBSA Registered</label>
                                        </div>
                                    </div>

                                    <!-- NCFRS -->
                                    <div class="col-md-3 mb-3">
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" value="1" id="is_ncfrs" name="is_ncfrs">
                                            <label class="form-check-label" for="is_ncfrs">NCFRS Registered</label>
                                        </div>
                                    </div>

                                    <!-- FISHERfolk -->
                                    <div class="col-md-3 mb-3">
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" value="1" id="is_fisherfolk" name="is_fisherfolk">
                                            <label class="form-check-label" for="is_fisherfolk">Fisherfolk Registered</label>
                                        </div>
                                    </div>

                                    <!-- Boat -->
                                    <div class="col-md-3 mb-3">
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" value="1" id="is_boat" name="is_boat">
                                            <label class="form-check-label" for="is_boat">Has Boat</label>
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
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label for="other_income_source" class="form-label">Other Income Source <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="other_income_source" name="other_income_source" rows="2" placeholder="e.g., fishing, vending, etc." required></textarea>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="land_area_hectares" class="form-label">Total Land Area (Hectares)</label>
                    <input type="number" class="form-control" id="land_area_hectares" name="land_area_hectares" step="0.01" min="0" placeholder="0.00">
                    <small class="form-text text-muted">Total land area owned by the farmer</small>
                </div>
            </div>
        </div>
    </div>                    <!-- RSBSA Registration Section -->
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
                                        <option value="">Select Education Level</option>
                                        <option value="Elementary">Elementary</option>
                                        <option value="Highschool">High School</option>
                                        <option value="College">College</option>
                                        <option value="Vocational">Vocational</option>
                                        <option value="Graduate">Graduate</option>
                                        <option value="Not Specified">Not Specified</option>
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
                                <div class="col-md-5">
                                    <strong>Commodity</strong>
                                </div>
                                <div class="col-md-3 text-center">
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
                                        <div class="col-md-5 mb-3 position-relative">
                                            <input type="text" class="form-control commodity-search" name="commodities[0][commodity_name]" autocomplete="off" required placeholder="Type to search commodity...">
                                            <!-- Hidden field to store the selected commodity ID so backend receives commodity_id -->
                                            <input type="hidden" name="commodities[0][commodity_id]" class="commodity-id" value="">
                                            <ul class="commodity-suggestions position-absolute w-100 bg-white border rounded shadow-sm mt-1 d-none list-unstyled mb-0" style="z-index: 1050; max-height: 200px; overflow-y: auto;"></ul>
                                        </div>
                                        <div class="col-md-3 mb-3">
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
// Toggle all registration checkboxes
function toggleAllRegistrations(source) {
    const ids = ['is_rsbsa', 'is_ncfrs', 'is_fisherfolk', 'is_boat'];
    ids.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.checked = source.checked;
    });
}
function toggleSpouseField() {
    const civilStatus = document.getElementById('civil_status').value;
    const spouseField = document.getElementById('spouse_field');
    const spouseInput = document.getElementById('spouse_name');
    if (!spouseField || !spouseInput) return;
    if (civilStatus === 'Single') {
        spouseField.style.display = 'none';
        spouseInput.removeAttribute('required');
        spouseInput.value = '';
    } else if (civilStatus === 'Married') {
        spouseField.style.display = 'block';
        spouseInput.setAttribute('required', 'required');
    } else {
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
    const newRow = document.createElement('div');
    newRow.className = 'commodity-row mb-3 p-3 border rounded';
    newRow.setAttribute('data-commodity-index', commodityIndex);
    newRow.innerHTML = `
        <div class="row align-items-end">
            <div class="col-md-5 mb-3 position-relative">
                <input type="text" class="form-control commodity-search" name="commodities[${commodityIndex}][commodity_name]" autocomplete="off" required placeholder="Type to search commodity...">
                <!-- Hidden field to store selected commodity id for backend -->
                <input type="hidden" name="commodities[${commodityIndex}][commodity_id]" class="commodity-id" value="">
                <ul class="commodity-suggestions position-absolute w-100 bg-white border rounded shadow-sm mt-1 d-none list-unstyled mb-0" id="commoditySuggestions${commodityIndex}" style="z-index: 1050; max-height: 200px; overflow-y: auto;"></ul>
            </div>
            <div class="col-md-3 mb-3">
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
        newRow.querySelector('.commodity-search').addEventListener('change', function() {
            validateDuplicateCommodities();
        });
}

function removeCommodityRow(row) {
    const container = document.getElementById('commoditiesContainer');
    const commodityRows = container.querySelectorAll('.commodity-row');
    
    // Prevent removal if it's the only row
    if (commodityRows.length <= 1) {
        if (window.AgriToast) { AgriToast.error('At least one commodity is required.'); }
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
    const selects = document.querySelectorAll('.commodity-search');
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

// Simple HTML-escape helper to safely inject suggestion text
function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

// Delegated, global autosuggest handlers for all commodity-search inputs (primary + dynamic)
document.addEventListener('input', function(e) {
    if (e.target.classList && e.target.classList.contains('commodity-search')) {
        const input = e.target;
        const suggestionsBox = input.parentElement.querySelector('.commodity-suggestions');
        const hiddenId = input.parentElement.querySelector('.commodity-id');
        if (hiddenId) hiddenId.value = ''; // clear previously selected id when user types
        if (!suggestionsBox) return;
        const q = input.value.trim();
        if (q.length < 1) {
            suggestionsBox.innerHTML = '';
            suggestionsBox.classList.add('d-none');
            if (hiddenId) hiddenId.value = '';
            return;
        }
        fetch('get_commodity_suggestions.php?q=' + encodeURIComponent(q))
            .then(res => res.json())
            .then(data => {
                if (data && data.success && Array.isArray(data.commodities) && data.commodities.length > 0) {
                    suggestionsBox.innerHTML = data.commodities.map(c =>
                        `<li class="list-group-item list-group-item-action" data-id="${c.commodity_id}" data-name="${escapeHtml(c.commodity_name)}">${escapeHtml(c.commodity_name)}${c.category_name ? ' <small class="text-muted">(' + escapeHtml(c.category_name) + ')</small>' : ''}</li>`
                    ).join('');
                    suggestionsBox.classList.remove('d-none');
                    suggestionsBox.querySelectorAll('li').forEach(li => {
                        li.addEventListener('click', function() {
                            input.value = this.getAttribute('data-name');
                            // set the hidden commodity_id so server receives the id
                            if (hiddenId) hiddenId.value = this.getAttribute('data-id');
                            suggestionsBox.classList.add('d-none');
                            validateDuplicateCommodities();
                        });
                    });
                } else {
                    suggestionsBox.innerHTML = '<li class="list-group-item text-muted">No results</li>';
                    suggestionsBox.classList.remove('d-none');
                    if (hiddenId) hiddenId.value = '';
                }
            })
            .catch(err => {
                suggestionsBox.innerHTML = '<li class="list-group-item text-danger">Error fetching suggestions</li>';
                suggestionsBox.classList.remove('d-none');
                if (hiddenId) hiddenId.value = '';
            });
    }
});

// Hide suggestions on blur (with a small delay to allow click to register)
document.addEventListener('focusout', function(e) {
    if (e.target.classList && e.target.classList.contains('commodity-search')) {
        setTimeout(() => {
            const suggestionsBox = e.target.parentElement.querySelector('.commodity-suggestions');
            if (suggestionsBox) suggestionsBox.classList.add('d-none');
        }, 200);
    }
});

// No .commodity-select exists anymore; validation is handled by .commodity-search and on form submit

// Form validation and submission
document.getElementById('farmerRegistrationForm').addEventListener('submit', function(e) {
    // Validate commodities first
    if (!validateDuplicateCommodities()) {
        e.preventDefault();
        if (window.AgriToast) { AgriToast.error('Please remove duplicate commodities before submitting.'); }
        return false;
    }

    // Check if at least one commodity input has a value
    const commodityInputs = document.querySelectorAll('.commodity-search');
    let hasValidCommodity = false;
    commodityInputs.forEach(inp => {
        if (inp.value && inp.value.trim()) hasValidCommodity = true;
    });

    if (!hasValidCommodity) {
        e.preventDefault();
        if (window.AgriToast) { AgriToast.error('At least one commodity must be selected.'); }
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
        if (window.AgriToast) { AgriToast.error('Please select which commodity is the primary one.'); }
        return false;
    }

    // Prevent duplicate farmer registration (same first, last, birthdate)
    const firstName = document.getElementById('first_name').value.trim().toLowerCase();
    const lastName = document.getElementById('last_name').value.trim().toLowerCase();
    const birthDate = document.getElementById('birth_date').value;
    let isDuplicate = false;
    // Synchronous AJAX is deprecated, but for validation before submit, we use fetch with async/await
    e.preventDefault();
    fetch('get_farmers.php?action=search&query=' + encodeURIComponent(firstName + ' ' + lastName))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.farmers) {
                data.farmers.forEach(farmer => {
                    if (farmer.full_name.toLowerCase().includes(lastName + ', ' + firstName) && farmer.birth_date === birthDate) {
                        isDuplicate = true;
                    }
                });
            }
            if (isDuplicate) {
                if (window.AgriToast) { AgriToast.error('A farmer with the same name and birth date already exists.'); }
                return false;
            } else {
                e.target.submit();
            }
        });
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