<?php
// Konfigurasi umum aplikasi
define('BASE_URL', 'http://localhost/beandesk/');
define('APP_NAME', 'SYMCAFE');

// Konfigurasi session
session_start();

// Autoload classes
spl_autoload_register(function ($class_name) {
    $directories = [
        'classes/',
        'models/',
        'controllers/'
    ];
    
    foreach ($directories as $directory) {
        $file = $directory . $class_name . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Include database connection
require_once __DIR__ . '/database.php';

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireRole($allowedRoles) {
    requireLogin();
    if (!in_array($_SESSION['user_role'], $allowedRoles)) {
        header('Location: unauthorized.php');
        exit();
    }
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function formatCurrency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function getCafeId() {
    if (!isset($_SESSION['cafe_id'])) {
        $db = new Database();
        $conn = $db->getConnection();
        
        $user_id = $_SESSION['user_id'];
        
        // Check if user is owner
        $stmt = $conn->prepare("SELECT cafe_id FROM cafes WHERE owner_id = ?");
        $stmt->execute([$user_id]);
        $cafe = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($cafe) {
            $_SESSION['cafe_id'] = $cafe['cafe_id'];
            return $cafe['cafe_id'];
        }
        
        // Check if user is a cashier
        $stmt = $conn->prepare("SELECT cafe_id FROM users WHERE user_id = ? AND cafe_id IS NOT NULL");
        $stmt->execute([$user_id]);
        $cafe = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($cafe) {
            $_SESSION['cafe_id'] = $cafe['cafe_id'];
            return $cafe['cafe_id'];
        }
        
        return null;
    }
    return $_SESSION['cafe_id'];
}

function requireCafeSetup() {
    requireLogin();
    $cafe_id = getCafeId();
    if (!$cafe_id) {
        header('Location: forms/cafe_setup.php');
        exit();
    }
}

/**
 * Generate correct URL path based on current location
 * Handles both root directory and forms subdirectory
 */
function url($path) {
    // Remove leading slash if present
    $path = ltrim($path, '/');
    
    // Get current script directory
    $current_dir = dirname($_SERVER['PHP_SELF']);
    
    // Check if we're in forms directory
    if (strpos($current_dir, '/forms') !== false || strpos($current_dir, '\\forms') !== false) {
        // We're in forms directory, need to go up one level
        return '../' . $path;
    }
    
    // We're in root directory
    return $path;
}

function hasCafe() {
    return getCafeId() !== null;
}
?>
