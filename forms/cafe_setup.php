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
                
                // Create default settings (matching index/home colors)
                $stmt = $conn->prepare("INSERT INTO cafe_settings (cafe_id, primary_color, secondary_color, accent_color) VALUES (?, '#FFFFFF', '#0f172a', '#6366f1')");
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            overflow-x: hidden;
        }

        /* Background with Image and Gradient Overlay */
        .landing-page {
            min-height: 100vh;
            position: relative;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 25%, #334155 50%, #475569 75%, #64748b 100%);
            background-attachment: fixed;
        }

        .landing-page::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 40% 20%, rgba(236, 72, 153, 0.1) 0%, transparent 50%);
            z-index: 0;
            pointer-events: none;
            animation: gradientShift 15s ease infinite;
        }

        @keyframes gradientShift {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.8;
            }
        }

        .login-page {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 100px 20px 40px;
        }

        .login-container {
            width: 100%;
            max-width: 600px;
        }

        .login-box {
            background: rgba(30, 41, 59, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 50px;
            border: 2px solid rgba(99, 102, 241, 0.2);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            font-size: 36px;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff 0%, #e0e7ff 50%, #c7d2fe 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
        }

        .login-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            color: white;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
            text-shadow: 0 1px 5px rgba(0, 0, 0, 0.2);
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 14px 18px;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(99, 102, 241, 0.2);
            border-radius: 12px;
            color: white;
            font-size: 16px;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            background: rgba(15, 23, 42, 0.7);
            border-color: rgba(99, 102, 241, 0.5);
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 12px;
            margin-top: 5px;
        }

        .btn-primary {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 12px 32px rgba(99, 102, 241, 0.6);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fee2e2;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            text-align: center;
            font-weight: 500;
        }
    </style>
</head>
<body class="landing-page">
    <div class="login-page">
        <div class="login-container">
            <div class="login-box">
                <div class="login-header">
                    <h1>Setup Your Café</h1>
                    <p>Complete your café profile to get started</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert-error"><?php echo $error; ?></div>
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
                        <p>Recommended: Square image, max 2MB. Formats: JPG, PNG, GIF, WEBP</p>
                    </div>
                    
                    <button type="submit" class="btn-primary">Complete Setup</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

