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
$addon = null;
$is_edit = false;

// Handle edit - get addon data
if (isset($_GET['id'])) {
    $addon_id = (int)$_GET['id'];
    try {
        $stmt = $conn->prepare("SELECT * FROM product_addons WHERE addon_id = ? AND cafe_id = ?");
        $stmt->execute([$addon_id, $cafe_id]);
        $addon = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($addon) {
            $is_edit = true;
        } else {
            header('Location: ../addons.php');
            exit();
        }
    } catch (Exception $e) {
        header('Location: ../addons.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $addon_name = trim(sanitizeInput($_POST['addon_name'] ?? ''));
    $addon_category = trim(sanitizeInput($_POST['addon_category'] ?? ''));
    $price = (float)($_POST['price'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $display_order = (int)($_POST['display_order'] ?? 0);
    
    if (empty($addon_name) || $price < 0) {
        $error = 'Add-on name and price are required';
    } else {
        try {
            if ($is_edit && isset($_GET['id'])) {
                $addon_id = (int)$_GET['id'];
                $stmt = $conn->prepare("UPDATE product_addons SET addon_name = ?, addon_category = ?, price = ?, is_active = ?, display_order = ? WHERE addon_id = ? AND cafe_id = ?");
                if ($stmt->execute([$addon_name, $addon_category, $price, $is_active, $display_order, $addon_id, $cafe_id])) {
                    $success = 'Add-on updated successfully';
                    header('Location: ../addons.php');
                    exit();
                }
            } else {
                $stmt = $conn->prepare("INSERT INTO product_addons (cafe_id, addon_name, addon_category, price, is_active, display_order) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$cafe_id, $addon_name, $addon_category, $price, $is_active, $display_order])) {
                    $success = 'Add-on added successfully';
                    header('Location: ../addons.php');
                    exit();
                }
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

$page_title = $is_edit ? 'Edit Add-on' : 'Add Add-on';
include '../includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;"><?php echo $is_edit ? 'Edit Add-on' : 'Add New Add-on'; ?></h2>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<div class="form-container">
    <form method="POST">
        <div class="form-group">
            <label for="addon_name">Add-on Name *</label>
            <input type="text" id="addon_name" name="addon_name" required 
                   value="<?php echo htmlspecialchars($addon['addon_name'] ?? ''); ?>" 
                   placeholder="e.g., Extra Cheese, Boba, Additional Toppings">
        </div>
        
        <div class="form-group">
            <label for="addon_category">Category (Optional)</label>
            <input type="text" id="addon_category" name="addon_category" 
                   value="<?php echo htmlspecialchars($addon['addon_category'] ?? ''); ?>" 
                   placeholder="e.g., Toppings, Extras, Beverages">
        </div>
        
        <div class="form-group">
            <label for="price">Price (Rp) *</label>
            <input type="number" id="price" name="price" step="0.01" min="0" required 
                   value="<?php echo $addon['price'] ?? ''; ?>">
        </div>
        
        <div class="form-group">
            <label for="display_order">Display Order</label>
            <input type="number" id="display_order" name="display_order" min="0" 
                   value="<?php echo $addon['display_order'] ?? 0; ?>">
        </div>
        
        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" id="is_active" name="is_active" <?php echo (!isset($addon) || $addon['is_active']) ? 'checked' : ''; ?>>
                <span>Active (available for selection in POS)</span>
            </label>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button type="submit" class="btn btn-primary"><?php echo $is_edit ? 'Update Add-on' : 'Add Add-on'; ?></button>
            <a href="../addons.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>

