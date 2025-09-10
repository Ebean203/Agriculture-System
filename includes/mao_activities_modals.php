<?php
// This file contains the modals for MAO Activities Management
// Make sure $staff_result is available when including this file
?>

<!-- View Activity Modal -->
<div class="modal fade" id="viewActivityModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-agri-green text-white">
                <h5 class="modal-title">
                    <i class="fas fa-eye mr-2"></i>View Activity Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label font-semibold text-gray-700">Staff Member</label>
                        <p id="view_staff_name" class="form-control-plaintext border rounded px-3 py-2 bg-gray-50"></p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label font-semibold text-gray-700">Activity Type</label>
                        <p id="view_activity_type" class="form-control-plaintext border rounded px-3 py-2 bg-gray-50"></p>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label font-semibold text-gray-700">Activity Title</label>
                    <p id="view_title" class="form-control-plaintext border rounded px-3 py-2 bg-gray-50"></p>
                </div>
                
                <div class="mb-3">
                    <label class="form-label font-semibold text-gray-700">Description</label>
                    <p id="view_description" class="form-control-plaintext border rounded px-3 py-2 bg-gray-50" style="min-height: 100px; white-space: pre-wrap;"></p>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label font-semibold text-gray-700">Activity Date</label>
                        <p id="view_activity_date" class="form-control-plaintext border rounded px-3 py-2 bg-gray-50"></p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label font-semibold text-gray-700">Location</label>
                        <p id="view_location" class="form-control-plaintext border rounded px-3 py-2 bg-gray-50"></p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label font-semibold text-gray-700">Created At</label>
                        <p id="view_created_at" class="form-control-plaintext border rounded px-3 py-2 bg-gray-50"></p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label font-semibold text-gray-700">Last Updated</label>
                        <p id="view_updated_at" class="form-control-plaintext border rounded px-3 py-2 bg-gray-50"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="closeViewAndOpenEdit()">
                    <i class="fas fa-edit mr-2"></i>Edit Activity
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Activity Modal -->
<div class="modal fade" id="addActivityModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-agri-green text-white">
                <h5 class="modal-title">
                    <i class="fas fa-plus mr-2"></i>Add New Activity
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_activity">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Staff Member *</label>
                            <select name="staff_id" class="form-select" required>
                                <option value="">Select Staff Member</option>
                                <?php
                                if (isset($staff_result) && $staff_result) {
                                    mysqli_data_seek($staff_result, 0);
                                    while ($staff = mysqli_fetch_assoc($staff_result)):
                                ?>
                                    <option value="<?php echo $staff['staff_id']; ?>">
                                        <?php echo htmlspecialchars($staff['full_name']); ?>
                                    </option>
                                <?php 
                                    endwhile;
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Activity Type *</label>
                            <select name="activity_type" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="Training">Training</option>
                                <option value="Meeting">Meeting</option>
                                <option value="Inspection">Inspection</option>
                                <option value="Seminar">Seminar</option>
                                <option value="Workshop">Workshop</option>
                                <option value="Conference">Conference</option>
                                <option value="Field Visit">Field Visit</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Activity Title *</label>
                        <input type="text" name="title" class="form-control" required 
                               placeholder="Enter activity title">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4" 
                                  placeholder="Enter activity description"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Activity Date *</label>
                            <input type="date" name="activity_date" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location *</label>
                            <input type="text" name="location" class="form-control" required 
                                   placeholder="Enter location">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save mr-2"></i>Add Activity
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Activity Modal -->
<div class="modal fade" id="editActivityModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-blue-600 text-white">
                <h5 class="modal-title">
                    <i class="fas fa-edit mr-2"></i>Edit Activity
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="editActivityForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_activity">
                    <input type="hidden" name="activity_id" id="edit_activity_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Staff Member *</label>
                            <select name="staff_id" id="edit_staff_id" class="form-select" required>
                                <option value="">Select Staff Member</option>
                                <?php
                                if (isset($staff_result) && $staff_result) {
                                    mysqli_data_seek($staff_result, 0);
                                    while ($staff = mysqli_fetch_assoc($staff_result)):
                                ?>
                                    <option value="<?php echo $staff['staff_id']; ?>">
                                        <?php echo htmlspecialchars($staff['full_name']); ?>
                                    </option>
                                <?php 
                                    endwhile;
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Activity Type *</label>
                            <select name="activity_type" id="edit_activity_type" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="Training">Training</option>
                                <option value="Meeting">Meeting</option>
                                <option value="Inspection">Inspection</option>
                                <option value="Seminar">Seminar</option>
                                <option value="Workshop">Workshop</option>
                                <option value="Conference">Conference</option>
                                <option value="Field Visit">Field Visit</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Activity Title *</label>
                        <input type="text" name="title" id="edit_title" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="4"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Activity Date *</label>
                            <input type="date" name="activity_date" id="edit_activity_date" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location *</label>
                            <input type="text" name="location" id="edit_location" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-2"></i>Update Activity
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

</div>

<script>
// Global variable to store current activity data for edit modal
let currentActivityData = null;

function openAddModal() {
    const modal = new bootstrap.Modal(document.getElementById('addActivityModal'));
    modal.show();
}

function openViewModal(activity) {
    // Populate view modal fields
    document.getElementById('view_staff_name').textContent = activity.staff_name || 'Unassigned';
    document.getElementById('view_activity_type').textContent = activity.activity_type;
    document.getElementById('view_title').textContent = activity.title;
    document.getElementById('view_description').textContent = activity.description || 'No description provided';
    document.getElementById('view_activity_date').textContent = formatDate(activity.activity_date);
    document.getElementById('view_location').textContent = activity.location;
    document.getElementById('view_created_at').textContent = formatDateTime(activity.created_at);
    document.getElementById('view_updated_at').textContent = formatDateTime(activity.updated_at);
    
    // Store activity data for potential edit action
    currentActivityData = activity;
    
    const modal = new bootstrap.Modal(document.getElementById('viewActivityModal'));
    modal.show();
}

function openEditModal(activity) {
    document.getElementById('edit_activity_id').value = activity.activity_id;
    document.getElementById('edit_staff_id').value = activity.staff_id || '';
    document.getElementById('edit_activity_type').value = activity.activity_type;
    document.getElementById('edit_title').value = activity.title;
    document.getElementById('edit_description').value = activity.description || '';
    document.getElementById('edit_activity_date').value = activity.activity_date;
    document.getElementById('edit_location').value = activity.location;
    
    const modal = new bootstrap.Modal(document.getElementById('editActivityModal'));
    modal.show();
}

function closeViewAndOpenEdit() {
    // Close view modal
    const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewActivityModal'));
    viewModal.hide();
    
    // Wait a bit for the view modal to close, then open edit modal
    setTimeout(() => {
        if (currentActivityData) {
            openEditModal(currentActivityData);
        }
    }, 300);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
}

function formatDateTime(dateTimeString) {
    const date = new Date(dateTimeString);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Set default date to today for add form
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    const addDateInput = document.querySelector('#addActivityModal input[name="activity_date"]');
    if (addDateInput) {
        addDateInput.value = today;
    }
});
</script>
