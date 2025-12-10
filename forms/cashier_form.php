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
$cashier = null;
$is_edit = false;

// Handle edit - get cashier data
if (isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? AND cafe_id = ? AND role = 'cashier'");
    $stmt->execute([$user_id, $cafe_id]);
    $cashier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($cashier) {
        $is_edit = true;
    } else {
        header('Location: cashiers.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($name) || empty($email) || empty($username)) {
        $error = 'All fields are required';
    } elseif (!$is_edit && empty($password)) {
        $error = 'Password is required for new cashier';
    } elseif (!$is_edit && strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        if ($is_edit && isset($_GET['id'])) {
            $user_id = (int)$_GET['id'];
            
            // Check if username or email already exists (excluding current user)
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
            $stmt->execute([$username, $email, $user_id]);
            if ($stmt->fetch()) {
                $error = 'Username or email already exists';
            } else {
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, username = ?, password = ? WHERE user_id = ? AND cafe_id = ?");
                    $stmt->execute([$name, $email, $username, $hashed_password, $user_id, $cafe_id]);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, username = ? WHERE user_id = ? AND cafe_id = ?");
                    $stmt->execute([$name, $email, $username, $user_id, $cafe_id]);
                }
                
                if ($stmt->rowCount() > 0) {
                    $success = 'Cashier updated successfully';
                } else {
                    $error = 'Failed to update cashier';
                }
            }
        } else {
            // Check if username or email already exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = 'Username or email already exists';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (name, email, username, password, role, cafe_id) VALUES (?, ?, ?, ?, 'cashier', ?)");
                if ($stmt->execute([$name, $email, $username, $hashed_password, $cafe_id])) {
                    $success = 'Cashier added successfully';
                    header('Location: ../cashiers.php');
                    exit();
                } else {
                    $error = 'Failed to add cashier';
                }
            }
        }
    }
}

$page_title = $is_edit ? 'Edit Cashier' : 'Add Cashier';
include '../includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;"><?php echo $is_edit ? 'Edit Cashier' : 'Add New Cashier'; ?></h2>

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
                <label for="name">Full Name *</label>
                <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($cashier['name'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($cashier['email'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="username">Username *</label>
                <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($cashier['username'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password <?php echo $is_edit ? '(leave blank to keep current)' : '*'; ?></label>
                <input type="password" id="password" name="password" <?php echo $is_edit ? '' : 'required'; ?> minlength="6">
            </div>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button type="submit" class="btn btn-primary"><?php echo $is_edit ? 'Update Cashier' : 'Add Cashier'; ?></button>
            <a href="../cashiers.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>

