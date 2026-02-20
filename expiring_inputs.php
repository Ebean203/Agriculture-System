<?php
session_start();
require_once 'check_session.php';
require_once 'conn.php';
require_once 'includes/activity_logger.php';
require_once 'includes/notification_system.php';

$pageTitle = 'Expiring Batches - Lagonglong FARMS';

// Fetch batches with expiration dates
// Optional filters from notifications
$filter_inventory_id = isset($_GET['inventory_id']) ? (int)$_GET['inventory_id'] : 0;
$filter_input_id = isset($_GET['input_id']) ? (int)$_GET['input_id'] : 0;

$query = "SELECT mi.inventory_id, mi.input_id, ic.input_name, ic.unit, mi.quantity_on_hand, mi.expiration_date, mi.is_expired, ic.total_stock
          FROM mao_inventory mi
          JOIN input_categories ic ON mi.input_id = ic.input_id
          WHERE mi.expiration_date IS NOT NULL AND mi.quantity_on_hand > 0";
if ($filter_inventory_id > 0) {
    $query .= " AND mi.inventory_id = " . $filter_inventory_id;
} elseif ($filter_input_id > 0) {
    $query .= " AND mi.input_id = " . $filter_input_id;
}
$query .= " ORDER BY mi.expiration_date ASC";
// Fetch batches
$res = mysqli_query($conn, $query);
if (!$res) {
    die('Query failed: ' . mysqli_error($conn));
}

// Collect all rows first to compute summary counts
$batches = [];
while ($row = mysqli_fetch_assoc($res)) {
    $days_until = (int) ((strtotime($row['expiration_date']) - strtotime(date('Y-m-d'))) / 86400);
    $status = 'normal';
    if ($days_until < 0) $status = 'expired';
    elseif ($days_until <= 10) $status = 'warning';
    $row['days_until'] = $days_until;
    $row['status']     = $status;
    $batches[]         = $row;
}
$count_expired = count(array_filter($batches, fn($b) => $b['status'] === 'expired'));
$count_warning = count(array_filter($batches, fn($b) => $b['status'] === 'warning'));
$count_normal  = count(array_filter($batches, fn($b) => $b['status'] === 'normal'));

