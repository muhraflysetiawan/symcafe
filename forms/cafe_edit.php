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

// Get current café info
// Check if logo column exists first
$columns = $conn->query("SHOW COLUMNS FROM cafes")->fetchAll(PDO::FETCH_COLUMN);
$has_logo_column = in_array('logo', $columns);

if ($has_logo_column) {
    $stmt = $conn->prepare("SELECT * FROM cafes WHERE cafe_id = ?");
} else {
    $stmt = $conn->prepare("SELECT cafe_id, owner_id, cafe_name, address, description, phone, created_at FROM cafes WHERE cafe_id = ?");
}
$stmt->execute([$cafe_id]);
$cafe = $stmt->fetch(PDO::FETCH_ASSOC);

// Add logo field if column doesn't exist
if (!$has_logo_column) {
    $cafe['logo'] = null;
}

if (!$cafe) {
        header('Location: ../dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cafe_name = sanitizeInput($_POST['cafe_name'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    
    if (empty($cafe_name)) {
        $error = 'Café name is required';
    } else {
        // Handle logo upload (only if column exists)
        $logo_path = isset($cafe['logo']) ? $cafe['logo'] : null; // Keep existing logo by default
        
        if ($has_logo_column && isset($_FILES['logo']) && $_FILES['logo']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/logos/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = 'cafe_' . $cafe_id . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                    // Delete old logo if exists
                    if (!empty($cafe['logo']) && file_exists($cafe['logo'])) {
                        unlink($cafe['logo']);
                    }
                    // Store path relative to root (without ../ prefix)
                    $logo_path = 'uploads/logos/' . $new_filename;
                } else {
                    $error = 'Failed to upload logo';
                }
            } else {
                $error = 'Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.';
            }
        }
        
        if (empty($error)) {
            if ($has_logo_column) {
                $stmt = $conn->prepare("UPDATE cafes SET cafe_name = ?, address = ?, description = ?, phone = ?, logo = ? WHERE cafe_id = ?");
                $result = $stmt->execute([$cafe_name, $address, $description, $phone, $logo_path, $cafe_id]);
            } else {
                $stmt = $conn->prepare("UPDATE cafes SET cafe_name = ?, address = ?, description = ?, phone = ? WHERE cafe_id = ?");
                $result = $stmt->execute([$cafe_name, $address, $description, $phone, $cafe_id]);
            }
            
            if ($result) {
                $success = 'Café information updated successfully';
                // Reload café data
                $stmt = $conn->prepare("SELECT * FROM cafes WHERE cafe_id = ?");
                $stmt->execute([$cafe_id]);
                $cafe = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = 'Failed to update café information';
            }
        }
    }
}

$page_title = 'Edit Café Information';
include '../includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">Edit Café Information</h2>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="form-container">
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="cafe_name">Café Name *</label>
            <input type="text" id="cafe_name" name="cafe_name" required value="<?php echo htmlspecialchars($cafe['cafe_name']); ?>">
        </div>
        
        <div class="form-group">
            <label for="address">Address</label>
            <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($cafe['address'] ?? ''); ?></textarea>
        </div>
        
        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($cafe['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="form-group">
            <label for="phone">Phone</label>
            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($cafe['phone'] ?? ''); ?>">
        </div>
        
        <?php if ($has_logo_column): ?>
        <div class="form-group">
            <label for="logo">Café Logo (Optional)</label>
            <?php if (!empty($cafe['logo']) && file_exists('../' . $cafe['logo'])): ?>
                <div style="margin-bottom: 10px;">
                    <img src="../<?php echo htmlspecialchars($cafe['logo']); ?>" alt="Current Logo" style="max-width: 200px; max-height: 100px; border: 1px solid var(--border-gray); border-radius: 5px; padding: 5px;">
                    <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">Current logo</p>
                </div>
            <?php endif; ?>
            <input type="file" id="logo" name="logo" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
            <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">Recommended: Square image, max 2MB. Formats: JPG, PNG, GIF, WEBP</p>
        </div>
        <?php else: ?>
        <div class="alert alert-warning">
            <strong>Note:</strong> Logo feature is not available yet. Please run the database migration script first.
            <br><a href="../migrate_database.php" style="color: var(--primary-white); text-decoration: underline;">Run Migration Now</a>
        </div>
        <?php endif; ?>
        
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button type="submit" class="btn btn-primary">Update Information</button>
            <a href="../dashboard.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>

