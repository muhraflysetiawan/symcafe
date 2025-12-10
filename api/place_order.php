<?php
/**
 * API Endpoint: Place Customer Order
 * POST /api/place_order.php
 */
// Suppress error display for production
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to prevent any unwanted output
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_clean();
    http_response_code(200);
    ob_end_flush();
    exit();
}

require_once __DIR__ . '/../config/config.php';

// Helper function to send clean JSON response
function sendJsonResponse($data, $httpCode = 200) {
    ob_clean();
    http_response_code($httpCode);
    echo json_encode($data);
    ob_end_flush();
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Try to get customer_id from session first (web), then from request data (mobile)
$customer_id = null;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'customer') {
    $customer_id = $_SESSION['user_id'];
}

// Get request data once
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

// Fallback for mobile apps: check request body for user_id
if (!$customer_id) {
    $user_id_param = isset($data['user_id']) ? (int)($data['user_id']) : null;
    
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
                error_log("User verification failed: user_id=$user_id_param not found or not a customer");
            }
        } catch (Exception $e) {
            error_log("Error verifying user_id: " . $e->getMessage());
        }
    } else {
        error_log("No user_id in request data and no session. Data keys: " . (isset($data) ? implode(',', array_keys($data ?: [])) : 'null'));
    }
}

if (!$customer_id) {
    sendJsonResponse([
        'success' => false, 
        'message' => 'Authentication required. Please login again.',
        'debug' => [
            'has_session' => isset($_SESSION['user_id']),
            'has_user_id_in_data' => isset($data['user_id']),
        ]
    ], 401);
}

$cart_data = $data['cart'] ?? [];
$cafe_id = (int)($data['cafe_id'] ?? 0);
$order_type = sanitizeInput($data['order_type'] ?? 'take-away');
$payment_method = sanitizeInput($data['payment_method'] ?? 'cash');
$customer_notes = sanitizeInput($data['customer_notes'] ?? '');
$subtotal = (float)($data['subtotal'] ?? 0);
$tax = (float)($data['tax'] ?? 0);
$total = (float)($data['total'] ?? 0);

