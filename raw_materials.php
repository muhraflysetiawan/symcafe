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
    $material_id = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM raw_materials WHERE material_id = ? AND cafe_id = ?");
        if ($stmt->execute([$material_id, $cafe_id])) {
            $message = 'Raw material deleted successfully';
            $message_type = 'success';
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle toggle active
if (isset($_GET['toggle'])) {
    $material_id = (int)$_GET['toggle'];
    try {
        $stmt = $conn->prepare("UPDATE raw_materials SET is_active = NOT is_active WHERE material_id = ? AND cafe_id = ?");
        if ($stmt->execute([$material_id, $cafe_id])) {
            $message = 'Material status updated';
            $message_type = 'success';
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get all raw materials with current stock
try {
    $stmt = $conn->prepare("
        SELECT m.*, 
               COALESCE(SUM(CASE WHEN b.is_used = 0 THEN b.quantity ELSE 0 END), 0) as current_stock,
               MIN(CASE WHEN b.is_used = 0 AND b.expiration_date IS NOT NULL THEN b.expiration_date ELSE NULL END) as nearest_expiration
        FROM raw_materials m
        LEFT JOIN material_batches b ON m.material_id = b.material_id
        WHERE m.cafe_id = ?
        GROUP BY m.material_id
        ORDER BY m.material_name
    ");
    $stmt->execute([$cafe_id]);
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $materials = [];
    if (empty($message)) {
        $message = 'Raw materials table not found. Please run the migration script first.';
        $message_type = 'error';
    }
}

$page_title = 'Raw Materials';
include 'includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">Raw Materials Management</h2>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type == 'error' ? 'error' : 'success'; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="table-container">
    <div class="table-header">
        <div class="table-title">Raw Materials Inventory</div>
        <a href="forms/raw_material_form.php" class="btn btn-primary btn-sm">Add New Material</a>
    </div>
    
    <?php if (empty($materials)): ?>
        <div style="padding: 20px; text-align: center; color: var(--text-gray);">
            No raw materials found. <a href="forms/raw_material_form.php" style="color: var(--primary-white);">Add your first raw material</a>
        </div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Material Name</th>
                    <th>Category</th>
                    <th>Unit</th>
                    <th>Current Cost</th>
                    <th>Current Stock</th>
                    <th>Min Level</th>
                    <th>Expiration Alert</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($materials as $material): ?>
                    <?php
                    $stock_status = 'ok';
                    $expiration_alert = '';
                    if ($material['current_stock'] <= $material['min_stock_level']) {
                        $stock_status = 'low';
                    }
                    if ($material['nearest_expiration']) {
                        $days_until_expiry = (strtotime($material['nearest_expiration']) - time()) / (60 * 60 * 24);
                        if ($days_until_expiry < 0) {
                            $expiration_alert = '<span class="badge badge-danger">Expired</span>';
                        } elseif ($days_until_expiry <= 7) {
                            $expiration_alert = '<span class="badge badge-warning">Expires in ' . ceil($days_until_expiry) . ' days</span>';
                        }
                    }
                    ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--primary-white);"><?php echo htmlspecialchars($material['material_name']); ?></td>
                        <td><?php echo htmlspecialchars($material['material_category'] ?? 'General'); ?></td>
                        <td><?php echo ucfirst($material['unit_type']); ?></td>
                        <td><?php echo formatCurrency($material['current_cost']); ?></td>
                        <td>
                            <span style="color: <?php echo $stock_status == 'low' ? '#dc3545' : 'var(--primary-white)'; ?>; font-weight: 600;">
                                <?php echo number_format($material['current_stock'], 2); ?>
                            </span>
                        </td>
                        <td><?php echo number_format($material['min_stock_level'], 2); ?></td>
                        <td><?php echo $expiration_alert; ?></td>
                        <td>
                            <?php if ($material['is_active']): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="forms/raw_material_form.php?id=<?php echo $material['material_id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                            <a href="material_batches.php?material_id=<?php echo $material['material_id']; ?>" class="btn btn-secondary btn-sm">Batches</a>
                            <a href="?toggle=<?php echo $material['material_id']; ?>" 
                               class="btn btn-<?php echo $material['is_active'] ? 'warning' : 'success'; ?> btn-sm"
                               onclick="return confirm('<?php echo $material['is_active'] ? 'Deactivate' : 'Activate'; ?> this material?')">
                                <?php echo $material['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </a>
                            <a href="?delete=<?php echo $material['material_id']; ?>" 
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Are you sure you want to delete this material? This will also delete all batches and recipe associations.')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

