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
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div style="max-width: 800px; margin: 50px auto; padding: 20px;">
        <h2 style="color: var(--primary-white); margin-bottom: 30px;">Checkout</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            <!-- Order Summary -->
            <div>
                <h3 style="color: var(--primary-white); margin-bottom: 20px;">Order Summary</h3>
                <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <h4 style="color: var(--primary-white); margin: 0 0 10px 0;"><?php echo htmlspecialchars($cafe['cafe_name']); ?></h4>
                    <?php if ($cafe['address']): ?>
                        <p style="color: var(--text-gray); margin: 5px 0; font-size: 14px;">📍 <?php echo htmlspecialchars($cafe['address']); ?></p>
                    <?php endif; ?>
                </div>
                
                <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
                    <h4 style="color: var(--primary-white); margin: 0 0 15px 0;">Items</h4>
                    <?php foreach ($cart_data as $item): ?>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid var(--border-gray);">
                            <div>
                                <p style="color: var(--primary-white); margin: 0; font-weight: 600;"><?php echo htmlspecialchars($item['name'] ?? 'Unknown'); ?></p>
                                <p style="color: var(--text-gray); margin: 5px 0 0 0; font-size: 14px;">Qty: <?php echo $item['quantity'] ?? 0; ?> × <?php echo formatCurrency($item['price'] ?? 0); ?></p>
                            </div>
                            <p style="color: #28a745; margin: 0; font-weight: bold;">
                                <?php echo formatCurrency(($item['price'] ?? 0) * ($item['quantity'] ?? 0)); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                    
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--border-gray);">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="color: var(--text-gray);">Subtotal:</span>
                            <span style="color: var(--primary-white);"><?php echo formatCurrency($subtotal); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="color: var(--text-gray);">Tax (<?php echo number_format($tax_percentage, 2); ?>%):</span>
                            <span style="color: var(--primary-white);"><?php echo formatCurrency($tax); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border-gray);">
                            <span style="color: var(--primary-white); font-size: 18px; font-weight: bold;">Total:</span>
                            <span style="color: #28a745; font-size: 20px; font-weight: bold;"><?php echo formatCurrency($total); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Checkout Form -->
            <div>
                <h3 style="color: var(--primary-white); margin-bottom: 20px;">Order Details</h3>
                <form method="POST" action="process_customer_order.php" class="form-container">
                    <div class="form-group">
                        <label for="order_type">Order Type *</label>
                        <select id="order_type" name="order_type" required>
                            <option value="take-away">Take Away</option>
                            <option value="dine-in">Dine In</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="customer_notes">Special Instructions (Optional)</label>
                        <textarea id="customer_notes" name="customer_notes" rows="4" placeholder="Any special requests or instructions..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_method">Payment Method *</label>
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
                    
                    <button type="submit" class="btn btn-primary btn-block" style="margin-top: 20px;">Place Order</button>
                    <a href="customer_menu.php" class="btn btn-secondary btn-block" style="margin-top: 10px; text-align: center; display: block;">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