if (empty($cart_data) || $cafe_id <= 0 || $total <= 0) {
    sendJsonResponse(['success' => false, 'message' => 'Invalid order data'], 400);
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $conn->beginTransaction();
    
    // Verify cafe exists
    $stmt = $conn->prepare("SELECT cafe_id FROM cafes WHERE cafe_id = ?");
    $stmt->execute([$cafe_id]);
    if (!$stmt->fetch()) {
        throw new Exception("Store not found");
    }
    
    // Get customer info
    $stmt = $conn->prepare("SELECT name, email FROM users WHERE user_id = ? AND role = 'customer'");
    $stmt->execute([$customer_id]);
    $customer_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer_user) {
        throw new Exception("Customer account not found");
    }
    
    // Create or get customer record in customers table
    $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE cafe_id = ? AND name = ? LIMIT 1");
    $stmt->execute([$cafe_id, $customer_user['name']]);
    $existing_customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $customer_record_id = null;
    if ($existing_customer) {
        $customer_record_id = $existing_customer['customer_id'];
    } else {
        $stmt = $conn->prepare("INSERT INTO customers (cafe_id, name) VALUES (?, ?)");
        $stmt->execute([$cafe_id, $customer_user['name']]);
        $customer_record_id = $conn->lastInsertId();
    }
    
    // Determine payment status
    $payment_status = (strtolower($payment_method) === 'cash' || stripos($payment_method, 'cash') !== false) ? 'unpaid' : 'paid';
    
    // Check columns
    $columns = $conn->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
    $has_order_source = in_array('order_source', $columns);
    $has_order_status = in_array('order_status', $columns);
    $has_customer_notes = in_array('customer_notes', $columns);
    $has_payment_method = in_array('payment_method', $columns);
    
    // Create order
    if ($has_order_source && $has_order_status && $has_customer_notes) {
        if ($has_payment_method) {
            $stmt = $conn->prepare("
                INSERT INTO orders (cafe_id, cashier_id, customer_id, order_type, subtotal, discount, tax, total_amount, payment_status, payment_method, order_status, order_source, customer_notes) 
                VALUES (?, NULL, ?, ?, ?, 0, ?, ?, ?, ?, 'pending', 'customer_online', ?)
            ");
            $stmt->execute([$cafe_id, $customer_record_id, $order_type, $subtotal, $tax, $total, $payment_status, $payment_method, $customer_notes]);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO orders (cafe_id, cashier_id, customer_id, order_type, subtotal, discount, tax, total_amount, payment_status, order_status, order_source, customer_notes) 
                VALUES (?, NULL, ?, ?, ?, 0, ?, ?, ?, 'pending', 'customer_online', ?)
            ");
            $stmt->execute([$cafe_id, $customer_record_id, $order_type, $subtotal, $tax, $total, $payment_status, $customer_notes]);
        }
    } else {
        if ($has_payment_method) {
            $stmt = $conn->prepare("
                INSERT INTO orders (cafe_id, cashier_id, customer_id, order_type, subtotal, discount, tax, total_amount, payment_status, payment_method) 
                VALUES (?, NULL, ?, ?, ?, 0, ?, ?, ?, ?)
            ");
            $stmt->execute([$cafe_id, $customer_record_id, $order_type, $subtotal, $tax, $total, $payment_status, $payment_method]);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO orders (cafe_id, cashier_id, customer_id, order_type, subtotal, discount, tax, total_amount, payment_status) 
                VALUES (?, NULL, ?, ?, ?, 0, ?, ?, ?)
            ");
            $stmt->execute([$cafe_id, $customer_record_id, $order_type, $subtotal, $tax, $total, $payment_status]);
        }
    }
    $order_id = $conn->lastInsertId();
    
    if (!$order_id || $order_id <= 0) {
        throw new Exception("Failed to create order");
    }
    
    // Add order items
    foreach ($cart_data as $item) {
        $item_id = (int)($item['id'] ?? 0);
        $quantity = (int)($item['quantity'] ?? 0);
        $price = (float)($item['price'] ?? 0);
        $subtotal_item = $price * $quantity;
        
        if ($item_id <= 0 || $quantity <= 0) {
            continue;
        }
        
        // Verify product exists and is available
        $stmt = $conn->prepare("SELECT item_id, stock, status FROM menu_items WHERE item_id = ? AND cafe_id = ?");
        $stmt->execute([$item_id, $cafe_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product || $product['status'] != 'available' || $product['stock'] < $quantity) {
            throw new Exception("Product unavailable or insufficient stock: " . ($item['name'] ?? 'Unknown'));
        }
        
        // Insert order item
        $stmt = $conn->prepare("INSERT INTO order_items (order_id, item_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$order_id, $item_id, $quantity, $price, $subtotal_item]);
        $order_item_id = $conn->lastInsertId();
        
        // Save variations
        if (isset($item['variations']) && is_array($item['variations']) && !empty($item['variations'])) {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO order_item_variations (order_item_id, variation_id, option_id, option_name, price_adjustment) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                foreach ($item['variations'] as $variation) {
                    $variation_id = (int)($variation['variation_id'] ?? 0);
                    $option_id = (int)($variation['option_id'] ?? 0);
                    $option_name = sanitizeInput($variation['option_name'] ?? '');
                    $price_adjustment = (float)($variation['price_adjustment'] ?? 0);
                    
                    if ($variation_id > 0 && $option_id > 0) {
                        $stmt->execute([$order_item_id, $variation_id, $option_id, $option_name, $price_adjustment]);
                    }
                }
            } catch (Exception $e) {
                error_log("Error saving variations: " . $e->getMessage());
            }
        }
        
        // Save add-ons
        if (isset($item['addons']) && is_array($item['addons']) && !empty($item['addons'])) {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO order_item_addons (order_item_id, addon_id, addon_name, addon_price) 
                    VALUES (?, ?, ?, ?)
                ");
                foreach ($item['addons'] as $addon) {
                    $addon_id = (int)($addon['addon_id'] ?? 0);
                    $addon_name = sanitizeInput($addon['addon_name'] ?? '');
                    $addon_price = (float)($addon['price'] ?? 0);
                    
                    if ($addon_id > 0) {
                        $stmt->execute([$order_item_id, $addon_id, $addon_name, $addon_price]);
                    }
                }
            } catch (Exception $e) {
                error_log("Error saving addons: " . $e->getMessage());
            }
        }
        
        // Update product stock
        $stmt = $conn->prepare("UPDATE menu_items SET stock = stock - ? WHERE item_id = ? AND cafe_id = ?");
        $stmt->execute([$quantity, $item_id, $cafe_id]);
        
        $stmt = $conn->prepare("UPDATE menu_items SET status = 'unavailable' WHERE item_id = ? AND cafe_id = ? AND stock <= 0");
        $stmt->execute([$item_id, $cafe_id]);
    }
    
    // Create payment record if paid
    if ($payment_status === 'paid') {
        try {
            $payment_columns = $conn->query("SHOW COLUMNS FROM payments")->fetchAll(PDO::FETCH_COLUMN);
            $has_payment_method = in_array('payment_method', $payment_columns);
            
            if ($has_payment_method) {
                $stmt = $conn->prepare("INSERT INTO payments (order_id, payment_method, amount) VALUES (?, ?, ?)");
                $stmt->execute([$order_id, $payment_method, $total]);
            } else {
                $stmt = $conn->prepare("INSERT INTO payments (order_id, amount) VALUES (?, ?)");
                $stmt->execute([$order_id, $total]);
            }
        } catch (Exception $e) {
            error_log("Could not create payment record: " . $e->getMessage());
        }
    }
    
    $conn->commit();
    
    sendJsonResponse([
        'success' => true,
        'message' => 'Order placed successfully',
        'order_id' => $order_id,
        'payment_status' => $payment_status
    ]);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    sendJsonResponse(['success' => false, 'message' => 'Order failed: ' . $e->getMessage()], 500);
}
?>

