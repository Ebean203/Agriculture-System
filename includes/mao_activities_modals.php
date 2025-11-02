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


    <style>
    /* Hover highlight for suggestion rows */
    .attendance-suggestion:hover { background-color: #f3f4f6; }
    </style>

    <script>
    // Farmer attendance search and suggestions (properly scoped and initialized)
    (function() {
        let attendanceSuggestionHideTimer = null;

        function esc(str) {
            return (str || '').replace(/[&<>"']/g, function(c) {
                return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]) || c;
            });
        }

        function getEl(id) { return document.getElementById(id); }
        function suggestionsEl() { return getEl('register_farmer_suggestions'); }
        function searchEl() { return getEl('register_farmer_search'); }
        function hiddenEl() { return getEl('register_farmer_id'); }
        function selectedBoxEl() { return getEl('register_selected_farmer'); }

        function setSearchValidity(message) {
            const input = searchEl();
            if (input) input.setCustomValidity(message || '');
        }

        function renderSuggestions(items) {
            const sug = suggestionsEl();
            if (!sug) return;
            if (!items || items.length === 0) {
                sug.innerHTML = '<div class="px-3 py-2 text-muted">No farmers found matching your search.</div>';
                sug.style.display = 'block';
                return;
            }
            let html = '';
            items.forEach(farmer => {
                const fullName = farmer.full_name || '';
                const barangay = farmer.barangay_name || '';
                const contact = farmer.contact_number || '';
                html += `
                    <div class="attendance-suggestion px-3 py-2 border-bottom" data-id="${esc(farmer.farmer_id)}" data-name="${esc(fullName)}" data-barangay="${esc(barangay)}" data-contact="${esc(contact)}" role="button" style="cursor:pointer;">
                        <div class="fw-semibold text-dark">${esc(fullName)}</div>
                        <div class="small text-muted">ID: ${esc(farmer.farmer_id)}${barangay ? ' | ' + esc(barangay) : ''}</div>
                        ${contact ? `<div class="small text-muted">Contact: ${esc(contact)}</div>` : ''}
                    </div>`;
            });
            sug.innerHTML = html;
            sug.style.display = 'block';
        }

        function searchFarmersForAttendanceInternal(query) {
            const sug = suggestionsEl();
            const se = searchEl();
            const hid = hiddenEl();
            const selBox = selectedBoxEl();
            if (!sug || !se) return;

            const trimmed = (query || '').trim();
            setSearchValidity('');

            if (trimmed.length === 0) {
                sug.innerHTML = '';
                sug.style.display = 'none';
                if (hid) hid.value = '';
                if (selBox) selBox.classList.add('d-none');
                return;
            }

            if (hid) hid.value = '';
            if (selBox) selBox.classList.add('d-none');

            sug.innerHTML = '<div class="px-3 py-2 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Searching...</div>';
            sug.style.display = 'block';

            fetch('get_farmers.php?action=search&include_archived=false&query=' + encodeURIComponent(trimmed))
                .then(res => res.json())
                .then(data => {
                    if (data && data.success && Array.isArray(data.farmers)) {
                        renderSuggestions(data.farmers);
                    } else {
                        renderSuggestions([]);
                    }
                })
                .catch(() => {
                    sug.innerHTML = '<div class="px-3 py-2 text-danger">Failed to load farmer suggestions.</div>';
                    sug.style.display = 'block';
                });
        }

        // Debounce wrapper to reduce server calls while typing
        let debounceTimer = null;
        function searchFarmersForAttendance(query) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => searchFarmersForAttendanceInternal(query), 250);
        }

        // Expose for inline handlers
        window.searchFarmersForAttendance = searchFarmersForAttendance;
        window.showAttendanceSuggestions = function() {
            clearTimeout(attendanceSuggestionHideTimer);
            const sug = suggestionsEl();
            if (sug && sug.innerHTML.trim() !== '') {
                sug.style.display = 'block';
            }
        };
        function hideAttendanceSuggestions() {
            const sug = suggestionsEl();
            if (sug) sug.style.display = 'none';
        }
        window.hideAttendanceSuggestionsWithDelay = function() {
            attendanceSuggestionHideTimer = setTimeout(hideAttendanceSuggestions, 150);
        };

        window.selectFarmerForAttendance = function(farmerId, fullName, contactNumber, barangay) {
            const se = searchEl();
            const hid = hiddenEl();
            const selBox = selectedBoxEl();
            const nameEl = getEl('register_selected_farmer_name');
            const detailsEl = getEl('register_selected_farmer_details');

            if (se) { se.value = fullName; setSearchValidity(''); }
            if (hid) { hid.value = farmerId; }
            if (nameEl) { nameEl.textContent = fullName; }
            if (detailsEl) {
                let info = 'ID: ' + farmerId;
                if (barangay) info += ' | ' + barangay;
                if (contactNumber) info += ' | Contact: ' + contactNumber;
                detailsEl.textContent = info;
            }
            if (selBox) { selBox.classList.remove('d-none'); }
            hideAttendanceSuggestions();
        };

        window.clearSelectedAttendanceFarmer = function(skipFocus) {
            const se = searchEl();
            const hid = hiddenEl();
            const selBox = selectedBoxEl();
            const sug = suggestionsEl();
            if (hid) hid.value = '';
            if (se) {
                se.value = '';
                setSearchValidity('Please select a farmer from the list.');
                if (!skipFocus) se.focus();
            }
            if (selBox) selBox.classList.add('d-none');
            if (sug) { sug.innerHTML = ''; sug.style.display = 'none'; }
        };

        document.addEventListener('DOMContentLoaded', function() {
            const sug = suggestionsEl();
            if (sug) {
                sug.addEventListener('mousedown', function(e) {
                    const item = e.target.closest('.attendance-suggestion');
                    if (!item) return;
                    e.preventDefault();
                    window.selectFarmerForAttendance(
                        item.getAttribute('data-id'),
                        item.getAttribute('data-name'),
                        item.getAttribute('data-contact'),
                        item.getAttribute('data-barangay')
                    );
                });
            }

            const se = searchEl();
            if (se) {
                se.addEventListener('focus', function() { setSearchValidity(''); });
            }

            const form = getEl('registerAttendanceForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const hid = hiddenEl();
                    const se = searchEl();
                    if (!hid || !hid.value) {
                        if (se) { setSearchValidity('Please select a farmer from the list.'); se.reportValidity(); }
                        e.preventDefault();
                    }
                });
            }
        });
    })();
    </script>

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
                    <button type="submit" class="btn bg-agri-green text-white border-0">
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
            <div class="modal-header bg-agri-green text-white">
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
                    <button type="submit" class="btn bg-agri-green text-white border-0">
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

