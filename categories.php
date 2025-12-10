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
if (isset($_GET['delete']) && $_GET['delete']) {
    $category_id = (int)$_GET['delete'];
    
    // Check if category is used by any products
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM menu_items WHERE category_id = ? AND cafe_id = ?");
    $stmt->execute([$category_id, $cafe_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        $message = 'Cannot delete category: it is being used by products';
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("DELETE FROM menu_categories WHERE category_id = ? AND cafe_id = ?");
        if ($stmt->execute([$category_id, $cafe_id])) {
            $message = 'Category deleted successfully';
            $message_type = 'success';
        } else {
            $message = 'Failed to delete category';
            $message_type = 'error';
        }
    }
}

// Get all categories
$stmt = $conn->prepare("SELECT mc.*, COUNT(mi.item_id) as product_count FROM menu_categories mc LEFT JOIN menu_items mi ON mc.category_id = mi.category_id WHERE mc.cafe_id = ? GROUP BY mc.category_id ORDER BY mc.category_name");
$stmt->execute([$cafe_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Categories';
include 'includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">Product Categories</h2>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<div class="table-container">
    <div class="table-header">
        <div class="table-title">Category List</div>
        <a href="forms/category_form.php" class="btn btn-primary btn-sm">+ Add Category</a>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>Category Name</th>
                <th>Products Count</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($categories)): ?>
                <tr>
                    <td colspan="3" style="text-align: center; color: var(--text-gray);">No categories found. <a href="forms/category_form.php" style="color: var(--primary-white);">Add your first category</a></td>
                </tr>
            <?php else: ?>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td style="color: var(--primary-white); font-weight: 500;"><?php echo htmlspecialchars($category['category_name']); ?></td>
                        <td><?php echo $category['product_count']; ?></td>
                        <td>
                            <a href="forms/category_form.php?id=<?php echo $category['category_id']; ?>" class="btn btn-secondary btn-sm">Edit</a>
                            <a href="?delete=<?php echo $category['category_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this category?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>

