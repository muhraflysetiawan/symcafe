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
    try {
        $stmt = $conn->prepare("SELECT * FROM payment_categories WHERE payment_category_id = ? AND cafe_id = ?");
        $stmt->execute([$category_id, $cafe_id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($category) {
            $is_edit = true;
        } else {
            header('Location: payment_categories.php');
            exit();
        }
    } catch (Exception $e) {
        header('Location: payment_categories.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $category_name = trim(sanitizeInput($_POST['category_name'] ?? ''));
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($category_name)) {
        $error = 'Category name is required';
    } else {
        try {
            if ($is_edit && isset($_GET['id'])) {
                $category_id = (int)$_GET['id'];
                // Check if name already exists (excluding current)
                $stmt = $conn->prepare("SELECT payment_category_id FROM payment_categories WHERE category_name = ? AND cafe_id = ? AND payment_category_id != ?");
                $stmt->execute([$category_name, $cafe_id, $category_id]);
                if ($stmt->fetch()) {
                    $error = 'Payment category name already exists';
                } else {
                    $stmt = $conn->prepare("UPDATE payment_categories SET category_name = ?, is_active = ? WHERE payment_category_id = ? AND cafe_id = ?");
                    if ($stmt->execute([$category_name, $is_active, $category_id, $cafe_id])) {
                        $success = 'Payment category updated successfully';
                        header('Location: ../payment_categories.php');
                        exit();
                    } else {
                        $error = 'Failed to update payment category';
                    }
                }
            } else {
                // Check if name already exists
                $stmt = $conn->prepare("SELECT payment_category_id FROM payment_categories WHERE category_name = ? AND cafe_id = ?");
                $stmt->execute([$category_name, $cafe_id]);
                if ($stmt->fetch()) {
                    $error = 'Payment category name already exists';
                } else {
                    $stmt = $conn->prepare("INSERT INTO payment_categories (cafe_id, category_name, is_active) VALUES (?, ?, ?)");
                    if ($stmt->execute([$cafe_id, $category_name, $is_active])) {
                        $success = 'Payment category added successfully';
                        header('Location: ../payment_categories.php');
                        exit();
                    } else {
                        $error = 'Failed to add payment category';
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

$page_title = $is_edit ? 'Edit Payment Category' : 'Add Payment Category';
include '../includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;"><?php echo $is_edit ? 'Edit Payment Category' : 'Add New Payment Category'; ?></h2>

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
            <input type="text" id="category_name" name="category_name" required value="<?php echo htmlspecialchars($category['category_name'] ?? ''); ?>" autofocus>
            <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">e.g., Cash, QRIS, Debit Card, Credit Card, E-Wallet, etc.</p>
        </div>
        
        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" id="is_active" name="is_active" <?php echo (!isset($category) || $category['is_active']) ? 'checked' : ''; ?>>
                <span>Active (available for selection in POS)</span>
            </label>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button type="submit" class="btn btn-primary"><?php echo $is_edit ? 'Update Category' : 'Add Category'; ?></button>
            <a href="../payment_categories.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>

