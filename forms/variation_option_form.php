<?php
require_once '../config/config.php';
requireLogin();
requireCafeSetup();
requireRole(['owner']);

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

$variation_id = isset($_GET['variation_id']) ? (int)$_GET['variation_id'] : 0;
$option_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $option_id > 0;

if (!$variation_id && !$is_edit) {
    header('Location: ../variations.php');
    exit();
}

// Get variation info
if ($variation_id) {
    $stmt = $conn->prepare("SELECT * FROM product_variations WHERE variation_id = ? AND cafe_id = ?");
    $stmt->execute([$variation_id, $cafe_id]);
    $variation = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$variation) {
        header('Location: ../variations.php');
        exit();
    }
}

// Get option data if editing
$option = null;
if ($is_edit) {
    $stmt = $conn->prepare("SELECT o.*, v.variation_id, v.cafe_id FROM variation_options o JOIN product_variations v ON o.variation_id = v.variation_id WHERE o.option_id = ? AND v.cafe_id = ?");
    $stmt->execute([$option_id, $cafe_id]);
    $option = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($option) {
        $variation_id = $option['variation_id'];
        $stmt = $conn->prepare("SELECT * FROM product_variations WHERE variation_id = ?");
        $stmt->execute([$variation_id]);
        $variation = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        header('Location: ../variations.php');
        exit();
    }
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $option_name = trim(sanitizeInput($_POST['option_name'] ?? ''));
    $price_adjustment = (float)($_POST['price_adjustment'] ?? 0);
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    $display_order = (int)($_POST['display_order'] ?? 0);
    $variation_id = (int)($_POST['variation_id'] ?? $variation_id);
    
    if (empty($option_name)) {
        $error = 'Option name is required';
    } else {
        try {
            // If setting as default, unset other defaults
            if ($is_default) {
                $stmt = $conn->prepare("UPDATE variation_options SET is_default = 0 WHERE variation_id = ?");
                $stmt->execute([$variation_id]);
            }
            
            if ($is_edit) {
                $stmt = $conn->prepare("UPDATE variation_options SET option_name = ?, price_adjustment = ?, is_default = ?, display_order = ? WHERE option_id = ?");
                if ($stmt->execute([$option_name, $price_adjustment, $is_default, $display_order, $option_id])) {
                    $success = 'Option updated successfully';
                    header('Location: ../variation_options.php?variation_id=' . $variation_id);
                    exit();
                }
            } else {
                $stmt = $conn->prepare("INSERT INTO variation_options (variation_id, option_name, price_adjustment, is_default, display_order) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$variation_id, $option_name, $price_adjustment, $is_default, $display_order])) {
                    $success = 'Option added successfully';
                    header('Location: ../variation_options.php?variation_id=' . $variation_id);
                    exit();
                }
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

$page_title = $is_edit ? 'Edit Option' : 'Add Option';
include '../includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">
    <?php echo $is_edit ? 'Edit Option' : 'Add New Option'; ?>
    <span style="color: var(--text-gray); font-size: 16px; font-weight: normal;">
        for <?php echo htmlspecialchars($variation['variation_name']); ?>
    </span>
</h2>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<div class="form-container">
    <form method="POST">
        <input type="hidden" name="variation_id" value="<?php echo $variation_id; ?>">
        
        <div class="form-group">
            <label for="option_name">Option Name *</label>
            <input type="text" id="option_name" name="option_name" required 
                   value="<?php echo htmlspecialchars($option['option_name'] ?? ''); ?>" 
                   placeholder="e.g., Small, Medium, Large, Hot, Ice, 0%, 50%, 100%">
        </div>
        
        <div class="form-group">
            <label for="price_adjustment">Price Adjustment (Rp)</label>
            <input type="number" id="price_adjustment" name="price_adjustment" step="0.01" 
                   value="<?php echo $option['price_adjustment'] ?? 0; ?>" 
                   placeholder="0 for no change, positive for increase, negative for decrease">
            <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">Enter 0 for no price change, positive number to increase price, negative to decrease</p>
        </div>
        
        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" id="is_default" name="is_default" <?php echo (isset($option) && $option['is_default']) ? 'checked' : ''; ?>>
                <span>Set as default option (will be pre-selected in POS)</span>
            </label>
        </div>
        
        <div class="form-group">
            <label for="display_order">Display Order</label>
            <input type="number" id="display_order" name="display_order" min="0" 
                   value="<?php echo $option['display_order'] ?? 0; ?>">
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button type="submit" class="btn btn-primary"><?php echo $is_edit ? 'Update Option' : 'Add Option'; ?></button>
            <a href="../variation_options.php?variation_id=<?php echo $variation_id; ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>

