<?php
require_once 'config/config.php';
requireLogin();
requireCafeSetup();

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

if (!isset($_GET['order_id'])) {
    header('Location: pos.php');
    exit();
}

$order_id = (int)$_GET['order_id'];

// Get order details - use LEFT JOIN for cashier to handle customer orders (NULL cashier_id)
$stmt = $conn->prepare("
    SELECT o.*, u.name as cashier_name, c.cafe_name, c.address as cafe_address, c.phone as cafe_phone, cust.name as customer_name
    FROM orders o
    LEFT JOIN users u ON o.cashier_id = u.user_id
    JOIN cafes c ON o.cafe_id = c.cafe_id
    LEFT JOIN customers cust ON o.customer_id = cust.customer_id
    WHERE o.order_id = ? AND o.cafe_id = ?
");
$stmt->execute([$order_id, $cafe_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: pos.php');
    exit();
}

// Get order items
$stmt = $conn->prepare("
    SELECT oi.*, mi.item_name
    FROM order_items oi
    JOIN menu_items mi ON oi.item_id = mi.item_id
    WHERE oi.order_id = ?
    ORDER BY oi.order_item_id
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get variations and add-ons for each item
$item_variations = [];
$item_addons = [];
try {
    foreach ($items as $item) {
        // Get variations
        $stmt = $conn->prepare("
            SELECT oiv.*, v.variation_name
            FROM order_item_variations oiv
            JOIN product_variations v ON oiv.variation_id = v.variation_id
            WHERE oiv.order_item_id = ?
        ");
        $stmt->execute([$item['order_item_id']]);
        $item_variations[$item['order_item_id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get add-ons
        $stmt = $conn->prepare("SELECT * FROM order_item_addons WHERE order_item_id = ?");
        $stmt->execute([$item['order_item_id']]);
        $item_addons[$item['order_item_id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Tables might not exist yet
}

// Get payment info
$stmt = $conn->prepare("SELECT * FROM payments WHERE order_id = ?");
$stmt->execute([$order_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .receipt-container {
            max-width: 400px;
            margin: 0 auto;
            background: white;
            color: black;
            padding: 30px;
            border-radius: 10px;
        }
        .receipt-header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .receipt-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dotted #ccc;
        }
        .receipt-total {
            border-top: 2px solid #000;
            padding-top: 10px;
            margin-top: 10px;
            font-weight: bold;
        }
        .receipt-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px dashed #000;
            font-size: 12px;
        }
        @media print {
            body {
                background: white;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="content" style="background: var(--primary-black);">
        <div class="receipt-container">
            <div class="receipt-header">
                <h2><?php echo htmlspecialchars($order['cafe_name']); ?></h2>
                <?php if ($order['cafe_address']): ?>
                    <p style="font-size: 12px; margin: 5px 0;"><?php echo htmlspecialchars($order['cafe_address']); ?></p>
                <?php endif; ?>
                <?php if ($order['cafe_phone']): ?>
                    <p style="font-size: 12px; margin: 5px 0;">Tel: <?php echo htmlspecialchars($order['cafe_phone']); ?></p>
                <?php endif; ?>
            </div>
            
            <div style="margin-bottom: 20px;">
                <p style="font-size: 12px; margin: 5px 0;"><strong>Order #:</strong> <?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></p>
                <p style="font-size: 12px; margin: 5px 0;"><strong>Date:</strong> <?php echo date('Y-m-d H:i:s', strtotime($order['created_at'])); ?></p>
                <?php if (!empty($order['customer_name'])): ?>
                    <p style="font-size: 12px; margin: 5px 0;"><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                <?php endif; ?>
                <?php if (!empty($order['cashier_name'])): ?>
                    <p style="font-size: 12px; margin: 5px 0;"><strong>Cashier:</strong> <?php echo htmlspecialchars($order['cashier_name']); ?></p>
                <?php else: ?>
                    <p style="font-size: 12px; margin: 5px 0;"><strong>Source:</strong> Online Order</p>
                <?php endif; ?>
                <p style="font-size: 12px; margin: 5px 0;"><strong>Type:</strong> <?php echo ucfirst(str_replace('-', ' ', $order['order_type'])); ?></p>
            </div>
            
            <div style="border-top: 2px dashed #000; padding-top: 10px; margin-bottom: 10px;"></div>
            
            <?php foreach ($items as $item): ?>
                <div class="receipt-item">
                    <div style="flex: 1;">
                        <div style="font-weight: bold;"><?php echo htmlspecialchars($item['item_name']); ?></div>
                        
                        <?php 
                        // Display variations
                        $variations = $item_variations[$item['order_item_id']] ?? [];
                        if (!empty($variations)) {
                            $variation_texts = [];
                            foreach ($variations as $var) {
                                $variation_texts[] = $var['variation_name'] . ': ' . $var['option_name'];
                            }
                            echo '<div style="font-size: 11px; color: #666; margin-top: 3px;">' . implode(', ', $variation_texts) . '</div>';
                        }
                        
                        // Display add-ons
                        $addons = $item_addons[$item['order_item_id']] ?? [];
                        if (!empty($addons)) {
                            $addon_texts = [];
                            foreach ($addons as $addon) {
                                $addon_texts[] = '+ ' . $addon['addon_name'] . ' (' . formatCurrency($addon['addon_price']) . ')';
                            }
                            echo '<div style="font-size: 11px; color: #666; margin-top: 3px;">' . implode(', ', $addon_texts) . '</div>';
                        }
                        ?>
                        
                        <div style="font-size: 12px; margin-top: 5px;"><?php echo $item['quantity']; ?> x <?php echo formatCurrency($item['price']); ?></div>
                    </div>
                    <div style="text-align: right;">
                        <?php echo formatCurrency($item['subtotal']); ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div style="border-top: 2px dashed #000; padding-top: 10px; margin-top: 10px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span>Subtotal:</span>
                    <span><?php echo formatCurrency($order['subtotal']); ?></span>
                </div>
                <?php if ($order['discount'] > 0): ?>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>Discount:</span>
                        <span>-<?php echo formatCurrency($order['discount']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($order['tax'] > 0): ?>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>Tax:</span>
                        <span><?php echo formatCurrency($order['tax']); ?></span>
                    </div>
                <?php endif; ?>
                <div class="receipt-total" style="display: flex; justify-content: space-between;">
                    <span>TOTAL:</span>
                    <span><?php echo formatCurrency($order['total_amount']); ?></span>
                </div>
            </div>
            
            <?php if ($payment): ?>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #000;">
                    <p style="font-size: 12px; margin: 5px 0;"><strong>Payment Method:</strong> <?php echo ucfirst($payment['payment_method'] ?? 'N/A'); ?></p>
                    <p style="font-size: 12px; margin: 5px 0;"><strong>Amount Paid:</strong> <?php echo formatCurrency($payment['amount']); ?></p>
                    <?php if (isset($payment['amount_given']) && $payment['amount_given'] > 0): ?>
                        <p style="font-size: 12px; margin: 5px 0;"><strong>Amount Received:</strong> <?php echo formatCurrency($payment['amount_given']); ?></p>
                    <?php endif; ?>
                    <?php if (isset($payment['change_amount']) && $payment['change_amount'] > 0): ?>
                        <p style="font-size: 12px; margin: 5px 0;"><strong>Change:</strong> <?php echo formatCurrency($payment['change_amount']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="receipt-footer">
                <p>Thank you for your visit!</p>
                <p style="margin-top: 10px;"><?php echo date('Y'); ?> <?php echo htmlspecialchars($order['cafe_name']); ?></p>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 20px;" class="no-print">
            <button onclick="window.print()" class="btn btn-primary">Print Receipt</button>
            <a href="pos.php" class="btn btn-secondary" style="margin-left: 10px;">New Transaction</a>
            <a href="dashboard.php" class="btn btn-secondary" style="margin-left: 10px;">Dashboard</a>
        </div>
    </div>
</body>
</html>

