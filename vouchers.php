<?php
require_once 'config/config.php';
requireLogin();
requireCafeSetup();
requireRole(['owner']);

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

$message = '';
$message_type = '';

// Handle delete
if (isset($_GET['delete'])) {
    $voucher_id = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM vouchers WHERE voucher_id = ? AND cafe_id = ?");
        if ($stmt->execute([$voucher_id, $cafe_id])) {
            $message = 'Voucher deleted successfully';
            $message_type = 'success';
        } else {
            $message = 'Failed to delete voucher';
            $message_type = 'error';
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle toggle active status
if (isset($_GET['toggle'])) {
    $voucher_id = (int)$_GET['toggle'];
    try {
        $stmt = $conn->prepare("UPDATE vouchers SET is_active = NOT is_active WHERE voucher_id = ? AND cafe_id = ?");
        if ($stmt->execute([$voucher_id, $cafe_id])) {
            $message = 'Voucher status updated';
            $message_type = 'success';
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get all vouchers
try {
    $stmt = $conn->prepare("SELECT * FROM vouchers WHERE cafe_id = ? ORDER BY created_at DESC");
    $stmt->execute([$cafe_id]);
    $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get QR codes for each voucher
    foreach ($vouchers as &$voucher) {
        try {
            $stmt = $conn->prepare("SELECT code_id, unique_code, qr_code_data FROM voucher_codes WHERE voucher_id = ? LIMIT 1");
            $stmt->execute([$voucher['voucher_id']]);
            $qr_code = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If QR code entry exists but unique_code is empty, use voucher_code
            if ($qr_code && empty($qr_code['unique_code'])) {
                $qr_code['unique_code'] = $voucher['voucher_code'];
            }
            
            $voucher['qr_code'] = $qr_code ?: null;
        } catch (Exception $e) {
            // If table doesn't exist or error, set to null
            $voucher['qr_code'] = null;
        }
    }
    unset($voucher);
} catch (Exception $e) {
    $vouchers = [];
    if (empty($message)) {
        $message = 'Vouchers table not found. Please run the migration script first.';
        $message_type = 'error';
    }
}

$page_title = 'Vouchers';
include 'includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">Voucher Management</h2>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type == 'error' ? 'error' : 'success'; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="table-container">
    <div class="table-header">
        <div class="table-title">Discount Vouchers</div>
        <a href="forms/voucher_form.php" class="btn btn-primary btn-sm">Add New Voucher</a>
    </div>
    
    <?php if (empty($vouchers)): ?>
        <div style="padding: 20px; text-align: center; color: var(--text-gray);">
            No vouchers found. <a href="forms/voucher_form.php" style="color: var(--primary-white);">Create your first voucher</a>
        </div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Voucher Code</th>
                    <th>QR Code</th>
                    <th>Discount</th>
                    <th>Order Conditions</th>
                    <th>Product Conditions</th>
                    <th>Usage</th>
                    <th>Valid Period</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vouchers as $voucher): ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--primary-white);">
                            <code style="background: var(--primary-black); padding: 4px 8px; border-radius: 4px; color: var(--primary-white);"><?php echo htmlspecialchars($voucher['voucher_code']); ?></code>
                        </td>
                        <td>
                            <?php 
                            // Determine QR code data - use unique_code if available, otherwise use voucher_code
                            $qr_data = null;
                            if (!empty($voucher['qr_code'])) {
                                // Check unique_code first
                                if (!empty($voucher['qr_code']['unique_code'])) {
                                    $qr_data = $voucher['qr_code']['unique_code'];
                                } 
                                // Then check qr_code_data
                                elseif (!empty($voucher['qr_code']['qr_code_data'])) {
                                    $qr_data = $voucher['qr_code']['qr_code_data'];
                                }
                            }
                            
                            // Final fallback: use voucher code directly
                            if (empty($qr_data) && !empty($voucher['voucher_code'])) {
                                $qr_data = $voucher['voucher_code'];
                            }
                            ?>
                            
                            <?php if ($qr_data): ?>
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 5px;">
                                    <img src="api/generate_qr_code.php?data=<?php echo urlencode($qr_data); ?>&size=80&t=<?php echo time(); ?>" 
                                         alt="QR Code" 
                                         style="width: 80px; height: 80px; border: 1px solid var(--border-gray); border-radius: 4px; background: #fff; padding: 5px;"
                                         onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'80\' height=\'80\'%3E%3Crect width=\'80\' height=\'80\' fill=\'%23fff\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' fill=\'%23999\' font-size=\'10\'%3ELoading...%3C/text%3E%3C/svg%3E';">
                                    <a href="api/generate_qr_code.php?data=<?php echo urlencode($qr_data); ?>&size=400" 
                                       download="<?php echo htmlspecialchars($voucher['voucher_code']); ?>_QR.png"
                                       class="btn btn-primary btn-sm"
                                       style="font-size: 11px; padding: 4px 8px;">
                                        Download
                                    </a>
                                </div>
                            <?php else: ?>
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 5px;">
                                    <span style="color: var(--text-gray); font-size: 11px;">No QR Code</span>
                                    <a href="forms/voucher_form.php?id=<?php echo $voucher['voucher_id']; ?>" 
                                       class="btn btn-secondary btn-sm"
                                       style="font-size: 10px; padding: 3px 6px;"
                                       title="Edit voucher to enable QR code">
                                        Enable QR
                                    </a>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo formatCurrency($voucher['discount_amount']); ?></td>
                        <td style="font-size: 12px; color: var(--text-gray);">
                            <?php if ($voucher['min_order_amount'] > 0): ?>
                                Min: <?php echo formatCurrency($voucher['min_order_amount']); ?><br>
                            <?php endif; ?>
                            <?php if ($voucher['max_order_amount']): ?>
                                Max: <?php echo formatCurrency($voucher['max_order_amount']); ?><br>
                            <?php endif; ?>
                            <?php if ($voucher['min_order_amount'] == 0 && !$voucher['max_order_amount']): ?>
                                No restrictions
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 12px; color: var(--text-gray);">
                            <?php if (!empty($voucher['applicable_products'])): ?>
                                <?php 
                                $product_ids = json_decode($voucher['applicable_products'], true);
                                if (is_array($product_ids) && count($product_ids) > 0) {
                                    $stmt = $conn->prepare("SELECT item_name FROM menu_items WHERE item_id IN (" . implode(',', array_fill(0, count($product_ids), '?')) . ")");
                                    $stmt->execute($product_ids);
                                    $product_names = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                    echo count($product_names) . ' product(s)';
                                } else {
                                    echo 'All products';
                                }
                                ?>
                            <?php else: ?>
                                All products
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo $voucher['used_count']; ?>
                            <?php if ($voucher['usage_limit']): ?>
                                / <?php echo $voucher['usage_limit']; ?>
                            <?php else: ?>
                                / ∞
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 12px; color: var(--text-gray);">
                            <?php if ($voucher['valid_from']): ?>
                                From: <?php echo date('d M Y', strtotime($voucher['valid_from'])); ?><br>
                            <?php endif; ?>
                            <?php if ($voucher['valid_until']): ?>
                                Until: <?php echo date('d M Y', strtotime($voucher['valid_until'])); ?>
                            <?php else: ?>
                                No expiry
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($voucher['is_active']): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="forms/voucher_form.php?id=<?php echo $voucher['voucher_id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                            <a href="?toggle=<?php echo $voucher['voucher_id']; ?>" 
                               class="btn btn-<?php echo $voucher['is_active'] ? 'warning' : 'success'; ?> btn-sm"
                               onclick="return confirm('<?php echo $voucher['is_active'] ? 'Deactivate' : 'Activate'; ?> this voucher?')">
                                <?php echo $voucher['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </a>
                            <a href="?delete=<?php echo $voucher['voucher_id']; ?>" 
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Are you sure you want to delete this voucher?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