include 'includes/layout_start.php';
?>
<style>
    .batch-card { border-left: 5px solid #e5e7eb; transition: background-color 0.15s; }
    .batch-card.expired  { border-left-color: #dc2626; background: #fff5f5; }
    .batch-card.expired:hover  { background: #fee2e2; }
    .batch-card.warning  { border-left-color: #d97706; background: #fffbeb; }
    .batch-card.warning:hover  { background: #fef3c7; }
    .batch-card.normal   { border-left-color: #16a34a; background: #f0fdf4; }
    .batch-card.normal:hover   { background: #dcfce7; }
    .stat-card { border-radius: 12px; padding: 18px 24px; display:flex; align-items:center; gap:16px; cursor:pointer; transition: transform 0.15s, box-shadow 0.15s; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,0.10); }
    .stat-card.active-filter { outline: 3px solid currentColor; box-shadow: 0 4px 16px rgba(0,0,0,0.13); transform: translateY(-2px); }
    .stat-card.active-filter.red-card  { outline-color: #dc2626; }
    .stat-card.active-filter.yellow-card { outline-color: #d97706; }
    .stat-card.active-filter.green-card  { outline-color: #16a34a; }
</style>

<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">

    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex items-center gap-3 mb-1">
            <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center">
                <i class="fas fa-clock text-red-600 text-lg"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Expiring Batches</h1>
                <p class="text-sm text-gray-500">Batches with remaining stock, ordered by earliest expiration date</p>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-3 gap-4 mb-8">
        <div class="stat-card red-card bg-red-50 border border-red-200" data-filter="expired" title="Click to show only expired batches">
            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-times-circle text-red-600"></i>
            </div>
            <div>
                <div class="text-2xl font-bold text-red-700"><?php echo $count_expired; ?></div>
                <div class="text-xs font-medium text-red-600 uppercase tracking-wide">Expired</div>
                <div class="text-xs text-red-400 mt-0.5">Click to filter</div>
            </div>
        </div>
        <div class="stat-card yellow-card bg-yellow-50 border border-yellow-200" data-filter="warning" title="Click to show only expiring-soon batches">
            <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-yellow-600"></i>
            </div>
            <div>
                <div class="text-2xl font-bold text-yellow-700"><?php echo $count_warning; ?></div>
                <div class="text-xs font-medium text-yellow-600 uppercase tracking-wide">Expiring Soon <span class="normal-case font-normal text-gray-400">(&le;10 days)</span></div>
                <div class="text-xs text-yellow-400 mt-0.5">Click to filter</div>
            </div>
        </div>
        <div class="stat-card green-card bg-green-50 border border-green-200" data-filter="normal" title="Click to show only within-date batches">
            <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-check-circle text-green-600"></i>
            </div>
            <div>
                <div class="text-2xl font-bold text-green-700"><?php echo $count_normal; ?></div>
                <div class="text-xs font-medium text-green-600 uppercase tracking-wide">Within Date</div>
                <div class="text-xs text-green-400 mt-0.5">Click to filter</div>
            </div>
        </div>
    </div>

    <!-- Batch List -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold text-gray-800 text-base">
                <i class="fas fa-boxes text-gray-400 mr-2"></i>Batch Records
                <span id="filterLabel" class="ml-2 text-xs font-normal text-gray-400 hidden"></span>
            </h2>
            <div class="flex items-center gap-3">
                <span id="batchCount" class="text-xs text-gray-400"><?php echo count($batches); ?> batch<?php echo count($batches) !== 1 ? 'es' : ''; ?> found</span>
                <button id="clearFilter" class="hidden text-xs text-blue-500 hover:text-blue-700 underline" onclick="applyFilter(null)">Clear filter</button>
            </div>
        </div>

        <?php if (empty($batches)): ?>
        <div class="flex flex-col items-center justify-center py-16 text-center">
            <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                <i class="fas fa-check-double text-gray-400 text-2xl"></i>
            </div>
            <p class="text-gray-700 font-medium text-base">No expiring batches</p>
            <p class="text-gray-400 text-sm mt-1">All batches either have no expiration date or have zero quantity.</p>
        </div>
        <?php else: ?>
        <div class="divide-y divide-gray-100">
            <?php foreach ($batches as $row):
                $status    = $row['status'];
                $days_until = $row['days_until'];
                $badgeMap  = [
                    'expired' => ['label' => 'EXPIRED',       'cls' => 'bg-red-100 text-red-700'],
                    'warning' => ['label' => 'EXPIRING SOON', 'cls' => 'bg-yellow-100 text-yellow-700'],
                    'normal'  => ['label' => 'OK',            'cls' => 'bg-green-100 text-green-700'],
                ];
                $badge = $badgeMap[$status];
                $iconMap = [
                    'expired' => 'fas fa-times-circle text-red-500',
                    'warning' => 'fas fa-exclamation-triangle text-yellow-500',
                    'normal'  => 'fas fa-check-circle text-green-500',
                ];
                $disabled = !empty($row['is_expired']) ? 'disabled' : '';
            ?>
            <div class="batch-card <?php echo $status; ?> px-4 py-4 flex items-center justify-between gap-4">
                <div class="flex items-start gap-4 min-w-0">
                    <div class="mt-1 flex-shrink-0">
                        <i class="<?php echo $iconMap[$status]; ?> text-xl"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-base font-semibold text-gray-900"><?php echo htmlspecialchars($row['input_name']); ?></span>
                            <span class="text-xs text-gray-400 font-normal">(<?php echo htmlspecialchars($row['unit']); ?>)</span>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?php echo $badge['cls']; ?>"><?php echo $badge['label']; ?></span>
                            <?php if (!empty($row['is_expired'])): ?>
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-gray-200 text-gray-600">Marked Expired</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex flex-wrap gap-x-4 mt-1 text-sm text-gray-500">
                            <span><i class="fas fa-tag mr-1 text-gray-300"></i>Batch #<?php echo $row['inventory_id']; ?></span>
                            <span><i class="fas fa-cubes mr-1 text-gray-300"></i><?php echo intval($row['quantity_on_hand']); ?> <?php echo htmlspecialchars($row['unit']); ?> remaining</span>
                            <span><i class="fas fa-layer-group mr-1 text-gray-300"></i><?php echo intval($row['total_stock']); ?> total stock</span>
                        </div>
                        <div class="mt-1 text-sm">
                            <i class="fas fa-calendar-alt mr-1 text-gray-300"></i>
                            <span class="text-gray-600">Expires <strong><?php echo date('M d, Y', strtotime($row['expiration_date'])); ?></strong></span>
                            <?php if ($days_until < 0): ?>
                                <span class="ml-2 text-red-500 text-xs font-medium"><?php echo abs($days_until); ?> day<?php echo abs($days_until) !== 1 ? 's' : ''; ?> ago</span>
                            <?php elseif ($days_until === 0): ?>
                                <span class="ml-2 text-red-500 text-xs font-medium">Expires today</span>
                            <?php else: ?>
                                <span class="ml-2 text-gray-400 text-xs">in <?php echo $days_until; ?> day<?php echo $days_until !== 1 ? 's' : ''; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="flex-shrink-0">
                    <button class="stockout-btn inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold
                        <?php echo $disabled ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-red-600 hover:bg-red-700 text-white transition-colors'; ?>"
                        <?php echo $disabled; ?>
                        data-inventory-id="<?php echo $row['inventory_id']; ?>"
                        data-input-name="<?php echo htmlspecialchars($row['input_name']); ?>"
                        data-quantity="<?php echo intval($row['quantity_on_hand']); ?>"
                        data-days-until="<?php echo $days_until; ?>">
                        <i class="fas fa-arrow-down text-xs"></i> Stock Out
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <!-- Pagination bar (shown by JS when needed) -->
        <div id="paginationBar" class="hidden px-6 py-4 border-t border-gray-100 flex items-center justify-between gap-4">
            <span id="paginationInfo" class="text-xs text-gray-500"></span>
            <div id="paginationButtons" class="flex items-center gap-1"></div>
        </div>
    </div>
</div>

<script>
// ── Pagination + KPI filter ──────────────────────────────────────────────────
const PER_PAGE    = 10;
let activeFilter  = null;
let currentPage   = 1;
const filterLabels = { expired: 'Showing: Expired', warning: 'Showing: Expiring Soon', normal: 'Showing: Within Date' };

function getVisibleCards() {
    const all = Array.from(document.querySelectorAll('.batch-card'));
    if (!activeFilter) return all;
    return all.filter(c => c.classList.contains(activeFilter));
}

function renderPage() {
    const visible    = getVisibleCards();
    const total      = visible.length;
    const totalPages = Math.ceil(total / PER_PAGE) || 1;
    if (currentPage > totalPages) currentPage = totalPages;

    // Hide all, show current page slice
    document.querySelectorAll('.batch-card').forEach(c => c.style.display = 'none');
    const start = (currentPage - 1) * PER_PAGE;
    visible.slice(start, start + PER_PAGE).forEach(c => c.style.display = '');

    // Update count label
    const countEl = document.getElementById('batchCount');
    if (countEl) countEl.textContent = total + ' batch' + (total !== 1 ? 'es' : '') + ' found';

    // Pagination bar
    const bar     = document.getElementById('paginationBar');
    const info    = document.getElementById('paginationInfo');
    const buttons = document.getElementById('paginationButtons');
    if (total <= PER_PAGE) { bar.classList.add('hidden'); return; }

    bar.classList.remove('hidden');
    const from = start + 1, to = Math.min(start + PER_PAGE, total);
    info.textContent = 'Showing ' + from + '–' + to + ' of ' + total + ' batches';

    // Build page buttons
    buttons.innerHTML = '';

    // Prev
    const prev = document.createElement('button');
    prev.innerHTML = '<i class="fas fa-chevron-left text-xs"></i>';
    prev.className = 'w-8 h-8 rounded-lg flex items-center justify-center border text-sm transition-colors ' +
        (currentPage === 1 ? 'border-gray-200 text-gray-300 cursor-not-allowed' : 'border-gray-300 text-gray-600 hover:bg-gray-100');
    prev.disabled = currentPage === 1;
    prev.onclick  = () => { currentPage--; renderPage(); };
    buttons.appendChild(prev);

    // Page numbers (show up to 5 around current)
    let pStart = Math.max(1, currentPage - 2), pEnd = Math.min(totalPages, pStart + 4);
    if (pEnd - pStart < 4) pStart = Math.max(1, pEnd - 4);
    for (let p = pStart; p <= pEnd; p++) {
        const btn = document.createElement('button');
        btn.textContent = p;
        const isActive = p === currentPage;
        btn.className = 'w-8 h-8 rounded-lg flex items-center justify-center border text-sm font-medium transition-colors ' +
            (isActive ? 'bg-green-600 border-green-600 text-white' : 'border-gray-300 text-gray-600 hover:bg-gray-100');
        btn.onclick = (function(pg){ return function(){ currentPage = pg; renderPage(); }; })(p);
        buttons.appendChild(btn);
    }

    // Next
    const next = document.createElement('button');
    next.innerHTML = '<i class="fas fa-chevron-right text-xs"></i>';
    next.className = 'w-8 h-8 rounded-lg flex items-center justify-center border text-sm transition-colors ' +
        (currentPage === totalPages ? 'border-gray-200 text-gray-300 cursor-not-allowed' : 'border-gray-300 text-gray-600 hover:bg-gray-100');
    next.disabled = currentPage === totalPages;
    next.onclick  = () => { currentPage++; renderPage(); };
    buttons.appendChild(next);
}

function applyFilter(status) {
    const kpis     = document.querySelectorAll('.stat-card');
    const label    = document.getElementById('filterLabel');
    const clearBtn = document.getElementById('clearFilter');

    if (activeFilter === status || status === null) {
        activeFilter = null;
        kpis.forEach(k => k.classList.remove('active-filter'));
        label.textContent = '';
        label.classList.add('hidden');
        clearBtn.classList.add('hidden');
    } else {
        activeFilter = status;
        kpis.forEach(k => k.classList.toggle('active-filter', k.dataset.filter === status));
        label.textContent = '— ' + filterLabels[status];
        label.classList.remove('hidden');
        clearBtn.classList.remove('hidden');
    }
    currentPage = 1;
    renderPage();
}

document.querySelectorAll('.stat-card[data-filter]').forEach(card => {
    card.addEventListener('click', function() { applyFilter(this.dataset.filter); });
});

// Initial render
renderPage();</script>

<!-- Stock Out Modal (Bootstrap modal) -->
<div class="modal fade" id="stockoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="stockout_batch.php">
                <div class="modal-header border-0 bg-light">
                    <h5 class="modal-title">Stock Out Batch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="inventory_id" id="so_inventory_id">
                    <input type="hidden" name="quantity" id="so_quantity">
                    <div class="mb-3">
                        <label class="form-label">Batch</label>
                        <input type="text" id="so_batch_label" class="form-control" readonly>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="destroy_record" value="1" id="so_destroy">
                        <label class="form-check-label" for="so_destroy">Remove batch record if quantity becomes 0</label>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm Stock Out</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php // layout_end removed: page is no longer using the global layout to avoid include warnings ?>


<script>

// Modal for stock out errors
function showStockoutError(msg) {
    let modal = document.getElementById('stockoutErrorModal');
    let modalMsg = document.getElementById('stockoutErrorMsg');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'stockoutErrorModal';
        modal.className = 'modal fade';
        modal.tabIndex = -1;
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-light border-0">
                        <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Stock Out Not Allowed</h5>
                        <button type="button" class="btn-close" onclick="closeStockoutErrorModal()"></button>
                    </div>
                    <div class="modal-body">
                        <div id="stockoutErrorMsg" class="text-danger text-base mb-2"></div>
                    </div>
                    <div class="modal-footer bg-gray-50 border-0">
                        <button type="button" class="btn btn-danger" onclick="closeStockoutErrorModal()">Close</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        modalMsg = document.getElementById('stockoutErrorMsg');
    }
        modalMsg.textContent = msg;
        // Use Bootstrap's modal API to show the modal with proper backdrop and keyboard handling
        try {
            const bsModal = bootstrap.Modal.getOrCreateInstance(modal);
            bsModal.show();
        } catch (e) {
            // fallback to manual show
            modal.classList.add('show');
            modal.style.display = 'block';
        }
}
function closeStockoutErrorModal() {
    let modal = document.getElementById('stockoutErrorModal');
    if (modal) {
                const inst = bootstrap.Modal.getInstance(modal);
                if (inst) inst.hide();
                else {
                    modal.classList.remove('show');
                    setTimeout(() => { modal.style.display = 'none'; }, 300);
                }
    }
}

document.querySelectorAll('.stockout-btn').forEach(btn => {
    btn.addEventListener('click', function(){
        const inv = this.dataset.inventoryId;
        const name = this.dataset.inputName;
        const qty = this.dataset.quantity;
        const daysUntil = parseInt(this.dataset.daysUntil, 10);
        if (daysUntil > 30) {
            showStockoutError('Cannot stock out: Batch is not within 1 month of expiration. (' + daysUntil + ' days left)');
            return;
        }
        document.getElementById('so_inventory_id').value = inv;
        document.getElementById('so_batch_label').value = name + ' (Batch ' + inv + ')';
        document.getElementById('so_quantity').value = qty;
        // Show Bootstrap modal
        const modalEl = document.getElementById('stockoutModal');
        const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        bsModal.show();
    });
});
// end script
</script>

<?php include 'includes/notification_complete.php'; ?>
