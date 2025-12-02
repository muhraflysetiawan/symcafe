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
    $addon_id = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM product_addons WHERE addon_id = ? AND cafe_id = ?");
        if ($stmt->execute([$addon_id, $cafe_id])) {
            $message = 'Add-on deleted successfully';
            $message_type = 'success';
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle toggle active
if (isset($_GET['toggle'])) {
    $addon_id = (int)$_GET['toggle'];
    try {
        $stmt = $conn->prepare("UPDATE product_addons SET is_active = NOT is_active WHERE addon_id = ? AND cafe_id = ?");
        if ($stmt->execute([$addon_id, $cafe_id])) {
            $message = 'Add-on status updated';
            $message_type = 'success';
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get all add-ons
try {
    $stmt = $conn->prepare("
        SELECT a.*, COUNT(DISTINCT paa.item_id) as product_count
        FROM product_addons a
        LEFT JOIN product_addon_assignments paa ON a.addon_id = paa.addon_id
        WHERE a.cafe_id = ?
        GROUP BY a.addon_id
        ORDER BY a.display_order, a.addon_name
    ");
    $stmt->execute([$cafe_id]);
    $addons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $addons = [];
    if (empty($message)) {
        $message = 'Add-ons table not found. Please run the migration script first.';
        $message_type = 'error';
    }
}

$page_title = 'Product Add-ons';
include 'includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">Product Add-ons Management</h2>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type == 'error' ? 'error' : 'success'; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="table-container">
    <div class="table-header">
        <div class="table-title">Add-ons</div>
        <a href="forms/addon_form.php" class="btn btn-primary btn-sm">Add New Add-on</a>
    </div>
    
    <?php if (empty($addons)): ?>
        <div style="padding: 20px; text-align: center; color: var(--text-gray);">
            No add-ons found. <a href="forms/addon_form.php" style="color: var(--primary-white);">Create your first add-on</a>
        </div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Add-on Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Used in Products</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($addons as $addon): ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--primary-white);"><?php echo htmlspecialchars($addon['addon_name']); ?></td>
                        <td><?php echo htmlspecialchars($addon['addon_category'] ?? 'General'); ?></td>
                        <td><?php echo formatCurrency($addon['price']); ?></td>
                        <td><?php echo $addon['product_count']; ?> products</td>
                        <td>
                            <?php if ($addon['is_active']): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="forms/addon_form.php?id=<?php echo $addon['addon_id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                            <a href="?toggle=<?php echo $addon['addon_id']; ?>" 
                               class="btn btn-<?php echo $addon['is_active'] ? 'warning' : 'success'; ?> btn-sm"
                               onclick="return confirm('<?php echo $addon['is_active'] ? 'Deactivate' : 'Activate'; ?> this add-on?')">
                                <?php echo $addon['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </a>
                            <a href="?delete=<?php echo $addon['addon_id']; ?>" 
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Are you sure you want to delete this add-on?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

