<?php
require_once '../config/config.php';
requireLogin();
requireCafeSetup();
requireRole(['owner']);

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

$material_id = isset($_GET['material_id']) ? (int)$_GET['material_id'] : 0;
$batch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $batch_id > 0;

if (!$material_id && !$is_edit) {
    header('Location: ../raw_materials.php');
    exit();
}

// Get material info
if ($material_id) {
    $stmt = $conn->prepare("SELECT * FROM raw_materials WHERE material_id = ? AND cafe_id = ?");
    $stmt->execute([$material_id, $cafe_id]);
    $material = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$material) {
        header('Location: ../raw_materials.php');
        exit();
    }
}

// Get batch data if editing
$batch = null;
if ($is_edit) {
    $stmt = $conn->prepare("SELECT b.*, m.material_id, m.cafe_id FROM material_batches b JOIN raw_materials m ON b.material_id = m.material_id WHERE b.batch_id = ? AND m.cafe_id = ?");
    $stmt->execute([$batch_id, $cafe_id]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($batch) {
        $material_id = $batch['material_id'];
        $stmt = $conn->prepare("SELECT * FROM raw_materials WHERE material_id = ?");
        $stmt->execute([$material_id]);
        $material = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        header('Location: ../raw_materials.php');
        exit();
    }
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $batch_number = trim(sanitizeInput($_POST['batch_number'] ?? ''));
    $quantity = (float)($_POST['quantity'] ?? 0);
    $cost_per_unit = (float)($_POST['cost_per_unit'] ?? 0);
    $received_date = sanitizeInput($_POST['received_date'] ?? date('Y-m-d'));
    $expiration_date = !empty($_POST['expiration_date']) ? sanitizeInput($_POST['expiration_date']) : null;
    $material_id = (int)($_POST['material_id'] ?? $material_id);
    
    if (empty($batch_number) || $quantity <= 0 || $cost_per_unit < 0) {
        $error = 'Batch number, quantity, and cost are required';
    } else {
        try {
            if ($is_edit) {
                $stmt = $conn->prepare("UPDATE material_batches SET batch_number = ?, quantity = ?, cost_per_unit = ?, received_date = ?, expiration_date = ? WHERE batch_id = ?");
                if ($stmt->execute([$batch_number, $quantity, $cost_per_unit, $received_date, $expiration_date, $batch_id])) {
                    $success = 'Batch updated successfully';
                    header('Location: ../material_batches.php?material_id=' . $material_id);
                    exit();
                }
            } else {
                $stmt = $conn->prepare("INSERT INTO material_batches (material_id, batch_number, quantity, cost_per_unit, received_date, expiration_date) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$material_id, $batch_number, $quantity, $cost_per_unit, $received_date, $expiration_date])) {
                    $success = 'Batch added successfully';
                    header('Location: ../material_batches.php?material_id=' . $material_id);
                    exit();
                }
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

$page_title = $is_edit ? 'Edit Batch' : 'Add Batch';
include '../includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">
    <?php echo $is_edit ? 'Edit Batch' : 'Add New Batch'; ?>
    <span style="color: var(--text-gray); font-size: 16px; font-weight: normal;">
        for <?php echo htmlspecialchars($material['material_name']); ?>
    </span>
</h2>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<div class="form-container">
    <form method="POST">
        <input type="hidden" name="material_id" value="<?php echo $material_id; ?>">
        
        <div class="form-row">
            <div class="form-group">
                <label for="batch_number">Batch Number *</label>
                <input type="text" id="batch_number" name="batch_number" required 
                       value="<?php echo htmlspecialchars($batch['batch_number'] ?? ''); ?>" 
                       placeholder="e.g., BATCH-001, SUPPLIER-2024-01">
            </div>
            
            <div class="form-group">
                <label for="quantity">Quantity *</label>
                <input type="number" id="quantity" name="quantity" step="0.01" min="0.01" required 
                       value="<?php echo $batch['quantity'] ?? ''; ?>">
                <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">In <?php echo $material['unit_type']; ?></p>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="cost_per_unit">Cost per Unit (Rp) *</label>
                <input type="number" id="cost_per_unit" name="cost_per_unit" step="0.01" min="0" required 
                       value="<?php echo $batch['cost_per_unit'] ?? $material['current_cost']; ?>">
            </div>
            
            <div class="form-group">
                <label for="received_date">Received Date *</label>
                <input type="date" id="received_date" name="received_date" required 
                       value="<?php echo $batch['received_date'] ?? date('Y-m-d'); ?>">
            </div>
        </div>
        
        <div class="form-group">
            <label for="expiration_date">Expiration Date (Optional)</label>
            <input type="date" id="expiration_date" name="expiration_date" 
                   value="<?php echo $batch['expiration_date'] ?? ''; ?>">
            <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">Leave empty if material doesn't expire</p>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button type="submit" class="btn btn-primary"><?php echo $is_edit ? 'Update Batch' : 'Add Batch'; ?></button>
            <a href="../material_batches.php?material_id=<?php echo $material_id; ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>

