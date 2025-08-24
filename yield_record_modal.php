<!-- Record Yield Modal -->
<div id="addVisitModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[95vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
            <h3 class="text-xl font-semibold text-gray-900 flex items-center">
                <i class="fas fa-clipboard-check text-agri-green mr-3"></i>Record Yield Visit
            </h3>
            <p class="text-sm text-gray-600 mt-1">Document yield results and farmer progress</p>
        </div>
        <form method="POST" action="record_yield.php">
            <div class="px-6 py-6">
                <!-- Visit Information Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="farmer_name_yield" class="block text-sm font-medium text-gray-700 mb-2">Select Farmer</label>
                        <div class="relative">
                            <input type="text" 
                                   class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" 
                                   id="farmer_name_yield" 
                                   placeholder="Type farmer name..."
                                   autocomplete="off"
                                   required
                                   onkeyup="searchFarmersYield(this.value)"
                                   onfocus="showSuggestionsYield()"
                                   onblur="hideSuggestionsYield()">
                            <input type="hidden" name="farmer_id" id="selected_farmer_id_yield" required>
                            
                            <!-- Suggestions dropdown -->
                            <div id="farmer_suggestions_yield" class="absolute z-50 w-full bg-white border border-gray-300 rounded-lg shadow-lg mt-1 max-h-60 overflow-y-auto hidden">
                                <!-- Suggestions will be populated here -->
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 mt-1">Start typing to search for farmers</p>
                    </div>
                    <div>
                        <label for="input_select" class="block text-sm font-medium text-gray-700 mb-2">Distributed Input</label>
                        <select class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" name="input_id" required>
                            <option value="">Select input type...</option>
                            <!-- Options will be populated based on farmer selection -->
                        </select>
                    </div>
                </div>
                
                <!-- Visit Details -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <div>
                        <label for="visit_date" class="block text-sm font-medium text-gray-700 mb-2">Visit Date</label>
                        <input type="date" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" name="visit_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div>
                        <label for="yield_amount" class="block text-sm font-medium text-gray-700 mb-2">Yield Amount</label>
                        <input type="number" step="0.1" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" name="yield_amount" placeholder="0.0" required>
                    </div>
                    <div>
                        <label for="yield_unit" class="block text-sm font-medium text-gray-700 mb-2">Unit</label>
                        <select class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" name="yield_unit" required>
                            <option value="">Select unit...</option>
                            <option value="sacks">Sacks</option>
                            <option value="kilograms">Kilograms</option>
                            <option value="tons">Tons</option>
                            <option value="pieces">Pieces</option>
                            <option value="heads">Heads</option>
                        </select>
                    </div>
                </div>
                
                <!-- Quality and Assessment -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="quality_grade" class="block text-sm font-medium text-gray-700 mb-2">Quality Grade</label>
                        <select class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" name="quality_grade" required>
                            <option value="">Select grade...</option>
                            <option value="Grade A">Grade A - Excellent</option>
                            <option value="Grade B">Grade B - Good</option>
                            <option value="Grade C">Grade C - Fair</option>
                            <option value="Grade D">Grade D - Poor</option>
                        </select>
                    </div>
                    <div>
                        <label for="growth_stage" class="block text-sm font-medium text-gray-700 mb-2">Growth Stage</label>
                        <select class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" name="growth_stage">
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
                
                <!-- Conditions and Notes -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Field Conditions</label>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="conditions[]" value="good_weather" class="mr-2">
                            <span class="text-sm">Good Weather</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="conditions[]" value="adequate_water" class="mr-2">
                            <span class="text-sm">Adequate Water</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="conditions[]" value="pest_issues" class="mr-2">
                            <span class="text-sm">Pest Issues</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="conditions[]" value="disease_present" class="mr-2">
                            <span class="text-sm">Disease Present</span>
                        </label>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label for="visit_notes" class="block text-sm font-medium text-gray-700 mb-2">Visit Notes</label>
                    <textarea class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" name="visit_notes" rows="4" placeholder="Record observations, recommendations, and any issues noted during the visit..."></textarea>
                </div>
                
                <!-- Recommendations -->
                <div class="mb-6">
                    <label for="recommendations" class="block text-sm font-medium text-gray-700 mb-2">Recommendations</label>
                    <textarea class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" name="recommendations" rows="3" placeholder="Provide recommendations for improvement or next steps..."></textarea>
                </div>
            </div>
            <div class="px-6 py-4 bg-gray-50 flex justify-end space-x-4 rounded-b-lg sticky bottom-0 border-t border-gray-200 z-10">
                <button type="button" class="px-6 py-3 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors font-medium" onclick="closeYieldModal()">
                    <i class="fas fa-times mr-2"></i>Cancel
                </button>
                <button type="submit" class="px-6 py-3 bg-agri-green text-white rounded-lg hover:bg-agri-dark transition-colors font-medium">
                    <i class="fas fa-save mr-2"></i>Record Visit
                </button>
            </div>
        </form>
    </div>
</div>
