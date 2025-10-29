<?php
require_once 'conn.php';
require_once 'check_session.php';
$pageTitle = 'Commodities Management';
include 'includes/layout_start.php';



// Fetch categories
$categories = [];
$res = $conn->query("SELECT category_id, category_name FROM commodity_categories ORDER BY category_name");
while ($row = $res->fetch_assoc()) {
    $categories[] = $row;
}

// Fetch commodities
$commodities = [];
$res = $conn->query("SELECT c.commodity_id, c.commodity_name, cc.category_name FROM commodities c LEFT JOIN commodity_categories cc ON c.category_id = cc.category_id ORDER BY cc.category_name, c.commodity_name");
while ($row = $res->fetch_assoc()) {
    $commodities[] = $row;
}
?>
<div class="container py-6">
    <h2 class="text-2xl font-bold mb-4">Commodities Management</h2>
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
        <div class="bg-white rounded-xl card-shadow p-6 mb-8">
                <h3 class="text-lg font-semibold mb-2">Add New Commodity</h3>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addNewCommodityModal">
                        <i class="fas fa-plus mr-2"></i> Add New Commodity
                </button>
        </div>
</div>

<!-- Add New Commodity Modal (Bootstrap style, matches farmer modal) -->
<div class="modal fade" id="addNewCommodityModal" tabindex="-1" aria-labelledby="addNewCommodityModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
                    <div class="modal-header" style="background-color: #16a34a; color: #fff;">
                        <h5 class="modal-title" id="addNewCommodityModalLabel">
                            <i class="fas fa-seedling me-2"></i>Add New Commodity
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
            <form action="add_new_input.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="new_commodity">
                    <div class="mb-3">
                        <label for="commodity_name" class="form-label">Commodity Name</label>
                        <input type="text" id="commodity_name" name="commodity_name" required class="form-control" placeholder="e.g., Rice, Corn, Vegetables">
                    </div>
                    <div class="mb-3">
                        <label for="commodity_category" class="form-label">Category</label>
                        <select id="commodity_category" name="category_id" required class="form-control">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn" style="background-color: #16a34a; color: #fff;">Add Commodity</button>
                        </div>
            </form>
        </div>
    </div>
</div>


    <div class="bg-white rounded-xl card-shadow p-6">
    <h3 class="text-2xl font-bold mb-4">Current Commodities</h3>
        <div class="mb-4 flex justify-end relative" style="min-width: 16rem;">
            <input type="text" id="commoditySearch" class="form-control w-64" placeholder="Search commodities..." autocomplete="off">
            <ul id="commoditySuggest" class="absolute bg-white border border-gray-300 rounded-md shadow z-10 hidden max-h-48 overflow-y-auto w-64 mt-1 left-auto right-0"></ul>
        </div>
        <div class="overflow-x-auto">
            <table class="table table-bordered w-full" id="commoditiesTable">
                <thead>
                    <tr>
                        <th>Commodity Name</th>
                        <th>Category</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($commodities as $com): ?>
                        <tr data-commodity="<?php echo htmlspecialchars(strtolower($com['commodity_name'])); ?>">
                            <td><?php echo htmlspecialchars($com['commodity_name']); ?></td>
                            <td><?php echo htmlspecialchars($com['category_name']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<script>
// --- Commodity Search & Autosuggest ---
const searchInput = document.getElementById('commoditySearch');
const suggestBox = document.getElementById('commoditySuggest');
const table = document.getElementById('commoditiesTable');
const rows = Array.from(table.querySelectorAll('tbody tr'));
const commodityNames = rows.map(row => row.cells[0].textContent.trim());

searchInput.addEventListener('input', function() {
    const val = this.value.trim().toLowerCase();
    // Filter table rows
    rows.forEach(row => {
        const name = row.getAttribute('data-commodity');
        row.style.display = (!val || name.includes(val)) ? '' : 'none';
    });
    // Autosuggest
    if (val) {
        const matches = commodityNames.filter(n => n.toLowerCase().includes(val));
        if (matches.length > 0) {
            suggestBox.innerHTML = matches.map(m => `<li class='px-3 py-2 cursor-pointer hover:bg-gray-100' tabindex='0'>${m}</li>`).join('');
            suggestBox.classList.remove('hidden');
        } else {
            suggestBox.innerHTML = '<li class="px-3 py-2 text-gray-400">No matches</li>';
            suggestBox.classList.remove('hidden');
        }
    } else {
        suggestBox.classList.add('hidden');
    }
});

// Click or keyboard select on suggestion
suggestBox.addEventListener('mousedown', function(e) {
    if (e.target.tagName === 'LI' && !e.target.classList.contains('text-gray-400')) {
        searchInput.value = e.target.textContent;
        filterTableToCommodity(e.target.textContent);
        suggestBox.classList.add('hidden');
    }
});
suggestBox.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && document.activeElement.tagName === 'LI') {
        searchInput.value = document.activeElement.textContent;
        filterTableToCommodity(document.activeElement.textContent);
        suggestBox.classList.add('hidden');
    }
});

function filterTableToCommodity(name) {
    const val = name.trim().toLowerCase();
    rows.forEach(row => {
        const rowName = row.getAttribute('data-commodity');
        row.style.display = (rowName === val) ? '' : 'none';
    });
}

// Hide suggest box when clicking outside
document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !suggestBox.contains(e.target)) {
        suggestBox.classList.add('hidden');
    }
});
</script>
</div>
