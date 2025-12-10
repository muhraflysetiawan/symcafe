<?php
/**
 * API Endpoint: Customer Registration
 * POST /api/register.php
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$name = sanitizeInput($data['name'] ?? '');
$email = sanitizeInput($data['email'] ?? '');
$password = $data['password'] ?? '';
$phone = sanitizeInput($data['phone'] ?? '');

if (empty($name) || empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Name, email, and password are required']);
    exit();
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        exit();
    }
    
    // Check what columns exist in users table
    $columns = $conn->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $has_username = in_array('username', $columns);
    $has_phone = in_array('phone', $columns);
    
    // Create customer account - users table doesn't have phone column (it's in customers table)
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Generate username from email if username column exists
    if ($has_username) {
        // Use email prefix as username (before @)
        $username = explode('@', $email)[0];
        // Make sure username is unique by checking for duplicates
        $username_base = $username;
        $counter = 1;
        $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt_check->execute([$username]);
        while ($stmt_check->fetch()) {
            $username = $username_base . $counter;
            $counter++;
            $stmt_check->execute([$username]);
        }
        
        $stmt = $conn->prepare("INSERT INTO users (name, email, username, password, role, cafe_id) VALUES (?, ?, ?, ?, 'customer', NULL)");
        $stmt->execute([$name, $email, $username, $hashed_password]);
    } else {
        // No username column, insert without it
        if ($has_phone) {
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, phone, cafe_id) VALUES (?, ?, ?, 'customer', ?, NULL)");
            $stmt->execute([$name, $email, $hashed_password, $phone]);
        } else {
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, cafe_id) VALUES (?, ?, ?, 'customer', NULL)");
            $stmt->execute([$name, $email, $hashed_password]);
        }
    }
    
    $user_id = $conn->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful',
        'user' => [
            'id' => $user_id,
            'name' => $name,
            'email' => $email
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
}
?>

