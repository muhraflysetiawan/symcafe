<?php
require_once 'config/config.php';
requireLogin();

if ($_SESSION['user_role'] != 'customer') {
    header('Location: dashboard.php');
    exit();
}

// Function to adjust color brightness
function adjustBrightness($hex, $percent) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $r = max(0, min(255, $r + ($r * $percent / 100)));
    $g = max(0, min(255, $g + ($g * $percent / 100)));
    $b = max(0, min(255, $b + ($b * $percent / 100)));
    return '#' . str_pad(dechex(round($r)), 2, '0', STR_PAD_LEFT) . 
                 str_pad(dechex(round($g)), 2, '0', STR_PAD_LEFT) . 
                 str_pad(dechex(round($b)), 2, '0', STR_PAD_LEFT);
}

// Function to convert hex to rgba
function hexToRgba($hex, $alpha = 1) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return "rgba($r, $g, $b, $alpha)";
}

$db = new Database();
$conn = $db->getConnection();
$customer_id = $_SESSION['user_id'];

// Get theme colors - try to get from first cafe or use defaults
$theme_colors = [
    'primary' => '#FFFFFF',
    'secondary' => '#252525',
    'accent' => '#3A3A3A'
];

try {
    $stmt = $conn->prepare("SELECT cafe_id FROM cafes ORDER BY cafe_id LIMIT 1");
    $stmt->execute();
    $first_cafe = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($first_cafe) {
        $stmt = $conn->prepare("SELECT primary_color, secondary_color, accent_color FROM cafe_settings WHERE cafe_id = ?");
        $stmt->execute([$first_cafe['cafe_id']]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($settings) {
            $theme_colors = [
                'primary' => $settings['primary_color'],
                'secondary' => $settings['secondary_color'],
                'accent' => $settings['accent_color']
            ];
        }
    }
} catch (Exception $e) {
    // Use defaults
}

// Create gradient colors from theme with opacity
$gradient_start = hexToRgba($theme_colors['secondary'], 0.85);
$gradient_mid1 = hexToRgba(adjustBrightness($theme_colors['accent'], 10), 0.8);
$gradient_mid2 = hexToRgba(adjustBrightness($theme_colors['accent'], 20), 0.75);
$gradient_mid3 = hexToRgba(adjustBrightness($theme_colors['accent'], 30), 0.7);
$gradient_end = hexToRgba(adjustBrightness($theme_colors['primary'], -20), 0.65);

// Get customer name from users table
$stmt = $conn->prepare("SELECT name FROM users WHERE user_id = ? AND role = 'customer'");
$stmt->execute([$customer_id]);
$customer_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer_user) {
    $orders = [];
} else {
    $customer_name = $customer_user['name'];
    
    // Get initial orders for this customer (first 10)
    // Orders are linked via customers table which is created per cafe
    // Find all customer records with matching name and get their orders
    $initial_limit = 10;
    $stmt = $conn->prepare("
        SELECT DISTINCT o.*, c.cafe_name, c.address as cafe_address, c.phone as cafe_phone
        FROM orders o
        JOIN cafes c ON o.cafe_id = c.cafe_id
        JOIN customers cust ON o.customer_id = cust.customer_id
        WHERE cust.name = ?
        ORDER BY o.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$customer_name, $initial_limit]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count of orders
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT o.order_id) as total
        FROM orders o
        JOIN cafes c ON o.cafe_id = c.cafe_id
        JOIN customers cust ON o.customer_id = cust.customer_id
        WHERE cust.name = ?
    ");
    $stmt->execute([$customer_name]);
    $total_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_orders = $total_result['total'] ?? 0;
    $has_more = $total_orders > $initial_limit;
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
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
            background: url('<?php echo BASE_URL; ?>assets/bg.jpg') center/cover no-repeat fixed;
        }

        .landing-page::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, 
                <?php echo $gradient_start; ?> 0%, 
                <?php echo $gradient_mid1; ?> 25%, 
                <?php echo $gradient_mid2; ?> 50%, 
                <?php echo $gradient_mid3; ?> 75%, 
                <?php echo $gradient_end; ?> 100%);
            z-index: 0;
            pointer-events: none;
        }

        /* Glass Header */
        .landing-nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .landing-nav h1 {
            color: white;
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .landing-nav-links {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .landing-nav-links a {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 20px;
            transition: all 0.3s ease;
            font-weight: 500;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .landing-nav-links a:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .landing-nav-links a.active {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.6);
        }

        .landing-nav-links span {
            color: rgba(255, 255, 255, 0.9);
            padding: 10px 20px;
        }

        /* Main Content */
        .main-content-wrapper {
            position: relative;
            z-index: 1;
            padding: 150px 40px 80px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-title {
            font-size: 48px;
            font-weight: 800;
            color: white;
            margin-bottom: 40px;
            text-shadow: 0 3px 15px rgba(0, 0, 0, 0.4);
            letter-spacing: 1px;
        }

        /* Order Cards */
        .orders-container {
            display: grid;
            gap: 25px;
        }

        .order-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
            border-color: rgba(255, 255, 255, 0.4);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .order-header-left h3 {
            color: white;
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 8px 0;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .order-header-left p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 15px;
            margin: 5px 0;
        }

        .order-status {
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .order-total {
            color: #28a745;
            font-size: 22px;
            font-weight: 800;
            margin-top: 12px;
            text-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
        }

        .order-items {
            margin-top: 20px;
        }

        .order-items h4 {
            color: white;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            text-shadow: 0 1px 5px rgba(0, 0, 0, 0.3);
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .order-item-name {
            color: rgba(255, 255, 255, 0.95);
            font-size: 16px;
            font-weight: 500;
        }

        .order-item-price {
            color: white;
            font-size: 16px;
            font-weight: 600;
        }

        .review-btn {
            padding: 8px 16px;
            border-radius: 12px;
            font-size: 13px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            margin-top: 8px;
        }

        .review-btn.primary {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.3) 0%, rgba(255, 193, 7, 0.2) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 193, 7, 0.4);
            color: white;
        }

        .review-btn.primary:hover {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.4) 0%, rgba(255, 193, 7, 0.3) 100%);
            transform: translateY(-2px);
        }

        .review-btn.secondary {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
        }

        .review-btn.secondary:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .empty-state {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 60px 40px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .empty-state p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 18px;
            margin-bottom: 30px;
        }

        .btn-home {
            padding: 14px 30px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.25) 0%, rgba(255, 255, 255, 0.15) 100%);
            backdrop-filter: blur(10px);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-radius: 30px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-home:hover {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.35) 0%, rgba(255, 255, 255, 0.25) 100%);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        .alert {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            border-color: rgba(40, 167, 69, 0.4);
            color: #d4edda;
        }

        /* Footer */
        .footer-landing {
            position: relative;
            z-index: 1;
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(20px);
            padding: 40px;
            text-align: center;
            color: rgba(255, 255, 255, 0.9);
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            margin-top: 60px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-title {
                font-size: 36px;
            }

            .landing-nav {
                padding: 16px 20px;
                flex-direction: column;
                gap: 16px;
            }

            .landing-nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }

            .main-content-wrapper {
                padding: 120px 20px 60px;
            }
        }
    </style>
