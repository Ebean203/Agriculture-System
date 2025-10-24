<!-- Yield Monitoring Modal -->
<div class="modal fade" id="addVisitModal" tabindex="-1" aria-labelledby="addVisitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="addVisitModalLabel">
                    <i class="fas fa-plus-circle me-2"></i>Record Yield Visit
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="yieldVisitForm" method="POST" action="yield_monitoring.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="record_visit">
                    
                    <!-- Yield Information Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-seedling me-2"></i>Yield Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="farmer_search" class="form-label">Select Farmer <span class="text-danger">*</span></label>
                                    <div class="relative">
                                        <input type="text" id="farmer_search" class="form-control" placeholder="Type farmer name..." autocomplete="off" required>
                                        <input type="hidden" id="farmer_id" name="farmer_id" required>
                                        <div id="farmer_suggestions" class="absolute z-50 w-full bg-white border border-gray-300 rounded-lg shadow-lg hidden max-h-60 overflow-y-auto"></div>
                                    </div>
                                </div>
                                <script>
                                // --- Auto-suggest commodities for selected farmer ---
                                document.addEventListener('DOMContentLoaded', function() {
                                    // --- Auto-suggest farmer search and commodities population ---
                                    const farmerInput = document.getElementById('farmer_search');
                                    const farmerIdInput = document.getElementById('farmer_id');
                                    const farmerSuggestionsEl = document.getElementById('farmer_suggestions');

                                    function clearSuggestions() {
                                        if (farmerSuggestionsEl) {
                                            farmerSuggestionsEl.innerHTML = '';
                                            farmerSuggestionsEl.classList.add('hidden');
                                        }
                                    }

                                    function renderSuggestions(farmers) {
                                        if (!farmerSuggestionsEl) return;
                                        farmerSuggestionsEl.innerHTML = '';
                                        if (!Array.isArray(farmers) || farmers.length === 0) {
                                            const no = document.createElement('div');
                                            no.className = 'px-3 py-2 text-gray-500 text-center';
                                            no.textContent = 'No farmers found';
                                            farmerSuggestionsEl.appendChild(no);
                                            farmerSuggestionsEl.classList.remove('hidden');
                                            return;
                                        }
                                        farmers.forEach(f => {
                                            const item = document.createElement('div');
                                            item.className = 'px-3 py-2 hover:bg-gray-100 cursor-pointer border-b border-gray-200 last:border-b-0 farmer-suggestion-item';
                                            item.innerHTML = `
                                                <div class="font-medium text-gray-900">${f.full_name}</div>
                                                <div class="text-sm text-gray-600">ID: ${f.farmer_id} | ${f.barangay_name || 'N/A'}</div>
                                            `;
                                            item.addEventListener('click', function() {
                                                farmerInput.value = f.full_name;
                                                farmerIdInput.value = f.farmer_id;
                                                clearSuggestions();
                                                fetchFarmerCommodities(f.farmer_id);
                                            });
                                            farmerSuggestionsEl.appendChild(item);
                                        });
                                        farmerSuggestionsEl.classList.remove('hidden');
                                    }

                                    let searchTimeout = null;
                                    if (farmerInput) {
                                        farmerInput.addEventListener('input', function() {
                                            const q = this.value.trim();
                                            farmerIdInput.value = '';
                                            if (searchTimeout) clearTimeout(searchTimeout);
                                            if (q.length < 2) {
                                                clearSuggestions();
                                                return;
                                            }
                                            searchTimeout = setTimeout(function() {
                                                fetch('search_farmers.php?query=' + encodeURIComponent(q))
                                                    .then(res => res.text())
                                                    .then(text => {
                                                        let data = { success: false, farmers: [] };
                                                        try { data = JSON.parse(text); } catch (e) { console.error('Invalid JSON from search_farmers.php', text); }
                                                        if (data.success && Array.isArray(data.farmers)) {
                                                            renderSuggestions(data.farmers);
                                                        } else {
                                                            renderSuggestions([]);
                                                        }
                                                    }).catch(err => {
                                                        console.error('Error searching farmers:', err);
                                                        renderSuggestions([]);
                                                    });
                                            }, 250);
                                        });

                                        // Close suggestions when clicking outside
                                        document.addEventListener('click', function(e) {
                                            if (!farmerSuggestionsEl) return;
                                            if (!farmerSuggestionsEl.contains(e.target) && e.target !== farmerInput) {
                                                clearSuggestions();
                                            }
                                        });
                                    }

                                    function fetchFarmerCommodities(farmerId) {
                                        if (!farmerId) return;
                                        fetch('get_farmer_commodities.php?farmer_id=' + encodeURIComponent(farmerId))
                                            .then(function(res) { if (!res.ok) return res.text().then(t => { throw new Error('Network: ' + res.status + ' ' + t); }); return res.text(); })
                                            .then(function(text) {
                                                let data = { success: false, commodities: [] };
                                                try { data = JSON.parse(text); } catch (e) { console.error('Invalid JSON from get_farmer_commodities.php', text); }
                                                const categoryFilter = document.getElementById('commodity_category_filter');
                                                const commoditySelect = document.getElementById('commodity_id');
                                                if (!commoditySelect) return;
                                                // Reset category filter options (keep 'All Categories')
                                                if (categoryFilter) {
                                                    categoryFilter.disabled = false;
                                                    // keep existing options but clear any attached farmerCommodities
                                                    categoryFilter.farmerCommodities = [];
                                                }
                                                // Clear commodity select except default
                                                commoditySelect.innerHTML = '<option value="">Select Commodity</option>';

                                                if (data.success && Array.isArray(data.commodities) && data.commodities.length > 0) {
                                                    const categories = [];
                                                    const farmerCommodities = [];
                                                    data.commodities.forEach(function(c) {
                                                        const name = c.commodity_name || '';
                                                        if (typeof name !== 'string') return;
                                                        // skip suspicious values like file paths
                                                        if (/^[A-Za-z]:\\/.test(name) || name.indexOf('\\') !== -1) return;
                                                        farmerCommodities.push({ id: c.commodity_id, name: name, category: c.category_name || c.category_id });
                                                        if (categoryFilter) {
                                                            const catName = c.category_name || c.category_id || '';
                                                            if (catName && !categories.includes(catName)) {
                                                                categories.push(catName);
                                                                const opt = document.createElement('option');
                                                                opt.value = catName;
                                                                opt.textContent = catName;
                                                                categoryFilter.appendChild(opt);
                                                            }
                                                        }
                                                    });
                                                    if (categoryFilter) categoryFilter.farmerCommodities = farmerCommodities;
                                                    // Populate commodity select with all farmer commodities (include data-category)
                                                    farmerCommodities.forEach(function(fc) {
                                                        const opt = document.createElement('option');
                                                        opt.value = fc.id;
                                                        opt.textContent = fc.name;
                                                        if (fc.category !== undefined) opt.setAttribute('data-category', fc.category);
                                                        commoditySelect.appendChild(opt);
                                                    });
                                                }
                                            }).catch(function(err) { console.error('Error fetching farmer commodities:', err); });
                                    }
                                });
                                </script>
                                <div class="col-md-6 mb-3">
                                    <label for="commodity_category_filter" class="form-label">Commodity Category</label>
                                    <select class="form-select" id="commodity_category_filter" onchange="filterCommodities()">
                                        <option value="">All Categories</option>
                                        <?php foreach ($commodity_categories as $category): ?>
                                            <option value="<?php echo htmlspecialchars($category['category_id']); ?>" 
                                                    <?php echo $category['category_name'] === 'Agronomic Crops' ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['category_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <!-- Category dropdown will be enabled and dynamically populated by JS in yield_monitoring.php -->
                                    <small class="text-muted">Filter commodities by category</small>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="commodity_id" class="form-label">Commodity <span class="text-danger">*</span></label>
                                    <select class="form-select" id="commodity_id" name="commodity_id" required>
                                        <option value="">Select Commodity</option>
                                        <?php foreach ($commodities as $commodity): ?>
                                            <option value="<?php echo htmlspecialchars($commodity['commodity_id']); ?>" 
                                                    data-category="<?php echo htmlspecialchars($commodity['category_id']); ?>">
                                                <?php echo htmlspecialchars($commodity['commodity_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                            <script>
                            // When a commodity is chosen, auto-select its category
                            document.addEventListener('DOMContentLoaded', function() {
                                var commoditySelect = document.getElementById('commodity_id');
                                var categorySelect = document.getElementById('commodity_category_filter');
                                if (commoditySelect && categorySelect) {
                                    commoditySelect.addEventListener('change', function() {
                                        var selected = commoditySelect.options[commoditySelect.selectedIndex];
                                        var catId = selected.getAttribute('data-category');
                                        if (catId) {
                                            for (var i = 0; i < categorySelect.options.length; i++) {
                                                if (categorySelect.options[i].value == catId) {
                                                    categorySelect.selectedIndex = i;
                                                    break;
                                                }
                                            }
                                        }
                                    });
                                }
                            });
                            </script>
                                </div>
                                <div class="col-md-6 mb-3"></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="season" class="form-label">Season <span class="text-danger">*</span></label>
                                    <select class="form-select" id="season" name="season" required>
                                        <option value="">Select Season</option>
                                        <option value="Dry Season">Dry Season</option>
                                        <option value="Wet Season">Wet Season</option>
                                        <option value="First Cropping">First Cropping</option>
                                        <option value="Second Cropping">Second Cropping</option>
                                        <option value="Third Cropping">Third Cropping</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="yield_amount" class="form-label">Yield Amount <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="yield_amount" name="yield_amount" placeholder="0.00" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="distributed_input" class="form-label">Distributed Input</label>
                                    <select class="form-select" id="distributed_input" name="distributed_input">
                                        <option value="">Select input type...</option>
                                        <option value="Urea">Urea</option>
                                        <option value="Complete">Complete</option>
                                        <option value="Ammonium Sulfate">Ammonium Sulfate</option>
                                        <option value="Organic Fertilizer">Organic Fertilizer</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="visit_date" class="form-label">Visit Date</label>
                                    <input type="date" class="form-control" id="visit_date" name="visit_date">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="unit" class="form-label">Unit</label>
                                    <select class="form-select" id="unit" name="unit">
                                        <option value="">Select unit...</option>
                                        <option value="kg">Kilograms</option>
                                        <option value="bags">Bags</option>
                                        <option value="sacks">Sacks</option>
                                        <option value="heads">Heads</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="quality_grade" class="form-label">Quality Grade</label>
                                    <select class="form-select" id="quality_grade" name="quality_grade">
                                        <option value="">Select grade...</option>
                                        <option value="Grade A">Grade A - Excellent</option>
                                        <option value="Grade B">Grade B - Good</option>
                                        <option value="Grade C">Grade C - Fair</option>
                                        <option value="Grade D">Grade D - Poor</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="growth_stage" class="form-label">Growth Stage</label>
                                    <select class="form-select" id="growth_stage" name="growth_stage">
                                        <option value="">Select stage...</option>
                                        <option value="Seedling">Seedling</option>
                                        <option value="Vegetative">Vegetative</option>
                                        <option value="Flowering">Flowering</option>
                                        <option value="Fruiting">Fruiting</option>
                                        <option value="Mature">Mature</option>
                                        <option value="Harvested">Harvested</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="field_conditions" class="form-label">Field Conditions</label>
                                    <select class="form-select" id="field_conditions" name="field_conditions">
                                        <option value="">Select condition...</option>
                                        <option value="Good Weather">Good Weather</option>
                                        <option value="Adequate Water">Adequate Water</option>
                                        <option value="Pest Issues">Pest Issues</option>
                                        <option value="Disease Present">Disease Present</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label for="visit_notes" class="form-label">Visit Notes</label>
                                    <textarea class="form-control" id="visit_notes" name="visit_notes" rows="2" placeholder="Additional notes..."></textarea>
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
                        <i class="fas fa-save me-1"></i>Record Visit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Record Modal (Enhanced) -->
<div class="modal fade" id="viewRecordModal" tabindex="-1" aria-labelledby="viewRecordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-agri-green text-white">
                <h5 class="modal-title" id="viewRecordModalLabel"><i class="fas fa-eye me-2"></i>Yield Record Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-body p-4">
                        <div class="flex items-center mb-4">
                            <div class="rounded-full bg-agri-light flex items-center justify-center mr-3" style="width:48px;height:48px;">
                                <i class="fas fa-user text-agri-green fa-lg"></i>
                            </div>
                            <div>
                                <div class="font-bold text-lg text-gray-900" id="view_farmer"></div>
                                <div class="text-sm text-gray-500" id="view_commodity"></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-2">
                            <div class="bg-blue-50 rounded-lg p-3 flex items-center">
                                <i class="fas fa-calendar-alt text-blue-600 mr-2"></i>
                                <span class="font-semibold">Season:</span>
                                <span class="ml-2" id="view_season"></span>
                            </div>
                            <div class="bg-green-50 rounded-lg p-3 flex items-center">
                                <i class="fas fa-sack text-green-600 mr-2"></i>
                                <span class="font-semibold">Yield:</span>
                                <span class="ml-2" id="view_yield_amount"></span>
                                <span class="ml-2" id="view_unit"></span>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-2">
                            <div class="bg-yellow-50 rounded-lg p-3 flex items-center">
                                <i class="fas fa-calendar-day text-yellow-600 mr-2"></i>
                                <span class="font-semibold">Visit Date:</span>
                                <span class="ml-2" id="view_visit_date"></span>
                            </div>
                            <div class="bg-purple-50 rounded-lg p-3 flex items-center">
                                <i class="fas fa-flask text-purple-600 mr-2"></i>
                                <span class="font-semibold">Distributed Input:</span>
                                <span class="ml-2" id="view_distributed_input"></span>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-2">
                            <div class="bg-indigo-50 rounded-lg p-3 flex items-center">
                                <i class="fas fa-star text-indigo-600 mr-2"></i>
                                <span class="font-semibold">Quality Grade:</span>
                                <span class="ml-2" id="view_quality_grade"></span>
                            </div>
                            <div class="bg-pink-50 rounded-lg p-3 flex items-center">
                                <i class="fas fa-seedling text-pink-600 mr-2"></i>
                                <span class="font-semibold">Growth Stage:</span>
                                <span class="ml-2" id="view_growth_stage"></span>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-2">
                            <div class="bg-orange-50 rounded-lg p-3 flex items-center">
                                <i class="fas fa-cloud-sun text-orange-600 mr-2"></i>
                                <span class="font-semibold">Field Conditions:</span>
                                <span class="ml-2" id="view_field_conditions"></span>
                            </div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3 mt-2">
                            <i class="fas fa-sticky-note text-gray-600 mr-2"></i>
                            <span class="font-semibold">Notes:</span>
                            <span class="ml-2" id="view_visit_notes"></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>



<!-- Edit Record Modal (Simplified Color Palette) -->
<div class="modal fade" id="editRecordModal" tabindex="-1" aria-labelledby="editRecordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-agri-green text-white">
                <h5 class="modal-title" id="editRecordModalLabel"><i class="fas fa-edit me-2"></i>Edit Yield Record</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editRecordForm" method="POST" action="yield_monitoring.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_record">
                    <input type="hidden" name="record_id" id="edit_record_id">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-agri-green">Farmer</label>
                            <input type="text" class="form-control bg-agri-light border-agri-green text-agri-dark" id="edit_farmer_name" disabled>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Commodity</label>
                            <select class="form-select" id="edit_commodity_id" name="commodity_id">
                                <option value="">Select Commodity</option>
                                <?php foreach ($commodities as $commodity): ?>
                                    <option value="<?php echo htmlspecialchars($commodity['commodity_id']); ?>"><?php echo htmlspecialchars($commodity['commodity_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <script>
                            // Build a map of category_id -> category_name for client-side logic
                            const categoryMap = {
                                <?php foreach ($commodity_categories as $cat): ?>
                                    '<?php echo $cat['category_id']; ?>': '<?php echo addslashes($cat['category_name']); ?>',
                                <?php endforeach; ?>
                            };

                            function setSeasonOptionsForCategory(selectEl, categoryName) {
                                if (!selectEl) return;
                                // agronomic and high-value: Dry/Wet + First..Fifth Cropping
                                const agronomicNames = ['Agronomic Crops', 'High Value Crops'];
                                let options = [];
                                if (agronomicNames.includes(categoryName)) {
                                    options = [
                                        ['', 'Select Season'],
                                        ['Dry Season','Dry Season'],
                                        ['Wet Season','Wet Season'],
                                        ['First Cropping','First Cropping'],
                                        ['Second Cropping','Second Cropping'],
                                        ['Third Cropping','Third Cropping'],
                                        ['Fourth Cropping','Fourth Cropping'],
                                        ['Fifth Cropping','Fifth Cropping']
                                    ];
                                } else {
                                    // livestock/poultry and other: Batch/Flock 1..3
                                    options = [
                                        ['', 'Select Season'],
                                        ['Batch/Flock 1','Batch/Flock 1 (or Cycle 1)'],
                                        ['Batch/Flock 2','Batch/Flock 2 (or Cycle 2)'],
                                        ['Batch/Flock 3','Batch/Flock 3 (or Cycle 3)']
                                    ];
                                }
                                // Replace options
                                selectEl.innerHTML = '';
                                options.forEach(function(opt) {
                                    const el = document.createElement('option');
                                    el.value = opt[0];
                                    el.textContent = opt[1];
                                    selectEl.appendChild(el);
                                });
                            }

                            // Watch commodity select change to auto-update season
                            document.addEventListener('DOMContentLoaded', function() {
                                const commoditySelect = document.getElementById('commodity_id');
                                const seasonSelect = document.getElementById('season');
                                const editCommoditySelect = document.getElementById('edit_commodity_id');
                                const editSeasonSelect = document.getElementById('edit_season');

                                function updateForSelectedCommodity(sel, seasonSel) {
                                    if (!sel || !seasonSel) return;
                                    const opt = sel.options[sel.selectedIndex];
                                    if (!opt) return;
                                    const catId = opt.getAttribute('data-category') || '';
                                    const catName = categoryMap[catId] || '';
                                    setSeasonOptionsForCategory(seasonSel, catName);
                                }

                                if (commoditySelect && seasonSelect) {
                                    commoditySelect.addEventListener('change', function() {
                                        updateForSelectedCommodity(commoditySelect, seasonSelect);
                                    });
                                }
                                if (editCommoditySelect && editSeasonSelect) {
                                    editCommoditySelect.addEventListener('change', function() {
                                        updateForSelectedCommodity(editCommoditySelect, editSeasonSelect);
                                    });
                                }

                                // Also ensure when edit modal populates, the edit season options are set appropriately
                                // This will run whenever edit buttons populate fields (we already set edit_commodity_id value there)
                                // Listen to a small custom event to refresh edit season
                                document.addEventListener('refreshEditSeason', function() {
                                    updateForSelectedCommodity(editCommoditySelect, editSeasonSelect);
                                });
                            });
                            </script>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Season</label>
                            <select class="form-select" id="edit_season" name="season">
                                <option value="">Select Season</option>
                                <option value="Dry Season">Dry Season</option>
                                <option value="Wet Season">Wet Season</option>
                                <option value="First Cropping">First Cropping</option>
                                <option value="Second Cropping">Second Cropping</option>
                                <option value="Third Cropping">Third Cropping</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Yield Amount</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="edit_yield_amount" name="yield_amount">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Unit</label>
                            <select class="form-select" id="edit_unit" name="unit">
                                <option value="">Select unit...</option>
                                <option value="kg">Kilograms</option>
                                <option value="bags">Bags</option>
                                <option value="sacks">Sacks</option>
                                <option value="heads">Heads</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Visit Date</label>
                            <input type="date" class="form-control" id="edit_visit_date" name="visit_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Distributed Input</label>
                            <input type="text" class="form-control" id="edit_distributed_input" name="distributed_input">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quality Grade</label>
                            <select class="form-select" id="edit_quality_grade" name="quality_grade">
                                <option value="">Select grade...</option>
                                <option value="Grade A">Grade A - Excellent</option>
                                <option value="Grade B">Grade B - Good</option>
                                <option value="Grade C">Grade C - Fair</option>
                                <option value="Grade D">Grade D - Poor</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Growth Stage</label>
                            <select class="form-select" id="edit_growth_stage" name="growth_stage">
                                <option value="">Select stage...</option>
                                <option value="Seedling">Seedling</option>
                                <option value="Vegetative">Vegetative</option>
                                <option value="Flowering">Flowering</option>
                                <option value="Fruiting">Fruiting</option>
                                <option value="Mature">Mature</option>
                                <option value="Harvested">Harvested</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label">Field Conditions</label>
                            <input type="text" class="form-control" id="edit_field_conditions" name="field_conditions">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label">Visit Notes</label>
                            <textarea class="form-control" id="edit_visit_notes" name="visit_notes" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary bg-gray-500 text-white border-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn bg-agri-green text-white border-0"><i class="fas fa-save me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Populate View and Edit modals when action buttons are clicked
document.addEventListener('DOMContentLoaded', function() {
    // Handler for view buttons
    document.querySelectorAll('.btn-view-record').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const record = JSON.parse(this.getAttribute('data-record') || '{}');
            document.getElementById('view_farmer').textContent = (record.first_name || '') + ' ' + (record.last_name || '');
            document.getElementById('view_commodity').textContent = record.commodity_name || '';
            document.getElementById('view_season').textContent = record.season || '';
            const viewUnit = record.unit || '';
            document.getElementById('view_yield_amount').textContent = (record.yield_amount !== undefined) ? Number(record.yield_amount).toFixed(2) + (viewUnit ? ' ' + viewUnit : '') : '';
            document.getElementById('view_unit').textContent = viewUnit;
            document.getElementById('view_visit_date').textContent = record.visit_date ? new Date(record.visit_date).toLocaleDateString() : '';
            document.getElementById('view_distributed_input').textContent = record.distributed_input || '';
            document.getElementById('view_quality_grade').textContent = record.quality_grade || '';
            document.getElementById('view_growth_stage').textContent = record.growth_stage || '';
            document.getElementById('view_field_conditions').textContent = record.field_conditions || '';
            document.getElementById('view_visit_notes').textContent = record.visit_notes || '';
        });
    });


    // Handler for edit buttons - populate the form and filter commodities
    document.querySelectorAll('.btn-edit-record').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const record = JSON.parse(this.getAttribute('data-record') || '{}');
            const recId = record.yield_id || record.id || record.yield_monitoring_id || record.ym_id || record.record_id || '';
            document.getElementById('edit_record_id').value = recId;
            document.getElementById('edit_farmer_name').value = (record.first_name || '') + ' ' + (record.last_name || '');

            // Fetch commodities for this farmer via AJAX
            fetch('get_farmer_commodities.php?farmer_id=' + encodeURIComponent(record.farmer_id))
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('edit_commodity_id');
                    select.innerHTML = '<option value="">Select Commodity</option>';
                        if (data.success && Array.isArray(data.commodities)) {
                        data.commodities.forEach(function(commodity) {
                            const opt = document.createElement('option');
                            opt.value = commodity.commodity_id;
                            opt.textContent = commodity.commodity_name;
                            // preserve category info for client-side season logic
                            if (commodity.category_id !== undefined) opt.setAttribute('data-category', commodity.category_id);
                            else if (commodity.category_name !== undefined) opt.setAttribute('data-category', commodity.category_name);
                            select.appendChild(opt);
                        });
                        // Set selected value
                        select.value = record.commodity_id || '';
                        // Dispatch event so season options update for the selected commodity
                        document.dispatchEvent(new Event('refreshEditSeason'));
                    } else {
                        // Fallback: show only the current commodity
                        if (record.commodity_id && record.commodity_name) {
                            const opt = document.createElement('option');
                            opt.value = record.commodity_id;
                            opt.textContent = record.commodity_name;
                            if (record.category_id !== undefined) opt.setAttribute('data-category', record.category_id);
                            else if (record.category_name !== undefined) opt.setAttribute('data-category', record.category_name);
                            select.appendChild(opt);
                            select.value = record.commodity_id;
                        }
                    }
                });

            // Set season after options have been refreshed. Use a small timeout to allow the refresh handler to run.
            setTimeout(function() {
                if (document.getElementById('edit_season')) {
                    // If the stored season value exists in options, select it; otherwise leave default
                    var seasonVal = record.season || '';
                    var seasonSel = document.getElementById('edit_season');
                    for (var i = 0; i < seasonSel.options.length; i++) {
                        if (seasonSel.options[i].value === seasonVal) {
                            seasonSel.selectedIndex = i;
                            break;
                        }
                    }
                }
            }, 150);
            document.getElementById('edit_yield_amount').value = record.yield_amount || '';
            document.getElementById('edit_unit').value = record.unit || '';
            document.getElementById('edit_visit_date').value = record.visit_date ? record.visit_date.split(' ')[0] : '';
            document.getElementById('edit_distributed_input').value = record.distributed_input || '';
            document.getElementById('edit_quality_grade').value = record.quality_grade || '';
            document.getElementById('edit_growth_stage').value = record.growth_stage || '';
            document.getElementById('edit_field_conditions').value = record.field_conditions || '';
            document.getElementById('edit_visit_notes').value = record.visit_notes || '';
        });
    });

    // Handle edit form submission via AJAX
    document.getElementById('editRecordForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        const submitBtn = form.querySelector('button[type="submit"]');
        const origText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';
        submitBtn.disabled = true;

        const formData = new FormData(form);
        // Ensure action is set
        formData.set('action', 'edit_record');

        fetch(window.location.pathname + window.location.search, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the table row in-place if present
                const updated = data.record || {};
                const recId = updated.yield_id || updated.id || formData.get('record_id');
                if (recId) {
                    const row = document.querySelector('tr[data-record-id="' + recId + '"]');
                    if (row) {
                        // Update displayed columns: Farmer name, Commodity, Season, Yield Amount, Date
                        const farmerCell = row.querySelector('td:nth-child(1) .text-sm.font-medium');
                        if (farmerCell && updated.first_name) farmerCell.textContent = (updated.first_name || '') + ' ' + (updated.last_name || '');
                        const commodityCell = row.querySelector('td:nth-child(2) .text-sm');
                        if (commodityCell && updated.commodity_name) commodityCell.textContent = updated.commodity_name;
                        const seasonCell = row.querySelector('td:nth-child(3) .inline-flex');
                        if (seasonCell && updated.season) seasonCell.textContent = updated.season;
                        const yieldCell = row.querySelector('td:nth-child(4) .text-sm.font-medium');
                        if (yieldCell && updated.yield_amount !== undefined) yieldCell.textContent = Number(updated.yield_amount).toFixed(2) + (updated.unit ? ' ' + updated.unit : '');
                        const dateCell = row.querySelector('td:nth-child(5)');
                        if (dateCell && updated.record_date) dateCell.textContent = new Date(updated.record_date).toLocaleDateString();
                        // Also update the data-record attribute on action buttons so future edits use latest data
                        const viewBtn = row.querySelector('.btn-view-record');
                        const editBtn = row.querySelector('.btn-edit-record');
                        if (viewBtn) viewBtn.setAttribute('data-record', JSON.stringify(updated));
                        if (editBtn) editBtn.setAttribute('data-record', JSON.stringify(updated));
                    }
                }

                // Close modal
                const modalEl = document.getElementById('editRecordModal');
                const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                modal.hide();

                // Optionally show a small success alert
                const successDiv = document.createElement('div');
                successDiv.className = 'bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6';
                successDiv.innerHTML = '<i class="fas fa-check-circle mr-2"></i>' + (data.message || 'Record updated');
                const main = document.querySelector('main.app-content');
                if (main) main.prepend(successDiv);
                setTimeout(() => { successDiv.remove(); }, 2500);
            } else {
                alert('Error updating record: ' + (data.message || 'Unknown error'));
            }
        }).catch(err => {
            console.error('Edit request failed', err);
            alert('Request failed. See console for details.');
        }).finally(() => {
            submitBtn.innerHTML = origText;
            submitBtn.disabled = false;
        });
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Ensure alias elements exist for index.php quick-actions code
    var farmerSearch = document.getElementById('farmer_search');
    var farmerId = document.getElementById('farmer_id');

    if (!document.getElementById('farmer_name_yield')) {
        var aliasName = document.createElement('input');
        aliasName.type = 'hidden';
        aliasName.id = 'farmer_name_yield';
        farmerSearch && farmerSearch.parentNode && farmerSearch.parentNode.appendChild(aliasName);
    }
    if (!document.getElementById('selected_farmer_id_yield')) {
        var aliasId = document.createElement('input');
        aliasId.type = 'hidden';
        aliasId.id = 'selected_farmer_id_yield';
        farmerSearch && farmerSearch.parentNode && farmerSearch.parentNode.appendChild(aliasId);
    }
    if (!document.getElementById('farmer_suggestions_yield')) {
        var aliasSug = document.createElement('div');
        aliasSug.id = 'farmer_suggestions_yield';
        aliasSug.className = 'absolute z-50 w-full bg-white border border-gray-300 rounded-lg shadow-lg hidden max-h-60 overflow-y-auto';
        // place after existing suggestions container if present
        var existing = document.getElementById('farmer_suggestions');
        if (existing && existing.parentNode) existing.parentNode.appendChild(aliasSug);
        else farmerSearch && farmerSearch.parentNode && farmerSearch.parentNode.appendChild(aliasSug);
    }

    // When typing in the modal's search field, prefer index's search function if available
    if (farmerSearch) {
        farmerSearch.addEventListener('input', function(e) {
            var q = (this.value || '').trim();
            // keep alias in sync
            var aliasName = document.getElementById('farmer_name_yield');
            if (aliasName) aliasName.value = q;

            // If the dashboard search function exists, delegate to it so it populates the expected suggestions container
            if (typeof window.searchFarmersYield === 'function') {
                try { window.searchFarmersYield(q); } catch (err) { console.error('dashboard searchFarmersYield error', err); }
            } else {
                // Fallback: if no dashboard search, do nothing — the modal already has its own search implementation
            }
        });
    }

    // Wrap or augment selectFarmerYield so selecting a suggestion (from dashboard suggestion UI) also updates this modal
    var originalSelect = window.selectFarmerYield;
    window.selectFarmerYield = function(farmerIdVal, farmerNameVal) {
        try {
            if (typeof originalSelect === 'function') originalSelect(farmerIdVal, farmerNameVal);
        } catch (e) {
            console.error('original selectFarmerYield error', e);
        }
        try {
            // Sync values into this modal's fields
            var farmerSearchEl = document.getElementById('farmer_search');
            var farmerIdEl = document.getElementById('farmer_id');
            if (farmerSearchEl) farmerSearchEl.value = farmerNameVal || '';
            if (farmerIdEl) farmerIdEl.value = farmerIdVal || '';
            var aliasName = document.getElementById('farmer_name_yield'); if (aliasName) aliasName.value = farmerNameVal || '';
            var aliasId = document.getElementById('selected_farmer_id_yield'); if (aliasId) aliasId.value = farmerIdVal || '';
            // Hide suggestions containers
            var s1 = document.getElementById('farmer_suggestions'); if (s1) s1.classList.add('hidden');
            var s2 = document.getElementById('farmer_suggestions_yield'); if (s2) s2.classList.add('hidden');
            // Trigger commodity population for this modal
            if (typeof fetchFarmerCommodities === 'function') {
                try { fetchFarmerCommodities(farmerIdVal); } catch (e) { console.error('fetchFarmerCommodities error', e); }
            }
        } catch (e) { console.error('selectFarmerYield wrapper error', e); }
    };
});
</script>