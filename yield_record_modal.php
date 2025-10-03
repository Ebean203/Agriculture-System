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
                                        <option value="tons">Tons</option>
                                        <option value="pieces">Pieces</option>
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