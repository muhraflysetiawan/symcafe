<?php
/**
 * API Endpoint: Get Customer Orders
 * GET /api/orders.php
 */
// Disable error display to prevent HTML in JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/config.php';

// Try to get customer_id from session first (web), then from request params (mobile)
$customer_id = null;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'customer') {
    $customer_id = $_SESSION['user_id'];
}

// Fallback for mobile apps: check GET parameter
if (!$customer_id) {
    $user_id_param = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    
    if ($user_id_param) {
        // Verify the user exists and is a customer
        try {
            $db = new Database();
            $conn = $db->getConnection();
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? AND role = 'customer'");
            $stmt->execute([$user_id_param]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $customer_id = $user_id_param;
            } else {
                error_log("Orders API: User verification failed - user_id=$user_id_param not found or not a customer");
            }
        } catch (Exception $e) {
            error_log("Orders API: Error verifying user_id - " . $e->getMessage());
        }
    } else {
        error_log("Orders API: No user_id in GET params and no session");
    }
}

if (!$customer_id) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Authentication required. Please login again.',
        'debug' => [
            'has_session' => isset($_SESSION['user_id']),
            'has_user_id_param' => isset($_GET['user_id']),
            'user_id_value' => $_GET['user_id'] ?? null,
        ]
    ]);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get customer name
    $stmt = $conn->prepare("SELECT name FROM users WHERE user_id = ? AND role = 'customer'");
    $stmt->execute([$customer_id]);
    $customer_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer_user) {
        echo json_encode(['success' => true, 'orders' => []]);
        exit();
    }
    
    $customer_name = $customer_user['name'];
    
    // Get all orders for this customer
    $stmt = $conn->prepare("
        SELECT DISTINCT o.*, c.cafe_name, c.address as cafe_address, c.phone as cafe_phone, c.logo
        FROM orders o
        JOIN cafes c ON o.cafe_id = c.cafe_id
        JOIN customers cust ON o.customer_id = cust.customer_id
        WHERE cust.name = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$customer_name]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get order items for each order
    foreach ($orders as &$order) {
        $order_id = $order['order_id'];
        
        // Get items
        $stmt = $conn->prepare("
            SELECT oi.*, mi.item_name, mi.image
            FROM order_items oi
            JOIN menu_items mi ON oi.item_id = mi.item_id
            WHERE oi.order_id = ?
            ORDER BY oi.order_item_id
        ");
        $stmt->execute([$order_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get variations and add-ons for each item
        foreach ($items as &$item) {
            $order_item_id = $item['order_item_id'];
            
            // Get variations
            try {
                $stmt = $conn->prepare("
                    SELECT oiv.*, v.variation_name
                    FROM order_item_variations oiv
                    JOIN product_variations v ON oiv.variation_id = v.variation_id
                    WHERE oiv.order_item_id = ?
                ");
                $stmt->execute([$order_item_id]);
                $item['variations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $item['variations'] = [];
            }
            
            // Get add-ons
            try {
                $stmt = $conn->prepare("SELECT * FROM order_item_addons WHERE order_item_id = ?");
                $stmt->execute([$order_item_id]);
                $item['addons'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $item['addons'] = [];
            }
            
            // Convert image to URL
            if ($item['image'] && file_exists($item['image'])) {
                $item['image_url'] = BASE_URL . $item['image'];
            } else {
                $item['image_url'] = null;
            }
        }
        
        $order['items'] = $items;
        
        // Get payment info
        try {
            $stmt = $conn->prepare("SELECT * FROM payments WHERE order_id = ?");
            $stmt->execute([$order_id]);
            $order['payment'] = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $order['payment'] = null;
        }
        
        // Process cafe logo URL
        if (!empty($order['logo'])) {
            $logo_path = ltrim($order['logo'], '/');
            $logo_path = str_replace('../', '', $logo_path);
            $order['logo_url'] = $logo_path;
        } else {
            $order['logo_url'] = null;
        }
    }
    
    echo json_encode([
        'success' => true,
        'orders' => $orders
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

