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
    $category_id = (int)$_GET['delete'];
    try {
        // Check if category is used in any payments
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM payments p 
                                JOIN payment_categories pc ON p.payment_method = pc.category_name 
                                WHERE pc.payment_category_id = ? AND pc.cafe_id = ?");
        $stmt->execute([$category_id, $cafe_id]);
        $used = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($used > 0) {
            $message = 'Cannot delete payment category that has been used in transactions. You can deactivate it instead.';
            $message_type = 'error';
        } else {
            $stmt = $conn->prepare("DELETE FROM payment_categories WHERE payment_category_id = ? AND cafe_id = ?");
            if ($stmt->execute([$category_id, $cafe_id])) {
                $message = 'Payment category deleted successfully';
                $message_type = 'success';
            } else {
                $message = 'Failed to delete payment category';
                $message_type = 'error';
            }
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle toggle active status
if (isset($_GET['toggle'])) {
    $category_id = (int)$_GET['toggle'];
    try {
        $stmt = $conn->prepare("UPDATE payment_categories SET is_active = NOT is_active WHERE payment_category_id = ? AND cafe_id = ?");
        if ($stmt->execute([$category_id, $cafe_id])) {
            $message = 'Payment category status updated';
            $message_type = 'success';
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get all payment categories
try {
    $stmt = $conn->prepare("SELECT * FROM payment_categories WHERE cafe_id = ? ORDER BY is_active DESC, category_name ASC");
    $stmt->execute([$cafe_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist yet
    $categories = [];
    if (empty($message)) {
        $message = 'Payment categories table not found. Please run the migration script first.';
        $message_type = 'error';
    }
}

$page_title = 'Payment Categories';
include 'includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">Payment Categories</h2>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type == 'error' ? 'error' : 'success'; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="table-container">
    <div class="table-header">
        <div class="table-title">Payment Methods</div>
        <a href="forms/payment_category_form.php" class="btn btn-primary btn-sm">Add Payment Category</a>
    </div>
    
    <?php if (empty($categories)): ?>
        <div style="padding: 20px; text-align: center; color: var(--text-gray);">
            No payment categories found. <a href="forms/payment_category_form.php" style="color: var(--primary-white);">Add your first payment category</a>
        </div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Category Name</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--primary-white);"><?php echo htmlspecialchars($category['category_name']); ?></td>
                        <td>
                            <?php if ($category['is_active']): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="forms/payment_category_form.php?id=<?php echo $category['payment_category_id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                            <a href="?toggle=<?php echo $category['payment_category_id']; ?>" 
                               class="btn btn-<?php echo $category['is_active'] ? 'warning' : 'success'; ?> btn-sm"
                               onclick="return confirm('<?php echo $category['is_active'] ? 'Deactivate' : 'Activate'; ?> this payment category?')">
                                <?php echo $category['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </a>
                            <a href="?delete=<?php echo $category['payment_category_id']; ?>" 
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Are you sure you want to delete this payment category?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

