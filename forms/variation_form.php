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
$variation = null;
$is_edit = false;

// Handle edit - get variation data
if (isset($_GET['id'])) {
    $variation_id = (int)$_GET['id'];
    try {
        $stmt = $conn->prepare("SELECT * FROM product_variations WHERE variation_id = ? AND cafe_id = ?");
        $stmt->execute([$variation_id, $cafe_id]);
        $variation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($variation) {
            $is_edit = true;
        } else {
            header('Location: ../variations.php');
            exit();
        }
    } catch (Exception $e) {
        header('Location: ../variations.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $variation_name = trim(sanitizeInput($_POST['variation_name'] ?? ''));
    $variation_type = sanitizeInput($_POST['variation_type'] ?? 'custom');
    $is_required = isset($_POST['is_required']) ? 1 : 0;
    $display_order = (int)($_POST['display_order'] ?? 0);
    
    if (empty($variation_name)) {
        $error = 'Variation name is required';
    } else {
        try {
            if ($is_edit && isset($_GET['id'])) {
                $variation_id = (int)$_GET['id'];
                // Check if name already exists (excluding current)
                $stmt = $conn->prepare("SELECT variation_id FROM product_variations WHERE variation_name = ? AND cafe_id = ? AND variation_id != ?");
                $stmt->execute([$variation_name, $cafe_id, $variation_id]);
                if ($stmt->fetch()) {
                    $error = 'Variation name already exists';
                } else {
                    $stmt = $conn->prepare("UPDATE product_variations SET variation_name = ?, variation_type = ?, is_required = ?, display_order = ? WHERE variation_id = ? AND cafe_id = ?");
                    if ($stmt->execute([$variation_name, $variation_type, $is_required, $display_order, $variation_id, $cafe_id])) {
                        $success = 'Variation updated successfully';
                        header('Location: ../variations.php');
                        exit();
                    } else {
                        $error = 'Failed to update variation';
                    }
                }
            } else {
                // Check if name already exists
                $stmt = $conn->prepare("SELECT variation_id FROM product_variations WHERE variation_name = ? AND cafe_id = ?");
                $stmt->execute([$variation_name, $cafe_id]);
                if ($stmt->fetch()) {
                    $error = 'Variation name already exists';
                } else {
                    $stmt = $conn->prepare("INSERT INTO product_variations (cafe_id, variation_name, variation_type, is_required, display_order) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt->execute([$cafe_id, $variation_name, $variation_type, $is_required, $display_order])) {
                        $variation_id = $conn->lastInsertId();
                        $success = 'Variation added successfully';
                        header('Location: ../variation_options.php?variation_id=' . $variation_id);
                        exit();
                    } else {
                        $error = 'Failed to add variation';
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

$page_title = $is_edit ? 'Edit Variation' : 'Add Variation';
include '../includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;"><?php echo $is_edit ? 'Edit Variation' : 'Add New Variation'; ?></h2>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="form-container">
    <form method="POST">
        <div class="form-group">
            <label for="variation_name">Variation Name *</label>
            <input type="text" id="variation_name" name="variation_name" required 
                   value="<?php echo htmlspecialchars($variation['variation_name'] ?? ''); ?>" 
                   placeholder="e.g., Size, Temperature, Sweetness">
        </div>
        
        <div class="form-group">
            <label for="variation_type">Variation Type</label>
            <select id="variation_type" name="variation_type">
                <option value="size" <?php echo (isset($variation['variation_type']) && $variation['variation_type'] == 'size') ? 'selected' : ''; ?>>Size</option>
                <option value="temperature" <?php echo (isset($variation['variation_type']) && $variation['variation_type'] == 'temperature') ? 'selected' : ''; ?>>Temperature</option>
                <option value="sweetness" <?php echo (isset($variation['variation_type']) && $variation['variation_type'] == 'sweetness') ? 'selected' : ''; ?>>Sweetness</option>
                <option value="custom" <?php echo (!isset($variation['variation_type']) || $variation['variation_type'] == 'custom') ? 'selected' : ''; ?>>Custom</option>
            </select>
        </div>
        
        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" id="is_required" name="is_required" <?php echo (!isset($variation) || $variation['is_required']) ? 'checked' : ''; ?>>
                <span>Required (cashier must select this variation)</span>
            </label>
        </div>
        
        <div class="form-group">
            <label for="display_order">Display Order</label>
            <input type="number" id="display_order" name="display_order" min="0" 
                   value="<?php echo $variation['display_order'] ?? 0; ?>">
            <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">Lower numbers appear first</p>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button type="submit" class="btn btn-primary"><?php echo $is_edit ? 'Update Variation' : 'Add Variation'; ?></button>
            <a href="../variations.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>

