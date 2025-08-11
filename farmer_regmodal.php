<?php
require_once 'check_session.php';

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
            <form id="farmerRegistrationForm" method="POST" action="index.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="register_farmer">
                    
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
                                    <input type="text" class="form-control" id="barangay" name="barangay" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="address_details" class="form-label">Full Address <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="address_details" name="address_details" rows="2" required placeholder="Complete residential address"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- RSBSA Registration Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-certificate me-2"></i>RSBSA Registration</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="rsbsa_registered" class="form-label">RSBSA Registered? <span class="text-danger">*</span></label>
                                    <select class="form-select" id="rsbsa_registered" name="rsbsa_registered" required onchange="toggleRSBSAFields()">
                                        <option value="">Select Option</option>
                                        <option value="Yes">Yes</option>
                                        <option value="No">No</option>
                                    </select>
                                </div>
                            </div>

                            <!-- RSBSA Details (Hidden by default) -->
                            <div id="rsbsa_details" style="display: none;">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="rsbsa_id" class="form-label">RSBSA ID <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="rsbsa_id" name="rsbsa_id" placeholder="Enter RSBSA ID">
                                    </div>
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

// Form validation and submission
document.getElementById('farmerRegistrationForm').addEventListener('submit', function(e) {
    const rsbsaRegistered = document.getElementById('rsbsa_registered').value;
    const rsbsaId = document.getElementById('rsbsa_id').value;
    
    if (rsbsaRegistered === 'Yes' && !rsbsaId.trim()) {
        e.preventDefault();
        alert('RSBSA ID is required when farmer is RSBSA registered.');
        document.getElementById('rsbsa_id').focus();
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