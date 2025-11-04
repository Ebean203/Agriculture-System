<?php
// geotagging_modal.php - Separate modal for farmer geo-tagging
?>

<!-- Geo-tagging Modal -->
<div class="modal fade" id="geotaggingModal" tabindex="-1" aria-labelledby="geotaggingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="geotaggingModalLabel">
                    <i class="fas fa-map-marker-alt me-2"></i>Farmer Geo-tagging
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form id="geotaggingForm" method="POST" action="upload_farmer_photo.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <!-- Farmer Search Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-search me-2"></i>Select Farmer</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label for="geotag_farmer_search" class="form-label">Search Farmer <span class="text-danger">*</span></label>
                                    <div class="position-relative">
                                        <input type="text" class="form-control" id="geotag_farmer_search" 
                                               placeholder="Search by name, mobile, or ID..." autocomplete="off" required
                                               onkeyup="searchFarmersForGeotagging(this.value)"
                                               onfocus="showGeotagFarmerSuggestions()"
                                               onblur="hideGeotagFarmerSuggestions()">
                                        <i class="fas fa-search position-absolute" style="left: 10px; top: 12px; color: #6c757d;"></i>
                                        <input type="hidden" id="selected_farmer_id" name="farmer_id" required>
                                        
                                        <!-- Auto-suggest dropdown -->
                                        <div id="geotag_farmer_suggestions" class="position-absolute w-100 bg-white border border-secondary rounded shadow-lg mt-1" 
                                             style="max-height: 200px; overflow-y: auto; z-index: 1050; display: none;">
                                            <!-- Suggestions will be populated here -->
                                        </div>
                                    </div>
                                    <small class="text-muted">Start typing to see matching farmers</small>
                                </div>
                            </div>
                            
                            <!-- Selected Farmer Display -->
                            <div id="selected_farmer_display" class="alert alert-success" style="display: none;">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-user-check fa-2x me-3"></i>
                                    <div>
                                        <h6 class="mb-1">Selected Farmer:</h6>
                                        <strong id="selected_farmer_name"></strong><br>
                                        <small class="text-muted" id="selected_farmer_details"></small>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" onclick="clearSelectedFarmer()">
                                        <i class="fas fa-times"></i> Change
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Photo Upload Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-camera me-2"></i>Upload Geo-tagged Photo</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="farmer_photo_geotag" class="form-label">Farmer Photo <span class="text-danger">*</span></label>
                     <input type="file" class="form-control" id="farmer_photo_geotag" name="farmer_photo" 
                         accept="image/jpeg,image/jpg,image/png" capture="camera" required>
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Take a photo with GPS location enabled for automatic geo-tagging
                                    </small>
                                </div>
                                <div class="col-md-4 mb-3 d-flex align-items-center">
                                    <button type="button" class="btn btn-outline-success btn-lg w-100" onclick="document.getElementById('farmer_photo_geotag').click()">
                                        <i class="fas fa-camera fa-2x d-block mb-2"></i>
                                        Take Photo
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Photo Preview -->
                            <div id="photo_preview_container" class="row" style="display: none;">
                                <div class="col-12">
                                    <div class="text-center">
                                        <img id="photo_preview" src="" alt="Photo Preview" class="img-thumbnail" style="max-width: 300px; max-height: 200px;">
                                        <div class="mt-2">
                                            <small class="text-success">
                                                <i class="fas fa-check-circle me-1"></i>Photo selected and ready for upload
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info py-2 mb-0 mt-3">
                                <small>
                                    <i class="fas fa-lightbulb me-1"></i>
                                    <strong>Important:</strong> Ensure your device's location services are enabled before taking the photo. This will embed GPS coordinates in the image file for accurate field mapping.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-success" id="uploadPhotoBtn" disabled>
                        <i class="fas fa-upload me-1"></i>Upload Photo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Farmer search functionality using the same pattern as farmers.php
