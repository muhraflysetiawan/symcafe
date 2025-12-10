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

// Get cart from POST first, then session
$cart_data = [];
if (isset($_POST['cart_data']) && !empty($_POST['cart_data'])) {
    $cart_data = json_decode($_POST['cart_data'], true);
    // Save to session for persistence
    if (!empty($cart_data)) {
        $_SESSION['customer_cart'] = $cart_data;
    }
}

if (empty($cart_data)) {
    // Try to get from session
    $cart_data = $_SESSION['customer_cart'] ?? [];
}

if (empty($cart_data)) {
    $_SESSION['error'] = 'Your cart is empty';
    header('Location: customer_menu.php');
    exit();
}

// Get selected cafe
$cafe_id = $_SESSION['selected_cafe_id'] ?? 0;
if (!$cafe_id) {
    $_SESSION['error'] = 'Please select a store first';
    header('Location: customer_menu.php');
    exit();
}

// Get cafe info
$stmt = $conn->prepare("SELECT cafe_id, cafe_name, address, phone FROM cafes WHERE cafe_id = ?");
$stmt->execute([$cafe_id]);
$cafe = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cafe) {
    $_SESSION['error'] = 'Store not found';
    header('Location: customer_menu.php');
    exit();
}

// Get active payment categories from owner configuration
$payment_categories = [];
try {
    $stmt = $conn->prepare("SELECT category_name FROM payment_categories WHERE cafe_id = ? AND is_active = 1 ORDER BY category_name");
    $stmt->execute([$cafe_id]);
    $payment_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // Table might not exist, use defaults
    $payment_categories = [];
}

