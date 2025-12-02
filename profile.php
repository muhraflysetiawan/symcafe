<?php
require_once 'config/config.php';
requireLogin();

$db = new Database();
$conn = $db->getConnection();

$error = '';
$success = '';

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($name) || empty($email) || empty($username)) {
        $error = 'All fields are required';
    } elseif (!empty($password) && $password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (!empty($password) && strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        // Check if username or email already exists (excluding current user)
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
        $stmt->execute([$username, $email, $user_id]);
        if ($stmt->fetch()) {
            $error = 'Username or email already exists';
        } else {
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, username = ?, password = ? WHERE user_id = ?");
                $stmt->execute([$name, $email, $username, $hashed_password, $user_id]);
            } else {
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, username = ? WHERE user_id = ?");
                $stmt->execute([$name, $email, $username, $user_id]);
            }
            
            if ($stmt->rowCount() > 0 || empty($password)) {
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['username'] = $username;
                $success = 'Profile updated successfully';
                
                // Reload user data
                $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = 'Failed to update profile';
            }
        }
    }
}

$page_title = 'Profile';
include 'includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">User Profile</h2>

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
                <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($user['name']); ?>">
            </div>
            
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($user['email']); ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="username">Username *</label>
                <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($user['username']); ?>">
            </div>
            
            <div class="form-group">
                <label for="role">Role</label>
                <input type="text" id="role" value="<?php echo ucfirst($user['role']); ?>" disabled style="background: var(--accent-gray);">
            </div>
        </div>
        
        <div style="border-top: 1px solid var(--border-gray); padding-top: 20px; margin-top: 20px;">
            <h3 style="color: var(--primary-white); margin-bottom: 15px;">Change Password (Optional)</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" minlength="6">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" minlength="6">
                </div>
            </div>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button type="submit" class="btn btn-primary">Update Profile</button>
            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>