<!-- Register Farmer Attendance Modal -->
<div class="modal fade" id="registerAttendanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-agri-green text-white">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2"></i>Register Farmer Attendance
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="registerAttendanceForm" method="POST" action="mao_activities.php">
                <input type="hidden" name="action" value="register_attendance">
                <input type="hidden" name="activity_id" id="register_activity_id">

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-gray-700">Activity</label>
                        <div class="border rounded px-3 py-2 bg-light">
                            <div class="fw-semibold" id="register_activity_title">Activity title</div>
                            <div class="text-muted small">Scheduled on <span id="register_activity_date">Date</span></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-gray-700">Select Farmer <span class="text-danger">*</span></label>
                        <div class="position-relative">
                <input type="text" id="register_farmer_search" class="form-control ps-5" placeholder="Search by last name, first name, or ID..." autocomplete="off" required
                                   oninput="searchFarmersForAttendance(this.value)"
                                   onfocus="showAttendanceSuggestions()"
                                   onblur="hideAttendanceSuggestionsWithDelay()">
                <i class="fas fa-search position-absolute" style="left: 12px; top: 50%; transform: translateY(-50%); color: #6c757d;"></i>
                            <input type="hidden" name="farmer_id" id="register_farmer_id" required>

                            <div id="register_farmer_suggestions" class="position-absolute w-100 bg-white border border-secondary rounded shadow-lg mt-1"
                                 style="max-height: 210px; overflow-y: auto; z-index: 1060; display: none;"></div>
                        </div>
                        <small class="text-muted">Start typing the farmer's last name to search.</small>
                    </div>

                    <div id="register_selected_farmer" class="alert alert-info d-none">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-user-check me-3 mt-1"></i>
                            <div class="flex-grow-1">
                                <div class="fw-semibold" id="register_selected_farmer_name"></div>
                                <div class="small text-muted" id="register_selected_farmer_details"></div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearSelectedAttendanceFarmer()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn bg-agri-green text-white border-0">
                        <i class="fas fa-user-plus me-1"></i>Register Farmer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reschedule Activity Modal -->