// Get tax percentage from cafe settings
$tax_percentage = 10.00; // Default
try {
    $columns = $conn->query("SHOW COLUMNS FROM cafe_settings")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('tax_percentage', $columns)) {
        $stmt = $conn->prepare("SELECT tax_percentage FROM cafe_settings WHERE cafe_id = ?");
        $stmt->execute([$cafe_id]);
        $tax_setting = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($tax_setting && isset($tax_setting['tax_percentage']) && $tax_setting['tax_percentage'] !== null) {
            $tax_percentage = (float)$tax_setting['tax_percentage'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching tax percentage: " . $e->getMessage());
}

// Calculate totals
$subtotal = 0;
foreach ($cart_data as $item) {
    $subtotal += ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
}
$tax = ($subtotal * $tax_percentage) / 100;
$total = $subtotal + $tax;

// Get theme colors - try to get from cafe's theme
$theme_colors = [
    'primary' => '#FFFFFF',
    'secondary' => '#252525',
    'accent' => '#3A3A3A'
];

try {
    if ($cafe_id > 0) {
        $stmt = $conn->prepare("SELECT primary_color, secondary_color, accent_color FROM cafe_settings WHERE cafe_id = ?");
        $stmt->execute([$cafe_id]);
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

// Create gradient colors from theme with opacity - matching index.php exactly
// Using slightly higher opacity to match index.php darker appearance
$gradient_start = hexToRgba($theme_colors['secondary'], 0.9);
$gradient_mid1 = hexToRgba(adjustBrightness($theme_colors['accent'], 10), 0.85);
$gradient_mid2 = hexToRgba(adjustBrightness($theme_colors['accent'], 20), 0.8);
$gradient_mid3 = hexToRgba(adjustBrightness($theme_colors['accent'], 30), 0.75);
$gradient_end = hexToRgba(adjustBrightness($theme_colors['primary'], -20), 0.7);

$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

$page_title = 'Checkout';
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

        /* Checkout Grid */
        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        /* Glass Cards */
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            margin-bottom: 25px;
        }

        .glass-card:last-child {
            margin-bottom: 0;
        }

        .glass-card h3 {
            color: white;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 20px;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .glass-card h4 {
            color: white;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            text-shadow: 0 1px 5px rgba(0, 0, 0, 0.3);
        }

        .cafe-info-card p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 15px;
            margin: 8px 0;
        }

        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: start;
            padding: 16px 20px;
            margin-bottom: 12px;
            background: rgba(60, 60, 60, 0.6);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .cart-item:last-child {
            margin-bottom: 0;
        }

        .cart-item-name {
            color: white;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .cart-item-details {
            color: rgba(255, 255, 255, 0.85);
            font-size: 14px;
        }

        .cart-item-price {
            color: #28a745;
            font-size: 18px;
            font-weight: 700;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding: 8px 0;
            color: rgba(255, 255, 255, 0.95);
            font-size: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .summary-row:last-of-type {
            border-bottom: none;
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid rgba(255, 255, 255, 0.2);
            width: 100%;
        }

        .summary-total-label {
            color: white;
            font-size: 20px;
            font-weight: 700;
        }

        .summary-total-value {
            color: #28a745;
            font-size: 24px;
            font-weight: 800;
            text-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
        }

        /* Form Styles */
        .form-container {
            background: transparent;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            color: white;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
            text-shadow: 0 1px 5px rgba(0, 0, 0, 0.3);
        }

        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 14px 18px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            color: white;
            font-size: 16px;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .form-group select option {
            background: <?php echo $theme_colors['secondary']; ?>;
            color: white;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.5);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .form-group textarea::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .btn-primary {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.25) 0%, rgba(255, 255, 255, 0.15) 100%);
            backdrop-filter: blur(10px);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.35) 0%, rgba(255, 255, 255, 0.25) 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 255, 255, 0.6);
        }

        .btn-secondary {
            width: 100%;
            padding: 14px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            font-size: 16px;
            font-weight: 500;
            text-decoration: none;
            text-align: center;
            display: block;
            transition: all 0.3s ease;
            margin-top: 15px;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.2);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 30px;
            border: 1px solid rgba(220, 53, 69, 0.4);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            color: #f8d7da;
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

            .checkout-grid {
                grid-template-columns: 1fr;
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
            <a href="customer_orders.php">My Orders</a>
            <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            <a href="logout.php">Logout</a>
        </div>
    </nav>

    <div class="main-content-wrapper">
        <h1 class="page-title">Checkout</h1>
        
        <?php if ($error): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="checkout-grid">
            <!-- Order Summary -->
            <div>
                <div class="glass-card cafe-info-card">
                    <h4><i class="fas fa-store"></i> <?php echo htmlspecialchars($cafe['cafe_name']); ?></h4>
                    <?php if ($cafe['address']): ?>
                        <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($cafe['address']); ?></p>
                    <?php endif; ?>
                    <?php if ($cafe['phone']): ?>
                        <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($cafe['phone']); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="glass-card">
                    <h4><i class="fas fa-shopping-cart"></i> Items</h4>
                    <?php foreach ($cart_data as $item): ?>
                        <div class="cart-item">
                            <div>
                                <div class="cart-item-name"><?php echo htmlspecialchars($item['name'] ?? 'Unknown'); ?></div>
                                <div class="cart-item-details">Qty: <?php echo $item['quantity'] ?? 0; ?> × <?php echo formatCurrency($item['price'] ?? 0); ?></div>
                            </div>
                            <div class="cart-item-price">
                                <?php echo formatCurrency(($item['price'] ?? 0) * ($item['quantity'] ?? 0)); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid rgba(255, 255, 255, 0.2);">
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span><?php echo formatCurrency($subtotal); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Tax (<?php echo number_format($tax_percentage, 2); ?>%):</span>
                            <span><?php echo formatCurrency($tax); ?></span>
                        </div>
                        <div class="summary-total">
                            <span class="summary-total-label">Total:</span>
                            <span class="summary-total-value"><?php echo formatCurrency($total); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Checkout Form -->
            <div>
                <div class="glass-card">
                    <h3><i class="fas fa-clipboard-list"></i> Order Details</h3>
                    <form method="POST" action="process_customer_order.php" class="form-container">
                        <div class="form-group">
                            <label for="order_type"><i class="fas fa-utensils"></i> Order Type *</label>
                            <select id="order_type" name="order_type" required>
                                <option value="take-away">Take Away</option>
                                <option value="dine-in">Dine In</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="customer_notes"><i class="fas fa-sticky-note"></i> Special Instructions (Optional)</label>
                            <textarea id="customer_notes" name="customer_notes" rows="4" placeholder="Any special requests or instructions..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_method"><i class="fas fa-credit-card"></i> Payment Method *</label>
                            <select id="payment_method" name="payment_method" required>
                                <?php if (empty($payment_categories)): ?>
                                    <option value="cash">Cash on Pickup</option>
                                    <option value="qris">QRIS</option>
                                    <option value="debit">Debit Card</option>
                                    <option value="credit">Credit Card</option>
                                <?php else: ?>
                                    <?php foreach ($payment_categories as $method): ?>
                                        <option value="<?php echo htmlspecialchars($method); ?>"><?php echo htmlspecialchars($method); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <input type="hidden" name="cart_data" value="<?php echo htmlspecialchars(json_encode($cart_data)); ?>">
                        <input type="hidden" name="cafe_id" value="<?php echo $cafe_id; ?>">
                        <input type="hidden" name="subtotal" value="<?php echo $subtotal; ?>">
                        <input type="hidden" name="tax" value="<?php echo $tax; ?>">
                        <input type="hidden" name="total" value="<?php echo $total; ?>">
                        
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-check"></i> Place Order
                        </button>
                        <a href="customer_menu.php" class="btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer-landing">
        <p style="margin: 0; font-size: 16px;">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
        <p style="margin-top: 10px; font-size: 14px;">Brewed with ❤️ for café lovers</p>
    </footer>
</body>
</html>

