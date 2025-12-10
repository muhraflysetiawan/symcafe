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
$material = null;
$is_edit = false;

// Handle edit - get material data
if (isset($_GET['id'])) {
    $material_id = (int)$_GET['id'];
    try {
        $stmt = $conn->prepare("SELECT * FROM raw_materials WHERE material_id = ? AND cafe_id = ?");
        $stmt->execute([$material_id, $cafe_id]);
        $material = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($material) {
            $is_edit = true;
        } else {
            header('Location: ../raw_materials.php');
            exit();
        }
    } catch (Exception $e) {
        header('Location: ../raw_materials.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $material_name = trim(sanitizeInput($_POST['material_name'] ?? ''));
    $material_category = trim(sanitizeInput($_POST['material_category'] ?? ''));
    $unit_type = sanitizeInput($_POST['unit_type'] ?? 'piece');
    $current_cost = (float)($_POST['current_cost'] ?? 0);
    $min_stock_level = (float)($_POST['min_stock_level'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($material_name) || $current_cost < 0) {
        $error = 'Material name and cost are required';
    } else {
        try {
            if ($is_edit && isset($_GET['id'])) {
                $material_id = (int)$_GET['id'];
                
                // Check if cost changed
                $old_cost = $material['current_cost'];
                $cost_changed = abs($old_cost - $current_cost) > 0.01;
                
                $stmt = $conn->prepare("UPDATE raw_materials SET material_name = ?, material_category = ?, unit_type = ?, current_cost = ?, min_stock_level = ?, is_active = ? WHERE material_id = ? AND cafe_id = ?");
                if ($stmt->execute([$material_name, $material_category, $unit_type, $current_cost, $min_stock_level, $is_active, $material_id, $cafe_id])) {
                    // Log cost change
                    if ($cost_changed) {
                        $stmt = $conn->prepare("INSERT INTO material_cost_history (material_id, old_cost, new_cost, changed_by) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$material_id, $old_cost, $current_cost, $_SESSION['user_id']]);
                        
                        // Trigger price recalculation for affected products
                        require_once '../config/functions_inventory.php';
                        if (function_exists('recalculateProductPricing')) {
                            recalculateProductPricing($conn, $cafe_id, $material_id);
                        }
                    }
                    
                    $success = 'Material updated successfully';
                    header('Location: ../raw_materials.php');
                    exit();
                }
            } else {
                $stmt = $conn->prepare("INSERT INTO raw_materials (cafe_id, material_name, material_category, unit_type, current_cost, min_stock_level, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$cafe_id, $material_name, $material_category, $unit_type, $current_cost, $min_stock_level, $is_active])) {
                    $success = 'Material added successfully';
                    header('Location: ../raw_materials.php');
                    exit();
                }
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

$page_title = $is_edit ? 'Edit Raw Material' : 'Add Raw Material';
include '../includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;"><?php echo $is_edit ? 'Edit Raw Material' : 'Add New Raw Material'; ?></h2>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<div class="form-container">
    <form method="POST">
        <div class="form-row">
            <div class="form-group">
                <label for="material_name">Material Name *</label>
                <input type="text" id="material_name" name="material_name" required 
                       value="<?php echo htmlspecialchars($material['material_name'] ?? ''); ?>" 
                       placeholder="e.g., Coffee Beans, Sugar, Milk">
            </div>
            
            <div class="form-group">
                <label for="material_category">Category (Optional)</label>
                <input type="text" id="material_category" name="material_category" 
                       value="<?php echo htmlspecialchars($material['material_category'] ?? ''); ?>" 
                       placeholder="e.g., Beverages, Ingredients, Packaging">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="unit_type">Unit Type *</label>
                <select id="unit_type" name="unit_type" required>
                    <option value="gram" <?php echo (isset($material['unit_type']) && $material['unit_type'] == 'gram') ? 'selected' : ''; ?>>Gram (g)</option>
                    <option value="kg" <?php echo (isset($material['unit_type']) && $material['unit_type'] == 'kg') ? 'selected' : ''; ?>>Kilogram (kg)</option>
                    <option value="ml" <?php echo (isset($material['unit_type']) && $material['unit_type'] == 'ml') ? 'selected' : ''; ?>>Milliliter (ml)</option>
                    <option value="liter" <?php echo (isset($material['unit_type']) && $material['unit_type'] == 'liter') ? 'selected' : ''; ?>>Liter (L)</option>
                    <option value="piece" <?php echo (!isset($material['unit_type']) || $material['unit_type'] == 'piece') ? 'selected' : ''; ?>>Piece</option>
                    <option value="pack" <?php echo (isset($material['unit_type']) && $material['unit_type'] == 'pack') ? 'selected' : ''; ?>>Pack</option>
                    <option value="other" <?php echo (isset($material['unit_type']) && $material['unit_type'] == 'other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="current_cost">Current Cost per Unit (Rp) *</label>
                <input type="number" id="current_cost" name="current_cost" step="0.01" min="0" required 
                       value="<?php echo $material['current_cost'] ?? ''; ?>">
                <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">Cost per unit (e.g., per gram, per ml, per piece)</p>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="min_stock_level">Minimum Stock Level</label>
                <input type="number" id="min_stock_level" name="min_stock_level" step="0.01" min="0" 
                       value="<?php echo $material['min_stock_level'] ?? 0; ?>">
                <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">Alert when stock falls below this level</p>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin-top: 25px;">
                    <input type="checkbox" id="is_active" name="is_active" <?php echo (!isset($material) || $material['is_active']) ? 'checked' : ''; ?>>
                    <span>Active (available for use in recipes)</span>
                </label>
            </div>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button type="submit" class="btn btn-primary"><?php echo $is_edit ? 'Update Material' : 'Add Material'; ?></button>
            <a href="../raw_materials.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>