</head>
<body class="landing-page">
    <!-- Glass Navigation Bar -->
    <nav class="landing-nav">
        <h1><?php echo APP_NAME; ?></h1>
        <div class="landing-nav-links">
            <a href="index.php">Home</a>
            <a href="customer_orders.php" class="active">My Orders</a>
            <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            <a href="logout.php">Logout</a>
        </div>
    </nav>
    
    <div class="main-content-wrapper">
        <h1 class="page-title">My Orders</h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
            <?php if (isset($_GET['cart_cleared'])): ?>
                <script>
                    // Clear cart from localStorage after successful order
                    localStorage.removeItem('customer_cart');
                </script>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <i class="fas fa-shopping-bag" style="font-size: 64px; color: rgba(255, 255, 255, 0.5); margin-bottom: 20px;"></i>
                <p>You haven't placed any orders yet.</p>
                <a href="index.php" class="btn-home">
                    <i class="fas fa-home"></i> Back to Home
                </a>
            </div>
        <?php else: ?>
            <div class="orders-container" id="ordersContainer">
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-header-left">
                                <h3>Order #<?php echo $order['order_id']; ?></h3>
                                <p>
                                    <i class="fas fa-store"></i> <?php echo htmlspecialchars($order['cafe_name']); ?>
                                </p>
                                <p>
                                    <i class="fas fa-calendar"></i> <?php echo date('d M Y, H:i', strtotime($order['created_at'])); ?>
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
                                <span class="order-status" style="background: <?php echo $status_color; ?>; color: white;">
                                    <?php echo htmlspecialchars($order['order_status']); ?>
                                </span>
                                <p class="order-total">
                                    <?php echo formatCurrency($order['total_amount']); ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="order-items">
                            <h4><i class="fas fa-list"></i> Items</h4>
                            <?php foreach ($order['items'] as $item): ?>
                                <div class="order-item">
                                    <div>
                                        <div class="order-item-name">
                                            <?php echo htmlspecialchars($item['item_name']); ?> × <?php echo $item['quantity']; ?>
                                        </div>
                                        <?php if (isset($reviews_table_exists) && $reviews_table_exists && in_array($order['order_status'], ['completed', 'ready'])): ?>
                                            <?php if (isset($item['review']) && $item['review']): ?>
                                                <a href="submit_review.php?item_id=<?php echo $item['item_id']; ?>&order_id=<?php echo $order['order_id']; ?>" 
                                                   class="review-btn secondary">
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
                                                   class="review-btn primary">
                                                    <i class="fas fa-star"></i> Write Review
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="order-item-price">
                                        <?php echo formatCurrency($item['subtotal']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($order['customer_notes']): ?>
                            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255, 255, 255, 0.2);">
                                <p style="color: rgba(255, 255, 255, 0.9); margin: 0; font-size: 15px;">
                                    <strong><i class="fas fa-sticky-note"></i> Notes:</strong> <?php echo htmlspecialchars($order['customer_notes']); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (isset($has_more) && $has_more): ?>
                <div style="text-align: center; margin-top: 30px;">
                    <button id="loadMoreBtn" class="btn btn-primary" style="padding: 12px 30px; font-size: 16px;">
                        <i class="fas fa-chevron-down"></i> Load More Orders
                    </button>
                    <div id="loadingIndicator" style="display: none; margin-top: 20px; color: var(--primary-white);">
                        <i class="fas fa-spinner fa-spin"></i> Loading more orders...
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer-landing">
        <p style="margin: 0; font-size: 16px;">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
        <p style="margin-top: 10px; font-size: 14px;">Brewed with ❤️ for café lovers</p>
    </footer>

    <script>
        let currentOffset = <?php echo count($orders); ?>;
        const totalOrders = <?php echo $total_orders ?? 0; ?>;
        const customerName = <?php echo json_encode($customer_name ?? ''); ?>;
        const statusColors = <?php echo json_encode([
            'pending' => '#ffc107',
            'processing' => '#17a2b8',
            'ready' => '#28a745',
            'completed' => '#6c757d',
            'cancelled' => '#dc3545'
        ]); ?>;
        const reviewsTableExists = <?php echo $reviews_table_exists ? 'true' : 'false'; ?>;
        
        document.getElementById('loadMoreBtn')?.addEventListener('click', function() {
            const btn = this;
            const loadingIndicator = document.getElementById('loadingIndicator');
            const ordersContainer = document.getElementById('ordersContainer');
            
            btn.style.display = 'none';
            loadingIndicator.style.display = 'block';
            
            fetch('api/load_more_orders.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    offset: currentOffset,
                    limit: 10,
                    customer_name: customerName
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.orders.length > 0) {
                    data.orders.forEach(order => {
                        const orderCard = createOrderCard(order, statusColors, reviewsTableExists);
                        ordersContainer.insertAdjacentHTML('beforeend', orderCard);
                    });
                    
                    currentOffset += data.orders.length;
                    
                    if (currentOffset >= totalOrders) {
                        btn.style.display = 'none';
                    } else {
                        btn.style.display = 'inline-block';
                    }
                } else {
                    btn.style.display = 'none';
                }
                loadingIndicator.style.display = 'none';
            })
            .catch(error => {
                console.error('Error loading more orders:', error);
                loadingIndicator.style.display = 'none';
                btn.style.display = 'inline-block';
                alert('Failed to load more orders. Please try again.');
            });
        });
        
        function createOrderCard(order, statusColors, reviewsTableExists) {
            const statusColor = statusColors[order.order_status] || '#6c757d';
            const date = new Date(order.created_at);
            const formattedDate = date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + ', ' + 
                                 date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
            
            let itemsHtml = '';
            order.items.forEach(item => {
                let reviewHtml = '';
                if (reviewsTableExists && ['completed', 'ready'].includes(order.order_status)) {
                    if (item.review) {
                        const stars = '★'.repeat(item.review.rating) + '☆'.repeat(5 - item.review.rating);
                        reviewHtml = `<a href="submit_review.php?item_id=${item.item_id}&order_id=${order.order_id}" class="review-btn secondary">${stars} Edit Review</a>`;
                    } else {
                        reviewHtml = `<a href="submit_review.php?item_id=${item.item_id}&order_id=${order.order_id}" class="review-btn primary"><i class="fas fa-star"></i> Write Review</a>`;
                    }
                }
                itemsHtml += `
                    <div class="order-item">
                        <div>
                            <div class="order-item-name">${escapeHtml(item.item_name)} × ${item.quantity}</div>
                            ${reviewHtml}
                        </div>
                        <div class="order-item-price">${formatCurrency(item.subtotal)}</div>
                    </div>
                `;
            });
            
            const notesHtml = order.customer_notes ? `
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255, 255, 255, 0.2);">
                    <p style="color: rgba(255, 255, 255, 0.9); margin: 0; font-size: 15px;">
                        <strong><i class="fas fa-sticky-note"></i> Notes:</strong> ${escapeHtml(order.customer_notes)}
                    </p>
                </div>
            ` : '';
            
            return `
                <div class="order-card">
                    <div class="order-header">
                        <div class="order-header-left">
                            <h3>Order #${order.order_id}</h3>
                            <p><i class="fas fa-store"></i> ${escapeHtml(order.cafe_name)}</p>
                            <p><i class="fas fa-calendar"></i> ${formattedDate}</p>
                        </div>
                        <div style="text-align: right;">
                            <span class="order-status" style="background: ${statusColor}; color: white;">
                                ${escapeHtml(order.order_status)}
                            </span>
                            <p class="order-total">${formatCurrency(order.total_amount)}</p>
                        </div>
                    </div>
                    <div class="order-items">
                        <h4><i class="fas fa-list"></i> Items</h4>
                        ${itemsHtml}
                    </div>
                    ${notesHtml}
                </div>
            `;
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatCurrency(amount) {
            return 'Rp ' + parseFloat(amount).toLocaleString('id-ID');
        }
    </script>
</body>
</html>

