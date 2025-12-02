<?php
require_once 'config/config.php';
requireLogin();

if ($_SESSION['user_role'] != 'customer') {
    header('Location: dashboard.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$customer_id = $_SESSION['user_id'];

// Get customer name from users table
$stmt = $conn->prepare("SELECT name FROM users WHERE user_id = ? AND role = 'customer'");
$stmt->execute([$customer_id]);
$customer_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer_user) {
    $orders = [];
} else {
    $customer_name = $customer_user['name'];
    
    // Get all orders for this customer
    // Orders are linked via customers table which is created per cafe
    // Find all customer records with matching name and get their orders
    $stmt = $conn->prepare("
        SELECT DISTINCT o.*, c.cafe_name, c.address as cafe_address, c.phone as cafe_phone
        FROM orders o
        JOIN cafes c ON o.cafe_id = c.cafe_id
        JOIN customers cust ON o.customer_id = cust.customer_id
        WHERE cust.name = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$customer_name]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

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

$page_title = 'My Orders';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Customer Navigation -->
    <nav style="background: var(--primary-black); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-gray);">
        <h1 style="color: var(--primary-white); margin: 0; font-size: 24px;"><?php echo APP_NAME; ?></h1>
        <div style="display: flex; gap: 15px; align-items: center;">
            <a href="index.php" style="color: var(--primary-white); text-decoration: none;">Home</a>
            <a href="customer_orders.php" style="color: var(--primary-white); text-decoration: none; font-weight: bold;">My Orders</a>
            <span style="color: var(--text-gray);"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            <a href="logout.php" style="color: var(--text-gray); text-decoration: none;">Logout</a>
        </div>
    </nav>
    
    <div style="max-width: 1200px; margin: 50px auto; padding: 20px;">
        <h2 style="color: var(--primary-white); margin-bottom: 30px;">My Orders</h2>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
            <?php if (isset($_GET['cart_cleared'])): ?>
                <script>
                    // Clear cart from localStorage after successful order
                    localStorage.removeItem('customer_cart');
                </script>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (empty($orders)): ?>
            <div style="padding: 40px; text-align: center; color: var(--text-gray);">
                <p>You haven't placed any orders yet.</p>
                <a href="index.php" class="btn btn-primary" style="margin-top: 20px; display: inline-block;">Back to Home</a>
            </div>
        <?php else: ?>
            <div style="display: grid; gap: 20px;">
                <?php foreach ($orders as $order): ?>
                    <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                            <div>
                                <h3 style="color: var(--primary-white); margin: 0 0 5px 0;">Order #<?php echo $order['order_id']; ?></h3>
                                <p style="color: var(--text-gray); margin: 5px 0; font-size: 14px;">
                                    <?php echo htmlspecialchars($order['cafe_name']); ?> • 
                                    <?php echo date('d M Y, H:i', strtotime($order['created_at'])); ?>
                                </p>
                            </div>
                            <div style="text-align: right;">
                                <?php
                                $status_colors = [
                                    'pending' => '#ffc107',
                                    'processing' => '#17a2b8',
                                    'ready' => '#28a745',
                                    'completed' => '#6c757d',
                                    'cancelled' => '#dc3545'
                                ];
                                $status_color = $status_colors[$order['order_status']] ?? '#6c757d';
                                ?>
                                <span style="background: <?php echo $status_color; ?>; color: white; padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase;">
                                    <?php echo htmlspecialchars($order['order_status']); ?>
                                </span>
                                <p style="color: #28a745; font-size: 18px; font-weight: bold; margin: 10px 0 0 0;">
                                    <?php echo formatCurrency($order['total_amount']); ?>
                                </p>
                            </div>
                        </div>
                        
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border-gray);">
                            <h4 style="color: var(--primary-white); margin: 0 0 10px 0; font-size: 14px;">Items:</h4>
                            <?php foreach ($order['items'] as $item): ?>
                                <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--border-gray);">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                        <span style="color: var(--text-gray);">
                                            <?php echo htmlspecialchars($item['item_name']); ?> × <?php echo $item['quantity']; ?>
                                        </span>
                                        <span style="color: var(--primary-white);">
                                            <?php echo formatCurrency($item['subtotal']); ?>
                                        </span>
                                    </div>
                                    <?php if (isset($reviews_table_exists) && $reviews_table_exists && in_array($order['order_status'], ['completed', 'ready'])): ?>
                                        <div style="display: flex; gap: 10px; align-items: center;">
                                            <?php if (isset($item['review']) && $item['review']): ?>
                                                <a href="submit_review.php?item_id=<?php echo $item['item_id']; ?>&order_id=<?php echo $order['order_id']; ?>" 
                                                   class="btn" 
                                                   style="padding: 5px 15px; font-size: 12px; background: var(--accent-gray); border: 1px solid var(--border-gray); color: var(--primary-white); text-decoration: none; border-radius: 4px;">
                                                    <?php
                                                    $stars = '';
                                                    for ($i = 0; $i < 5; $i++) {
                                                        $stars .= $i < $item['review']['rating'] ? '★' : '☆';
                                                    }
                                                    echo $stars . ' Edit Review';
                                                    ?>
                                                </a>
                                            <?php else: ?>
                                                <a href="submit_review.php?item_id=<?php echo $item['item_id']; ?>&order_id=<?php echo $order['order_id']; ?>" 
                                                   class="btn btn-primary" 
                                                   style="padding: 5px 15px; font-size: 12px; text-decoration: none; border-radius: 4px;">
                                                    ⭐ Write Review
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($order['customer_notes']): ?>
                            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border-gray);">
                                <p style="color: var(--text-gray); margin: 0; font-size: 14px;">
                                    <strong>Notes:</strong> <?php echo htmlspecialchars($order['customer_notes']); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

