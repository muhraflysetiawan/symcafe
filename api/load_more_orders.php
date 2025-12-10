<?php
require_once '../config/config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$offset = intval($input['offset'] ?? 0);
$limit = intval($input['limit'] ?? 10);
$customer_name = $input['customer_name'] ?? '';

if (empty($customer_name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Customer name is required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get customer ID
    $customer_id = $_SESSION['user_id'];
    
    // Get orders for this customer with offset and limit
    $stmt = $conn->prepare("
        SELECT DISTINCT o.*, c.cafe_name, c.address as cafe_address, c.phone as cafe_phone
        FROM orders o
        JOIN cafes c ON o.cafe_id = c.cafe_id
        JOIN customers cust ON o.customer_id = cust.customer_id
        WHERE cust.name = ?
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$customer_name, $limit, $offset]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if reviews table exists
    $reviews_table_exists = false;
    try {
        $conn->query("SELECT 1 FROM product_reviews LIMIT 1");
        $reviews_table_exists = true;
    } catch (Exception $e) {
        $reviews_table_exists = false;
    }
    
    // Get order items for each order
    foreach ($orders as &$order) {
        $stmt = $conn->prepare("
            SELECT oi.*, mi.item_name, mi.image
            FROM order_items oi
            JOIN menu_items mi ON oi.item_id = mi.item_id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order['order_id']]);
        $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check for existing reviews for each item
        if ($reviews_table_exists) {
            foreach ($order['items'] as &$item) {
                $stmt = $conn->prepare("
                    SELECT review_id, rating, comment 
                    FROM product_reviews 
                    WHERE item_id = ? AND customer_id = ? AND order_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$item['item_id'], $customer_id, $order['order_id']]);
                $item['review'] = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'status_colors' => [
            'pending' => '#ffc107',
            'processing' => '#17a2b8',
            'ready' => '#28a745',
            'completed' => '#6c757d',
            'cancelled' => '#dc3545'
        ],
        'reviews_table_exists' => $reviews_table_exists
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error loading orders: ' . $e->getMessage()]);
}

