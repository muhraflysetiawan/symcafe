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

