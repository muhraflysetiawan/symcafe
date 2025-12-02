<?php
require_once '../config/config.php';
requireLogin();
requireCafeSetup();
requireRole(['owner']);

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

$error = '';
$success = '';
$category = null;
$is_edit = false;

// Handle edit - get category data
if (isset($_GET['id'])) {
    $category_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM menu_categories WHERE category_id = ? AND cafe_id = ?");
    $stmt->execute([$category_id, $cafe_id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($category) {
        $is_edit = true;
    } else {
        header('Location: categories.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $category_name = sanitizeInput($_POST['category_name'] ?? '');
    
    if (empty($category_name)) {
        $error = 'Category name is required';
    } else {
        // Check if category name already exists (excluding current category if editing)
        if ($is_edit && isset($_GET['id'])) {
            $category_id = (int)$_GET['id'];
            $stmt = $conn->prepare("SELECT category_id FROM menu_categories WHERE category_name = ? AND cafe_id = ? AND category_id != ?");
            $stmt->execute([$category_name, $cafe_id, $category_id]);
        } else {
            $stmt = $conn->prepare("SELECT category_id FROM menu_categories WHERE category_name = ? AND cafe_id = ?");
            $stmt->execute([$category_name, $cafe_id]);
        }
        
        if ($stmt->fetch()) {
            $error = 'Category name already exists';
        } else {
            if ($is_edit && isset($_GET['id'])) {
                $category_id = (int)$_GET['id'];
                $stmt = $conn->prepare("UPDATE menu_categories SET category_name = ? WHERE category_id = ? AND cafe_id = ?");
                if ($stmt->execute([$category_name, $category_id, $cafe_id])) {
                    $success = 'Category updated successfully';
                    header('Location: ../categories.php');
                    exit();
                } else {
                    $error = 'Failed to update category';
                }
            } else {
                $stmt = $conn->prepare("INSERT INTO menu_categories (cafe_id, category_name) VALUES (?, ?)");
                if ($stmt->execute([$cafe_id, $category_name])) {
                    $success = 'Category added successfully';
                    header('Location: ../categories.php');
                    exit();
                } else {
                    $error = 'Failed to add category';
                }
            }
        }
    }
}

$page_title = $is_edit ? 'Edit Category' : 'Add Category';
include '../includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;"><?php echo $is_edit ? 'Edit Category' : 'Add New Category'; ?></h2>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="form-container">
    <form method="POST">
        <div class="form-group">
            <label for="category_name">Category Name *</label>
            <input type="text" id="category_name" name="category_name" required autofocus value="<?php echo htmlspecialchars($category['category_name'] ?? ''); ?>" placeholder="e.g., Beverages, Food, Desserts">
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button type="submit" class="btn btn-primary"><?php echo $is_edit ? 'Update Category' : 'Add Category'; ?></button>
            <a href="../categories.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>

