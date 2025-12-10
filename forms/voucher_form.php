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
$voucher = null;
$is_edit = false;

// Handle edit - get voucher data
if (isset($_GET['id'])) {
    $voucher_id = (int)$_GET['id'];
    try {
        $stmt = $conn->prepare("SELECT * FROM vouchers WHERE voucher_id = ? AND cafe_id = ?");
        $stmt->execute([$voucher_id, $cafe_id]);
        $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($voucher) {
            $is_edit = true;
        } else {
            header('Location: vouchers.php');
            exit();
        }
    } catch (Exception $e) {
        header('Location: vouchers.php');
        exit();
    }
}

// Get all products for product selection
$stmt = $conn->prepare("SELECT item_id, item_name FROM menu_items WHERE cafe_id = ? ORDER BY item_name");
$stmt->execute([$cafe_id]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to generate single QR code
function generateQRCode($conn, $voucher_id, $voucher_code) {
    try {
        // Check if QR code already exists
        $stmt = $conn->prepare("SELECT code_id FROM voucher_codes WHERE voucher_id = ? LIMIT 1");
        $stmt->execute([$voucher_id]);
        if ($stmt->fetch()) {
            // QR code already exists, update it to ensure it matches current voucher code
            $stmt = $conn->prepare("UPDATE voucher_codes SET unique_code = ?, qr_code_data = ? WHERE voucher_id = ?");
            $stmt->execute([$voucher_code, $voucher_code, $voucher_id]);
            return true;
        }
        
        // Create single QR code
        $stmt = $conn->prepare("INSERT INTO voucher_codes (voucher_id, unique_code, qr_code_data) VALUES (?, ?, ?)");
        $result = $stmt->execute([$voucher_id, $voucher_code, $voucher_code]);
        
        // Verify it was created
        if ($result) {
            $stmt = $conn->prepare("SELECT code_id FROM voucher_codes WHERE voucher_id = ? LIMIT 1");
            $stmt->execute([$voucher_id]);
            return $stmt->fetch() !== false;
        }
        return false;
    } catch (Exception $e) {
        error_log("Error generating QR code: " . $e->getMessage());
        return false;
    }
}

// Check if voucher has QR code
$has_qr_code = false;
if ($is_edit && isset($voucher['voucher_id'])) {
    try {
        $stmt = $conn->prepare("SELECT code_id FROM voucher_codes WHERE voucher_id = ? LIMIT 1");
        $stmt->execute([$voucher['voucher_id']]);
        $has_qr_code = $stmt->fetch() !== false;
    } catch (Exception $e) {
        // Table might not exist
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $voucher_code = strtoupper(trim(sanitizeInput($_POST['voucher_code'] ?? '')));
    $discount_amount = (float)($_POST['discount_amount'] ?? 0);
    $min_order_amount = (float)($_POST['min_order_amount'] ?? 0);
    $max_order_amount = !empty($_POST['max_order_amount']) ? (float)$_POST['max_order_amount'] : null;
    $applicable_products = isset($_POST['applicable_products']) && is_array($_POST['applicable_products']) 
        ? json_encode(array_map('intval', $_POST['applicable_products'])) 
        : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $usage_limit = !empty($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : null;
    $valid_from = !empty($_POST['valid_from']) ? $_POST['valid_from'] : null;
    $valid_until = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
    $enable_qr = isset($_POST['enable_qr']) ? 1 : 0;
    
    if (empty($voucher_code) || $discount_amount <= 0) {
        $error = 'Voucher code and discount amount are required';
    } elseif ($min_order_amount > 0 && $max_order_amount && $min_order_amount > $max_order_amount) {
        $error = 'Minimum order amount cannot be greater than maximum order amount';
    } elseif ($valid_from && $valid_until && strtotime($valid_from) > strtotime($valid_until)) {
        $error = 'Valid from date cannot be after valid until date';
    } else {
        try {
            if ($is_edit && isset($_GET['id'])) {
                $voucher_id = (int)$_GET['id'];
                // Check if code already exists (excluding current)
                $stmt = $conn->prepare("SELECT voucher_id FROM vouchers WHERE voucher_code = ? AND cafe_id = ? AND voucher_id != ?");
                $stmt->execute([$voucher_code, $cafe_id, $voucher_id]);
                if ($stmt->fetch()) {
                    $error = 'Voucher code already exists';
                } else {
                    $stmt = $conn->prepare("UPDATE vouchers SET voucher_code = ?, discount_amount = ?, min_order_amount = ?, max_order_amount = ?, applicable_products = ?, is_active = ?, usage_limit = ?, valid_from = ?, valid_until = ? WHERE voucher_id = ? AND cafe_id = ?");
                    if ($stmt->execute([$voucher_code, $discount_amount, $min_order_amount, $max_order_amount, $applicable_products, $is_active, $usage_limit, $valid_from, $valid_until, $voucher_id, $cafe_id])) {
                        // Generate single QR code if enabled
                        if ($enable_qr) {
                            generateQRCode($conn, $voucher_id, $voucher_code);
                        }
                        $success = 'Voucher updated successfully';
                        header('Location: ../vouchers.php');
                        exit();
                    } else {
                        $error = 'Failed to update voucher';
                    }
                }
            } else {
                // Check if code already exists
                $stmt = $conn->prepare("SELECT voucher_id FROM vouchers WHERE voucher_code = ? AND cafe_id = ?");
                $stmt->execute([$voucher_code, $cafe_id]);
                if ($stmt->fetch()) {
                    $error = 'Voucher code already exists';
                } else {
                    $stmt = $conn->prepare("INSERT INTO vouchers (cafe_id, voucher_code, discount_amount, min_order_amount, max_order_amount, applicable_products, is_active, usage_limit, valid_from, valid_until) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt->execute([$cafe_id, $voucher_code, $discount_amount, $min_order_amount, $max_order_amount, $applicable_products, $is_active, $usage_limit, $valid_from, $valid_until])) {
                        $voucher_id = $conn->lastInsertId();
                        
                        // Generate single QR code if enabled
                        if ($enable_qr) {
                            generateQRCode($conn, $voucher_id, $voucher_code);
                        }
                        
                        $success = 'Voucher added successfully';
                        header('Location: ../vouchers.php');
                        exit();
                    } else {
                        $error = 'Failed to add voucher';
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

$page_title = $is_edit ? 'Edit Voucher' : 'Add Voucher';
include '../includes/header.php';

// Parse applicable products for edit
$selected_products = [];
if ($is_edit && !empty($voucher['applicable_products'])) {
    $selected_products = json_decode($voucher['applicable_products'], true);
    if (!is_array($selected_products)) {
        $selected_products = [];
    }
}
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;"><?php echo $is_edit ? 'Edit Voucher' : 'Add New Voucher'; ?></h2>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="form-container">
    <form method="POST">
        <div class="form-row">
            <div class="form-group">
                <label for="voucher_code">Voucher Code *</label>
                <input type="text" id="voucher_code" name="voucher_code" required 
                       value="<?php echo htmlspecialchars($voucher['voucher_code'] ?? ''); ?>" 
                       style="text-transform: uppercase;"
                       placeholder="e.g., DISCOUNT10">
                <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">Code will be converted to uppercase</p>
            </div>
            
            <div class="form-group">
                <label for="discount_amount">Discount Amount (Rp) *</label>
                <input type="number" id="discount_amount" name="discount_amount" step="0.01" min="0" required 
                       value="<?php echo $voucher['discount_amount'] ?? ''; ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="min_order_amount">Minimum Order Amount (Rp)</label>
                <input type="number" id="min_order_amount" name="min_order_amount" step="0.01" min="0" 
                       value="<?php echo $voucher['min_order_amount'] ?? 0; ?>">
                <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">Leave 0 for no minimum</p>
            </div>
            
            <div class="form-group">
                <label for="max_order_amount">Maximum Order Amount (Rp)</label>
                <input type="number" id="max_order_amount" name="max_order_amount" step="0.01" min="0" 
                       value="<?php echo $voucher['max_order_amount'] ?? ''; ?>">
                <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">Leave empty for no maximum</p>
            </div>
        </div>
        
        <div class="form-group">
            <label for="applicable_products">Applicable Products</label>
            <select id="applicable_products" name="applicable_products[]" multiple size="8" style="min-height: 150px;">
                <?php foreach ($products as $product): ?>
                    <option value="<?php echo $product['item_id']; ?>" 
                            <?php echo in_array($product['item_id'], $selected_products) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($product['item_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">Hold Ctrl/Cmd to select multiple products. Leave empty to apply to all products.</p>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="usage_limit">Usage Limit</label>
                <input type="number" id="usage_limit" name="usage_limit" min="1" 
                       value="<?php echo $voucher['usage_limit'] ?? ''; ?>">
                <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">Leave empty for unlimited usage</p>
            </div>
            
            <div class="form-group">
                <label for="valid_from">Valid From</label>
                <input type="date" id="valid_from" name="valid_from" 
                       value="<?php echo $voucher['valid_from'] ?? ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="valid_until">Valid Until</label>
                <input type="date" id="valid_until" name="valid_until" 
                       value="<?php echo $voucher['valid_until'] ?? ''; ?>">
            </div>
        </div>
        
        <div class="form-group" style="background: var(--accent-gray); padding: 15px; border-radius: 5px; margin-top: 20px;">
            <h3 style="color: var(--primary-white); margin-top: 0; margin-bottom: 15px; font-size: 16px;">QR Code Options</h3>
            
            <div>
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="enable_qr" name="enable_qr" <?php echo $has_qr_code ? 'checked' : ''; ?>>
                    <span>Enable QR Code for this voucher</span>
                </label>
                <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px; margin-left: 28px;">
                    If enabled, a single QR code will be created for this voucher. The QR code can be used up to the usage limit set above.
                </p>
                <?php if ($has_qr_code): ?>
                    <p style="color: #28a745; font-size: 12px; margin-top: 5px; margin-left: 28px;">
                        ✓ QR code already exists for this voucher
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" id="is_active" name="is_active" <?php echo (!isset($voucher) || $voucher['is_active']) ? 'checked' : ''; ?>>
                <span>Active (available for use in POS)</span>
            </label>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button type="submit" class="btn btn-primary"><?php echo $is_edit ? 'Update Voucher' : 'Add Voucher'; ?></button>
            <a href="../vouchers.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>