<div class="modal fade" id="rescheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-agri-green text-white">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-alt mr-2"></i>Reschedule Activity
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="mao_activities.php">
                <input type="hidden" name="action" value="reschedule">
                <input type="hidden" name="activity_id" id="reschedule_activity_id">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label font-semibold text-gray-700">Activity</label>
                        <p id="reschedule_title" class="form-control-plaintext border rounded px-3 py-2 bg-gray-50 font-semibold"></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label font-semibold text-gray-700">Current Date</label>
                        <input type="date" id="reschedule_current_date" class="form-control bg-gray-100" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reschedule_new_date" class="form-label font-semibold text-gray-700">New Date <span class="text-red-500">*</span></label>
                        <input type="date" name="new_date" id="reschedule_new_date" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reschedule_reason" class="form-label font-semibold text-gray-700">Reason for Rescheduling <span class="text-red-500">*</span></label>
                        <textarea name="reschedule_reason" id="reschedule_reason" class="form-control" rows="3" placeholder="Enter reason for rescheduling..." required maxlength="500"></textarea>
                        <div class="form-text text-muted">Please provide a brief reason (max 500 characters).</div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn bg-agri-green text-white border-0">
                        <i class="fas fa-calendar-alt mr-1"></i>Reschedule Activity
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Mark as Done Confirmation Modal -->
<div class="modal fade" id="markDoneModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-agri-green text-white">
                <h5 class="modal-title">
                    <i class="fas fa-check-circle mr-2"></i>Confirm Mark as Done
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="mao_activities.php">
                <input type="hidden" name="action" value="mark_done">
                <input type="hidden" name="activity_id" id="markdone_activity_id">
                <div class="modal-body">
                    <p class="mb-3">Are you sure you want to mark this activity as <strong>completed</strong>?</p>
                    <div class="border rounded p-3 bg-light">
                        <div class="fw-semibold" id="markdone_title">Activity title</div>
                        <div class="text-muted small">Scheduled on <span id="markdone_date">Date</span></div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0">
                        This action will update the status to <strong>completed</strong> and log the change.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn bg-agri-green text-white border-0">
                        <i class="fas fa-check mr-1"></i>Yes, Mark as Done
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- View Attendance Modal -->
<div class="modal fade" id="viewAttendanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-agri-green text-white">
                <h5 class="modal-title">
                    <i class="fas fa-users mr-2"></i>Activity Attendance
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Activity Info -->
                <div class="border rounded p-3 mb-4 bg-light">
                    <h6 class="fw-bold mb-2" id="attendance_activity_title">Activity Title</h6>
                    <div class="row text-sm">
                        <div class="col-md-6">
                            <i class="fas fa-calendar text-agri-green mr-2"></i>
                            <span id="attendance_activity_date">Date</span>
                        </div>
                        <div class="col-md-6">
                            <i class="fas fa-map-marker-alt text-agri-green mr-2"></i>
                            <span id="attendance_activity_location">Location</span>
                        </div>
                    </div>
                </div>

                <!-- Attendance Count -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Registered Farmers</h6>
                    <span class="badge bg-agri-green" id="attendance_count">0 attendees</span>
                </div>

                <!-- Loading State -->
                <div id="attendance_loading" class="text-center py-5">
                    <div class="spinner-border text-agri-green" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted mt-2">Loading attendance data...</p>
                </div>

                <!-- Error State -->
                <div id="attendance_error" class="alert alert-danger d-none" role="alert">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <span id="attendance_error_message">Error loading attendance data</span>
                </div>

                <!-- No Attendees State -->
                <div id="attendance_empty" class="text-center py-5 d-none">
                    <i class="fas fa-user-slash text-gray-300" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">No farmers have registered for this activity yet.</p>
                </div>

                <!-- Attendees List -->
                <div id="attendance_list" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-uppercase text-muted small fw-semibold">Farmer Name</th>
                                    <th class="text-uppercase text-muted small fw-semibold text-center" style="width: 200px;">Registered On</th>
                                </tr>
                            </thead>
                            <tbody id="attendance_table_body">
                                <!-- Dynamically populated -->
                            </tbody>
                        </table>
                    </div>
                    <div id="attendance_pagination" class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-2 mt-3 d-none">
                        <span class="text-muted small" id="attendance_pagination_info"></span>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="attendance_prev_btn">
                                <i class="fas fa-chevron-left me-1"></i>Previous
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="attendance_next_btn">
                                Next<i class="fas fa-chevron-right ms-1"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>


