<?php
require_once '../config/config.php';
requireLogin();

$error = '';

// Check if user already has a café
$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->prepare("SELECT cafe_id FROM cafes WHERE owner_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$existing_cafe = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing_cafe) {
    header('Location: ../index.php');
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
        // Handle logo upload
        $logo_path = null;
        
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/logos/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $temp_filename = 'temp_' . time() . '.' . $file_extension;
                $temp_path = $upload_dir . $temp_filename;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $temp_path)) {
                    $logo_path = $temp_path;
                } else {
                    $error = 'Failed to upload logo';
                }
            } else {
                $error = 'Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.';
            }
        }
        
        if (empty($error)) {
            // Insert cafe first without logo (we'll update it after getting cafe_id)
            $stmt = $conn->prepare("INSERT INTO cafes (owner_id, cafe_name, address, description, phone, logo) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$_SESSION['user_id'], $cafe_name, $address, $description, $phone, null])) {
                $cafe_id = $conn->lastInsertId();
                $_SESSION['cafe_id'] = $cafe_id;
                
                // Rename logo file with cafe_id and update database
                if ($logo_path) {
                    $file_extension = pathinfo($logo_path, PATHINFO_EXTENSION);
                    $new_filename = 'cafe_' . $cafe_id . '_' . time() . '.' . $file_extension;
                    $new_logo_path_full = $upload_dir . $new_filename;
                    // Store path relative to root (without ../ prefix)
                    $new_logo_path_db = 'uploads/logos/' . $new_filename;
                    if (file_exists($logo_path)) {
                        rename($logo_path, $new_logo_path_full);
                        $stmt = $conn->prepare("UPDATE cafes SET logo = ? WHERE cafe_id = ?");
                        $stmt->execute([$new_logo_path_db, $cafe_id]);
                    }
                }
                
                // Create default category
                $stmt = $conn->prepare("INSERT INTO menu_categories (cafe_id, category_name) VALUES (?, 'General')");
                $stmt->execute([$cafe_id]);
                
                // Create default settings
                $stmt = $conn->prepare("INSERT INTO cafe_settings (cafe_id, primary_color, secondary_color, accent_color) VALUES (?, '#FFFFFF', '#252525', '#3A3A3A')");
                $stmt->execute([$cafe_id]);
                
                header('Location: ../index.php');
                exit();
            } else {
                $error = 'Failed to create café. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Café Setup - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="login-page">
        <div class="login-container" style="max-width: 600px;">
            <div class="login-box">
                <div class="login-header">
                    <h1>Setup Your Café</h1>
                    <p>Complete your café profile to get started</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" class="login-form" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="cafe_name">Café Name *</label>
                        <input type="text" id="cafe_name" name="cafe_name" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="text" id="phone" name="phone">
                    </div>
                    
                    <div class="form-group">
                        <label for="logo">Café Logo (Optional)</label>
                        <input type="file" id="logo" name="logo" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                        <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">Recommended: Square image, max 2MB. Formats: JPG, PNG, GIF, WEBP</p>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-full">Complete Setup</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