function searchFarmersForGeotagging(query) {
    const suggestions = document.getElementById('geotag_farmer_suggestions');
    
    if (!suggestions) return; // Exit if element doesn't exist

    if (query.length < 1) {
        suggestions.innerHTML = '';
        suggestions.style.display = 'none';
        clearSelectedFarmer();
        return;
    }

    // Show loading indicator
    suggestions.innerHTML = '<div class="px-3 py-2 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Searching...</div>';
    suggestions.style.display = 'block';

    // Make AJAX request to get farmer suggestions (same endpoint as farmers.php)
    fetch('get_farmers.php?action=search&include_archived=false&query=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.farmers && data.farmers.length > 0) {
                let html = '';
                data.farmers.forEach(farmer => {
                    html += `
                        <div class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-bottom farmer-suggestion-item" 
                             onclick="selectFarmerForGeotagging('${farmer.farmer_id}', '${farmer.full_name.replace(/'/g, "\\'")}', '${farmer.contact_number}', '${farmer.barangay_name}')"
                             style="cursor: pointer;">
                            <div class="fw-bold text-dark">${farmer.full_name}</div>
                            <div class="small text-muted">ID: ${farmer.farmer_id} | Contact: ${farmer.contact_number}</div>
                            <div class="small text-muted">${farmer.barangay_name}</div>
                        </div>
                    `;
                });
                suggestions.innerHTML = html;
                suggestions.style.display = 'block';
            } else {
                suggestions.innerHTML = '<div class="px-3 py-2 text-muted">No farmers found matching your search</div>';
                suggestions.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            suggestions.innerHTML = '<div class="px-3 py-2 text-danger">Error loading suggestions</div>';
            suggestions.style.display = 'block';
        });
}

function selectFarmerForGeotagging(farmerId, farmerName, contactNumber, barangayName) {
    // Get the search input and fill it with the farmer name
    const searchInput = document.getElementById('geotag_farmer_search');
    const selectedFarmerIdInput = document.getElementById('selected_farmer_id');
    const selectedFarmerDisplay = document.getElementById('selected_farmer_display');
    const selectedFarmerNameEl = document.getElementById('selected_farmer_name');
    const selectedFarmerDetailsEl = document.getElementById('selected_farmer_details');
    
    // Fill the search input with farmer name
    if (searchInput) {
        searchInput.value = farmerName;
    }
    
    // Set the hidden farmer ID
    if (selectedFarmerIdInput) {
        selectedFarmerIdInput.value = farmerId;
    }
    
    // Update the selected farmer display
    if (selectedFarmerNameEl) {
        selectedFarmerNameEl.textContent = farmerName;
    }
    if (selectedFarmerDetailsEl) {
        selectedFarmerDetailsEl.textContent = `ID: ${farmerId} | ${barangayName}`;
    }
    if (selectedFarmerDisplay) {
        selectedFarmerDisplay.style.display = 'block';
    }
    
    // Hide suggestions
    hideGeotagFarmerSuggestions();
    
    // Check if form is complete
    checkFormComplete();
}

function showGeotagFarmerSuggestions() {
    const searchInput = document.getElementById('geotag_farmer_search');
    if (searchInput.value.length >= 1) {
        searchFarmersForGeotagging(searchInput.value);
    }
}

function hideGeotagFarmerSuggestions() {
    const suggestions = document.getElementById('geotag_farmer_suggestions');
    if (suggestions) {
        setTimeout(() => {
            suggestions.style.display = 'none';
        }, 200); // Delay to allow click events on suggestions
    }
}

function clearSelectedFarmer() {
    const searchInput = document.getElementById('geotag_farmer_search');
    const selectedFarmerIdInput = document.getElementById('selected_farmer_id');
    const selectedFarmerDisplay = document.getElementById('selected_farmer_display');
    const uploadBtn = document.getElementById('uploadPhotoBtn');
    
    // Check if elements exist before accessing them
    if (selectedFarmerIdInput) selectedFarmerIdInput.value = '';
    if (searchInput) searchInput.value = '';
    if (selectedFarmerDisplay) selectedFarmerDisplay.style.display = 'none';
    
    checkFormComplete();
}

