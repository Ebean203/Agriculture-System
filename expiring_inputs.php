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
          WHERE mi.expiration_date IS NOT NULL";
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

// Prepare notifications for display on this page
$all_notifications = getNotifications($conn);
$critical_count = getCriticalNotificationCount($conn);
$stock_notifications = getStockNotifications($conn);
$stock_map = [];
foreach ($stock_notifications as $sn) {
    $inputId = $sn['data']['input_id'] ?? null;
    if ($inputId !== null) {
        $stock_map[(int)$inputId] = $sn;
    }
}
// Prepare inventory notifications for display on this page
$stock_notifications = getStockNotifications($conn); // inventory-specific alerts
$critical_count = 0;
foreach ($stock_notifications as $sn) {
    if ($sn['type'] === 'urgent' || $sn['priority'] <= 2) $critical_count++;
}
$stock_map = [];

include 'includes/layout_start.php';
// Show success or error messages from session
if (!empty($_SESSION['success'])) {
    echo '<div class="alert alert-success bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded mb-4">' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}
if (!empty($_SESSION['error'])) {
    echo '<div class="alert alert-danger bg-red-100 border border-red-400 text-red-800 px-4 py-3 rounded mb-4">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}
?>
<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-bold">Expiring Batches</h1>
            <p class="text-sm text-gray-600">Batches ordered by earliest expiration. Warning at 10 days before expiration.</p>
        </div>
        <?php if ($filter_inventory_id || $filter_input_id): ?>
        <div class="bg-blue-50 border-l-4 border-blue-500 p-3 mb-4 rounded">
            <div class="flex items-center justify-between">
                <div class="text-sm text-blue-800">
                    Showing results for
                    <?php if ($filter_inventory_id): ?>
                        Inventory Batch ID <strong>#<?php echo $filter_inventory_id; ?></strong>
                    <?php else: ?>
                        Input <strong>#<?php echo $filter_input_id; ?></strong>
                    <?php endif; ?>
                </div>
                <a href="expiring_inputs.php" class="text-blue-700 hover:underline text-sm">View all</a>
            </div>
        </div>
        <?php endif; ?>
        <div class="flex items-center justify-end mb-4">
            <div class="text-sm mr-4">
                <span class="font-medium">Inventory Alerts:</span>
                <span class="ml-2 text-xs bg-red-100 text-red-800 px-2 py-1 rounded">Critical: <?php echo intval($critical_count); ?></span>
                <span class="ml-2 text-xs bg-gray-100 text-gray-800 px-2 py-1 rounded">Total: <?php echo count($stock_notifications); ?></span>
            </div>
            <div class="w-80 bg-gray-50 border rounded p-3">
                <?php if (count($stock_notifications) === 0): ?>
                    <div class="text-xs text-gray-500">No inventory alerts</div>
                <?php else: ?>
                    <ul class="space-y-2 text-sm">
                        <?php $shown = 0; foreach ($stock_notifications as $n): if ($shown++ >= 6) break; ?>
                            <li class="flex items-start gap-2">
                                <i class="<?php echo htmlspecialchars($n['icon']); ?> mt-1 text-xs"></i>
                                <div>
                                    <div class="font-medium <?php echo ($n['type'] === 'urgent' ? 'text-red-700' : 'text-yellow-700'); ?>"><?php echo htmlspecialchars($n['title']); ?></div>
                                    <div class="text-xs text-gray-600"><?php echo htmlspecialchars($n['message']); ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <?php if (mysqli_num_rows($res) === 0): ?>
            <div class="text-gray-600 py-6">No batches with expiration dates found.</div>
        <?php else: ?>
            <div class="grid gap-4">
                <?php while ($row = mysqli_fetch_assoc($res)):
                    $days_until = (int) ( (strtotime($row['expiration_date']) - strtotime(date('Y-m-d'))) / 86400 );
                    $status = 'normal';
                    if ($days_until < 0) $status = 'expired';
                    elseif ($days_until <= 10) $status = 'warning';
                    $badge = ['normal' => 'bg-green-100 text-green-800', 'warning' => 'bg-yellow-100 text-yellow-800', 'expired' => 'bg-red-100 text-red-800'][$status];
                    $inputId = (int)$row['input_id'];
                    $stockNote = $stock_map[$inputId] ?? null;
                ?>
                <div class="p-4 border rounded flex items-center justify-between">
                    <div>
                        <div class="text-lg font-semibold"><?php echo htmlspecialchars($row['input_name']); ?> <span class="text-sm text-gray-500">(<?php echo htmlspecialchars($row['unit']); ?>)</span></div>
                        <div class="text-sm text-gray-600">Batch ID: <?php echo $row['inventory_id']; ?> &middot; Quantity: <?php echo intval($row['quantity_on_hand']); ?> &middot; Master total: <?php echo intval($row['total_stock']); ?></div>
                        <div class="text-sm mt-1"><strong>Expires:</strong> <?php echo date('M d, Y', strtotime($row['expiration_date'])); ?> &mdash; <span class="<?php echo $badge; ?> px-2 py-1 rounded text-xs font-medium"><?php echo ($status === 'expired' ? 'EXPIRED' : ($status === 'warning' ? 'WARNING' : 'OK')); ?></span>
                            <span class="ml-3 text-xs text-gray-500">(in <?php echo $days_until; ?> days)</span>
                        </div>
                        <?php if ($stockNote): ?>
                            <div class="mt-2">
                                <span class="text-xs px-2 py-1 rounded <?php echo ($stockNote['type'] === 'urgent' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'); ?>"><?php echo htmlspecialchars($stockNote['message']); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($row['is_expired'])): ?>
                            <div class="mt-2">
                                <span class="text-xs px-2 py-1 rounded bg-gray-100 text-gray-800">Marked Expired</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php $disabled = !empty($row['is_expired']) ? 'disabled' : ''; ?>
                        <button class="bg-red-600 text-white px-3 py-2 rounded stockout-btn" <?php echo $disabled; ?>
                            data-inventory-id="<?php echo $row['inventory_id']; ?>"
                            data-input-name="<?php echo htmlspecialchars($row['input_name']); ?>"
                            data-quantity="<?php echo intval($row['quantity_on_hand']); ?>"
                            data-days-until="<?php echo $days_until; ?>"
                        >Stock Out Batch</button>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

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