// Global function to check if form is complete
function checkFormComplete() {
    // Simple check - get elements
    const farmerId = document.getElementById('selected_farmer_id')?.value || '';
    const photoInput = document.getElementById('farmer_photo_geotag');
    const uploadBtn = document.getElementById('uploadPhotoBtn');
    
    if (uploadBtn) {
        // Enable button if we have farmer and photo
        const hasPhoto = photoInput && photoInput.files.length > 0;
        uploadBtn.disabled = !(farmerId && hasPhoto);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Photo preview functionality
    const photoInput = document.getElementById('farmer_photo_geotag');
    if (photoInput) {
        photoInput.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('photo_preview').src = e.target.result;
            document.getElementById('photo_preview_container').style.display = 'block';
            };
            reader.readAsDataURL(file);
            
            checkFormComplete();
        }
        });
    }

    // Form submission
    const geotaggingForm = document.getElementById('geotaggingForm');
    if (geotaggingForm) {
        geotaggingForm.addEventListener('submit', function(e) {
    e.preventDefault(); // Prevent default form submission
    
    // Validate farmer selection
    const farmerId = document.getElementById('selected_farmer_id').value;
    if (!farmerId) {
        if (window.AgriToast) { AgriToast.error('Please select a farmer first.'); }
        return;
    }
    
    // Validate photo selection
    const photoInput = document.getElementById('farmer_photo_geotag');
    if (!photoInput.files.length) {
        if (window.AgriToast) { AgriToast.error('Please select a photo first.'); }
        return;
    }
    // Client-side type check to avoid server error alerts
    const allowedTypes = ['image/jpeg','image/jpg','image/png'];
    const fileType = photoInput.files[0].type;
    if (!allowedTypes.includes(fileType)) {
        if (window.AgriToast) { AgriToast.error('Only JPEG and PNG images are allowed.'); }
        return;
    }
    
    const formData = new FormData(this);
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Uploading...';
    submitBtn.disabled = true;
    
    fetch('upload_farmer_photo.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close modal first
            const modal = bootstrap.Modal.getInstance(document.getElementById('geotaggingModal'));
            if (modal) modal.hide();

            // Simple toast only
            if (window.AgriToast) { AgriToast.success('Photo uploaded successfully.'); }

            // Refresh page after a short delay so toast is visible
            setTimeout(() => { window.location.reload(); }, 1600);
        } else {
            // Error toast only
            if (window.AgriToast) { AgriToast.error(data.message || 'Failed to upload photo.'); }
        }
    })
    .catch(error => {
        if (window.AgriToast) { AgriToast.error('An error occurred while uploading the photo.'); }
    })
    .finally(() => {
        // Reset button
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        });
        });
    }

    // Reset modal when closed
    const geotaggingModal = document.getElementById('geotaggingModal');
    if (geotaggingModal) {
        geotaggingModal.addEventListener('hidden.bs.modal', function() {
            const form = document.getElementById('geotaggingForm');
            if (form) form.reset();
            clearSelectedFarmer();
            
            const suggestions = document.getElementById('geotag_farmer_suggestions');
            if (suggestions) {
                suggestions.style.display = 'none';
            }
            
            const photoPreview = document.getElementById('photo_preview_container');
            if (photoPreview) photoPreview.style.display = 'none';
        });
    }
}); // End of DOMContentLoaded

// Hide search results when clicking outside
document.addEventListener('click', function(e) {
    const searchInput = document.getElementById('geotag_farmer_search');
    const suggestions = document.getElementById('geotag_farmer_suggestions');
    
    if (searchInput && suggestions && 
        !searchInput.contains(e.target) && !suggestions.contains(e.target)) {
        suggestions.style.display = 'none';
    }
});
</script>

<style>
.list-group-item:hover {
    background-color: #f8f9fa;
}

#photo_preview {
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.modal-lg {
    max-width: 600px;
}

/* Farmer suggestions styling */
#geotag_farmer_suggestions {
    border: 1px solid #dee2e6;
    border-top: none;
    border-radius: 0 0 0.375rem 0.375rem;
    background-color: white;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

#geotag_farmer_suggestions .farmer-suggestion-item:hover {
    background-color: #f8f9fa !important;
}

#geotag_farmer_suggestions .farmer-suggestion-item:not(:last-child) {
    border-bottom: 1px solid #dee2e6;
}

/* Search input with icon */
#geotag_farmer_search {
    padding-left: 35px;
}
</style>