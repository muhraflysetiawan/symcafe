<?php
require_once 'config/config.php';
requireLogin();
requireCafeSetup();

// Only cashiers and owners can access
if (!in_array($_SESSION['user_role'], ['cashier', 'owner'])) {
    header('Location: dashboard.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

// Check and add payment_method column to orders table if it doesn't exist
try {
    $columns = $conn->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('payment_method', $columns)) {
        // Add payment_method column to orders table
        $conn->exec("ALTER TABLE orders ADD COLUMN payment_method VARCHAR(50) NULL AFTER payment_status");
    }
} catch (Exception $e) {
    error_log("Error checking/adding payment_method column to orders: " . $e->getMessage());
}

// Check and add customer_cash_payment status to order_status ENUM if it doesn't exist
try {
    $stmt = $conn->query("SHOW COLUMNS FROM orders WHERE Field = 'order_status'");
    $column_info = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($column_info) {
        $enum_values = $column_info['Type'];
        if (stripos($enum_values, 'customer_cash_payment') === false) {
            // Modify ENUM to include customer_cash_payment
            $conn->exec("ALTER TABLE orders MODIFY COLUMN order_status ENUM('pending', 'customer_cash_payment', 'processing', 'ready', 'completed', 'cancelled') DEFAULT 'pending'");
        }
    }
} catch (Exception $e) {
    error_log("Error checking/adding customer_cash_payment status: " . $e->getMessage());
}

// Check and add amount_given and change_amount columns to payments table if they don't exist
try {
    $payment_columns = $conn->query("SHOW COLUMNS FROM payments")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('amount_given', $payment_columns)) {
        $conn->exec("ALTER TABLE payments ADD COLUMN amount_given DECIMAL(10,2) NULL AFTER amount");
    }
    if (!in_array('change_amount', $payment_columns)) {
        $conn->exec("ALTER TABLE payments ADD COLUMN change_amount DECIMAL(10,2) NULL AFTER amount_given");
    }
} catch (Exception $e) {
    error_log("Error checking/adding payment columns: " . $e->getMessage());
}

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['update_status']) || isset($_POST['cancel_order']))) {
    $order_id = (int)($_POST['order_id'] ?? 0);
    
    // Determine if this is a cancel action or update action
    if (isset($_POST['cancel_order'])) {
        $new_status = 'cancelled';
    } else {
        // Auto-determine next status - will be calculated based on current status and payment method
        $new_status = null; // Will be set below
    }
    
    if ($order_id > 0) {
        try {
            $conn->beginTransaction();
            
            // Get order details before updating
            $stmt = $conn->prepare("SELECT payment_status, total_amount, order_status FROM orders WHERE order_id = ? AND cafe_id = ?");
            $stmt->execute([$order_id, $cafe_id]);
            $order_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order_data) {
                throw new Exception("Order not found");
            }
            
            $old_status = $order_data['order_status'];
            
            // Determine next status based on current status and payment method if not cancelled
            if ($new_status != 'cancelled') {
                // Get payment method to determine flow
                $columns = $conn->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
                $has_payment_method = in_array('payment_method', $columns);
                
                $payment_method = null;
                if ($has_payment_method) {
                    $stmt = $conn->prepare("SELECT payment_method FROM orders WHERE order_id = ?");
                    $stmt->execute([$order_id]);
                    $pm_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    $payment_method = $pm_data['payment_method'] ?? null;
                }
                
                // Check if it's a cash payment
                $is_cash = ($payment_method && (stripos($payment_method, 'cash') !== false || strtolower($payment_method) === 'cash'));
                
                // Determine next status in the flow
                $current_status = $order_data['order_status'] ?? 'pending';
                
                if ($is_cash) {
                    // Cash flow: Pending > Customer Cash Payment > Processing > Ready > Completed
                    switch ($current_status) {
                        case 'pending':
                            $new_status = 'customer_cash_payment';
                            break;
                        case 'customer_cash_payment':
                            $new_status = 'processing';
                            break;
                        case 'processing':
                            $new_status = 'ready';
                            break;
                        case 'ready':
                            $new_status = 'completed';
                            break;
                        default:
                            $new_status = $current_status;
                    }
                } else {
                    // Non-cash flow: Pending > Processing > Ready > Completed
                    switch ($current_status) {
                        case 'pending':
                            $new_status = 'processing';
                            break;
                        case 'processing':
                            $new_status = 'ready';
                            break;
                        case 'ready':
                            $new_status = 'completed';
                            break;
                        default:
                            $new_status = $current_status;
                    }
                }
            }
            
            // Validate the new status
            if (!in_array($new_status, ['pending', 'customer_cash_payment', 'processing', 'ready', 'completed', 'cancelled'])) {
                throw new Exception("Invalid status: " . $new_status);
            }
            
            // If cancelling an order, restore stock
            if ($new_status == 'cancelled' && $old_status != 'cancelled') {
                // Get all order items
                $stmt = $conn->prepare("SELECT item_id, quantity FROM order_items WHERE order_id = ?");
                $stmt->execute([$order_id]);
                $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Restore stock for each item
                foreach ($order_items as $item) {
                    $stmt = $conn->prepare("UPDATE menu_items SET stock = stock + ? WHERE item_id = ? AND cafe_id = ?");
                    $stmt->execute([$item['quantity'], $item['item_id'], $cafe_id]);
                    
                    // Update status back to available if stock was restored
                    $stmt = $conn->prepare("UPDATE menu_items SET status = 'available' WHERE item_id = ? AND cafe_id = ? AND stock > 0");
                    $stmt->execute([$item['item_id'], $cafe_id]);
                }
                
                // If payment was made, set payment_status to 'unpaid' (refund handling)
                if ($order_data['payment_status'] == 'paid') {
                    $stmt = $conn->prepare("UPDATE orders SET payment_status = 'unpaid' WHERE order_id = ?");
                    $stmt->execute([$order_id]);
                    
                    // Delete payment record to prevent revenue counting
                    try {
                        $stmt = $conn->prepare("DELETE FROM payments WHERE order_id = ?");
                        $stmt->execute([$order_id]);
                    } catch (Exception $e) {
                        error_log("Could not delete payment record: " . $e->getMessage());
                    }
                }
            }
            
            // Determine next status based on current status and payment method
            // Get payment method to determine flow
            $columns = $conn->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
            $has_payment_method = in_array('payment_method', $columns);
            
            $payment_method = null;
            if ($has_payment_method) {
                $stmt = $conn->prepare("SELECT payment_method FROM orders WHERE order_id = ?");
                $stmt->execute([$order_id]);
                $pm_data = $stmt->fetch(PDO::FETCH_ASSOC);
                $payment_method = $pm_data['payment_method'] ?? null;
            }
            
            // Check if it's a cash payment
            $is_cash = ($payment_method && (stripos($payment_method, 'cash') !== false || strtolower($payment_method) === 'cash'));
            
            // Determine next status in the flow
            $current_status = $order_data['order_status'] ?? 'pending';
            $next_status = $new_status; // Default to form selection
            
            // If Update button clicked (not Cancel), determine next status automatically
            if ($new_status != 'cancelled') {
                if ($is_cash) {
                    // Cash flow: Pending > Customer Cash Payment > Processing > Ready > Completed
                    switch ($current_status) {
                        case 'pending':
                            $next_status = 'customer_cash_payment';
                            break;
                        case 'customer_cash_payment':
                            $next_status = 'processing';
                            break;
                        case 'processing':
                            $next_status = 'ready';
                            break;
                        case 'ready':
                            $next_status = 'completed';
                            break;
                        default:
                            $next_status = $current_status;
                    }
                } else {
                    // Non-cash flow: Pending > Processing > Ready > Completed
                    switch ($current_status) {
                        case 'pending':
                            $next_status = 'processing';
                            break;
                        case 'processing':
                            $next_status = 'ready';
                            break;
                        case 'ready':
                            $next_status = 'completed';
                            break;
                        default:
                            $next_status = $current_status;
                    }
                }
                $new_status = $next_status;
            }
            
            // Handle cash payment processing when status moves to customer_cash_payment
            if ($new_status == 'customer_cash_payment' && $order_data['payment_status'] == 'unpaid' && $is_cash) {
                // This status requires cash payment processing - handled via modal
                // But if Update was clicked, check if payment info is provided
                if (isset($_POST['cash_amount_given']) && $_POST['cash_amount_given'] > 0) {
                    $cash_amount_given = (float)$_POST['cash_amount_given'];
                    
                    if ($cash_amount_given < $order_data['total_amount']) {
                        throw new Exception("Cash amount received is less than order total. Please enter correct amount.");
                    }
                    
                    // Update payment status to paid
                    $stmt = $conn->prepare("UPDATE orders SET payment_status = 'paid' WHERE order_id = ?");
                    $stmt->execute([$order_id]);
                    
                    // Create payment record
                    try {
                        $payment_columns = $conn->query("SHOW COLUMNS FROM payments")->fetchAll(PDO::FETCH_COLUMN);
                        $has_payment_method_col = in_array('payment_method', $payment_columns);
                        $has_amount_given = in_array('amount_given', $payment_columns);
                        $has_change = in_array('change_amount', $payment_columns);
                        
                        $change_amount = $cash_amount_given - $order_data['total_amount'];
                        
                        if ($has_payment_method_col && $has_amount_given && $has_change) {
                            $stmt = $conn->prepare("INSERT INTO payments (order_id, payment_method, amount, amount_given, change_amount) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE payment_method=?, amount=?, amount_given=?, change_amount=?");
                            $stmt->execute([$order_id, $payment_method, $order_data['total_amount'], $cash_amount_given, $change_amount, $payment_method, $order_data['total_amount'], $cash_amount_given, $change_amount]);
                        } elseif ($has_payment_method_col) {
                            $stmt = $conn->prepare("INSERT INTO payments (order_id, payment_method, amount) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE payment_method=?, amount=?");
                            $stmt->execute([$order_id, $payment_method, $order_data['total_amount'], $payment_method, $order_data['total_amount']]);
                        }
                    } catch (Exception $e) {
                        error_log("Could not create payment record: " . $e->getMessage());
                    }
                }
            }
            
            // Auto-process payment for non-cash orders when moving from pending to processing
            // For cash orders, payment is processed via modal when moving to customer_cash_payment status
            if ($new_status == 'processing' && $order_data['payment_status'] == 'unpaid' && !$is_cash) {
                // Auto-process payment for non-cash orders
                $stmt = $conn->prepare("UPDATE orders SET payment_status = 'paid' WHERE order_id = ?");
                $stmt->execute([$order_id]);
                
                // Create payment record
                try {
                    $payment_columns = $conn->query("SHOW COLUMNS FROM payments")->fetchAll(PDO::FETCH_COLUMN);
                    $has_payment_method_col = in_array('payment_method', $payment_columns);
                    
                    if ($has_payment_method_col) {
                        $stmt = $conn->prepare("INSERT INTO payments (order_id, payment_method, amount) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE payment_method=?, amount=?");
                        $stmt->execute([$order_id, $payment_method ?? 'qris', $order_data['total_amount'], $payment_method ?? 'qris', $order_data['total_amount']]);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO payments (order_id, amount) VALUES (?, ?) ON DUPLICATE KEY UPDATE amount=?");
                        $stmt->execute([$order_id, $order_data['total_amount'], $order_data['total_amount']]);
                    }
                } catch (Exception $e) {
                    error_log("Could not create payment record: " . $e->getMessage());
                }
            }
            
            
            // Update order status with the selected status from form
            // Always update status when Update button is clicked
            // Get current status first to check if it needs updating
            $stmt = $conn->prepare("SELECT order_status FROM orders WHERE order_id = ? AND cafe_id = ?");
            $stmt->execute([$order_id, $cafe_id]);
            $current_status_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $current_status_now = $current_status_data['order_status'] ?? '';
            
            // Always update to the selected status from the form if it's different
            // This ensures the user's selection is respected
            if ($current_status_now != $new_status) {
                $stmt = $conn->prepare("
                    UPDATE orders 
                    SET order_status = ?, prepared_by = ?, prepared_at = NOW()
                    WHERE order_id = ? AND cafe_id = ?
                ");
                $result = $stmt->execute([$new_status, $_SESSION['user_id'], $order_id, $cafe_id]);
                
                if (!$result) {
                    error_log("Failed to update order status: " . print_r($stmt->errorInfo(), true));
                }
            }
            
            // Create notification for customer
            $stmt = $conn->prepare("SELECT customer_id FROM orders WHERE order_id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($order && $order['customer_id']) {
                try {
                    $messages = [
                        'customer_cash_payment' => "Your order #{$order_id} cash payment is being processed",
                        'processing' => "Your order #{$order_id} is being prepared",
                        'ready' => "Your order #{$order_id} is ready for pickup!",
                        'completed' => "Your order #{$order_id} has been completed",
                        'cancelled' => "Your order #{$order_id} has been cancelled"
                    ];
                    
                    $stmt = $conn->prepare("
                        INSERT INTO order_notifications (order_id, customer_id, notification_type, message) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $notification_type = $new_status == 'customer_cash_payment' ? 'order_processing' :
                                       ($new_status == 'processing' ? 'order_processing' : 
                                       ($new_status == 'ready' ? 'order_ready' : 
                                       ($new_status == 'completed' ? 'order_completed' : 'order_cancelled')));
                    $stmt->execute([$order_id, $order['customer_id'], $notification_type, $messages[$new_status] ?? '']);
                } catch (Exception $e) {
                    error_log("Could not create notification: " . $e->getMessage());
                }
            }
            
            $conn->commit();
            $_SESSION['success'] = "Order status updated successfully";
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $_SESSION['error'] = 'Failed to update order: ' . $e->getMessage();
        }
    }
    
    header('Location: cashier_orders.php');
    exit();
}

// Get filter
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';

// Get orders
// Note: customer_id in orders references customers table
// For customer_online orders, we need to check order_source column
$columns = $conn->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
$has_order_source = in_array('order_source', $columns);

$sql = "
    SELECT o.*, c.name as customer_name, c.phone as customer_phone
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.customer_id
    WHERE o.cafe_id = ? " . ($has_order_source ? "AND (o.order_source = 'customer_online' OR o.order_source IS NULL)" : "") . "
";
$params = [$cafe_id];

if ($status_filter != 'all') {
    $sql .= " AND o.order_status = ?";
    $params[] = $status_filter;
} else {
    // Only show customer orders, not POS orders
    if ($has_order_source) {
        // Already filtered above
    } else {
        // For older schema, we can't distinguish, so show all orders
    }
}

// Auto-sort by status priority: Pending > Processing > Ready > Completed (or Customer Cash Payment for cash)
// Status priority order: pending=1, customer_cash_payment=2, processing=3, ready=4, completed=5, cancelled=6
$sql .= " ORDER BY 
    CASE o.order_status 
        WHEN 'pending' THEN 1
        WHEN 'customer_cash_payment' THEN 2
        WHEN 'processing' THEN 3
        WHEN 'ready' THEN 4
        WHEN 'completed' THEN 5
        WHEN 'cancelled' THEN 6
        ELSE 7
    END ASC,
    o.created_at ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get order items for each order
foreach ($orders as &$order) {
    $stmt = $conn->prepare("
        SELECT oi.*, mi.item_name
        FROM order_items oi
        JOIN menu_items mi ON oi.item_id = mi.item_id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order['order_id']]);
    $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get variations and addons for each item
    foreach ($order['items'] as &$item) {
        try {
            // Get variations
            $stmt = $conn->prepare("
                SELECT oiv.*, v.variation_name
                FROM order_item_variations oiv
                JOIN product_variations v ON oiv.variation_id = v.variation_id
                WHERE oiv.order_item_id = ?
            ");
            $stmt->execute([$item['order_item_id']]);
            $item['variations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get addons
            $stmt = $conn->prepare("SELECT * FROM order_item_addons WHERE order_item_id = ?");
            $stmt->execute([$item['order_item_id']]);
            $item['addons'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Tables might not exist
            $item['variations'] = [];
            $item['addons'] = [];
        }
    }
}

$page_title = 'Customer Orders';
include 'includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">Customer Orders</h2>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>

<!-- Filter Tabs -->
<div class="subnav-container">
    <div class="subnav-tabs">
        <a href="?status=all" class="subnav-tab <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
            <i class="fas fa-list"></i>
            <span>All Orders</span>
        </a>
        <a href="?status=pending" class="subnav-tab <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
            <i class="fas fa-clock"></i>
            <span>Pending</span>
        </a>
        <a href="?status=processing" class="subnav-tab <?php echo $status_filter == 'processing' ? 'active' : ''; ?>">
            <i class="fas fa-spinner"></i>
            <span>Processing</span>
        </a>
        <a href="?status=ready" class="subnav-tab <?php echo $status_filter == 'ready' ? 'active' : ''; ?>">
            <i class="fas fa-check-circle"></i>
            <span>Ready</span>
        </a>
        <a href="?status=completed" class="subnav-tab <?php echo $status_filter == 'completed' ? 'active' : ''; ?>">
            <i class="fas fa-check-double"></i>
            <span>Completed</span>
        </a>
    </div>
</div>

<?php if (empty($orders)): ?>
    <div style="padding: 40px; text-align: center; color: var(--text-gray);">
        <p>No customer orders found.</p>
    </div>
<?php else: ?>
    <div style="display: grid; gap: 20px;">
        <?php foreach ($orders as $order): ?>
            <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                    <div>
                        <h3 style="color: var(--primary-white); margin: 0 0 5px 0;">Order #<?php echo $order['order_id']; ?></h3>
                        <p style="color: var(--text-gray); margin: 5px 0; font-size: 14px;">
                            <?php echo htmlspecialchars($order['customer_name'] ?? 'Guest'); ?> • 
                            <?php echo date('d M Y, H:i', strtotime($order['created_at'])); ?>
                        </p>
                        <p style="color: var(--text-gray); margin: 5px 0; font-size: 14px;">
                            Type: <?php echo htmlspecialchars($order['order_type']); ?> • 
                            Payment: <span style="color: <?php echo $order['payment_status'] == 'paid' ? '#28a745' : '#ffc107'; ?>;">
                                <?php echo strtoupper($order['payment_status']); ?>
                            </span>
                        </p>
                    </div>
                    <div style="text-align: right;">
                        <?php
                        $status_colors = [
                            'pending' => '#ffc107',
                            'customer_cash_payment' => '#ff9800',
                            'processing' => '#17a2b8',
                            'ready' => '#28a745',
                            'completed' => '#6c757d',
                            'cancelled' => '#dc3545'
                        ];
                        $status_labels = [
                            'pending' => 'Pending',
                            'customer_cash_payment' => 'Customer Cash Payment',
                            'processing' => 'Processing',
                            'ready' => 'Ready',
                            'completed' => 'Completed',
                            'cancelled' => 'Cancelled'
                        ];
                        $status_color = $status_colors[$order['order_status']] ?? '#6c757d';
                        $status_label = $status_labels[$order['order_status']] ?? ucfirst($order['order_status']);
                        ?>
                        <span style="background: <?php echo $status_color; ?>; color: white; padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; display: inline-block; margin-bottom: 10px;">
                            <?php echo htmlspecialchars($status_label); ?>
                        </span>
                        <p style="color: #28a745; font-size: 18px; font-weight: bold; margin: 0;">
                            <?php echo formatCurrency($order['total_amount']); ?>
                        </p>
                    </div>
                </div>
                
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border-gray);">
                    <h4 style="color: var(--primary-white); margin: 0 0 10px 0; font-size: 14px;">Items:</h4>
                    <?php foreach ($order['items'] as $item): ?>
                        <div style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid var(--border-gray);">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <span style="color: var(--text-gray);">
                                    <?php echo htmlspecialchars($item['item_name']); ?> × <?php echo $item['quantity']; ?>
                                </span>
                                <span style="color: var(--primary-white);">
                                    <?php echo formatCurrency($item['subtotal']); ?>
                                </span>
                            </div>
                            <?php if (!empty($item['variations'])): ?>
                                <div style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">
                                    <?php
                                    $var_texts = [];
                                    foreach ($item['variations'] as $var) {
                                        $var_texts[] = $var['variation_name'] . ': ' . $var['option_name'];
                                    }
                                    echo implode(', ', $var_texts);
                                    ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($item['addons'])): ?>
                                <div style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">
                                    Add-ons: <?php
                                    $addon_texts = [];
                                    foreach ($item['addons'] as $addon) {
                                        $addon_texts[] = $addon['addon_name'] . ' (+' . formatCurrency($addon['addon_price']) . ')';
                                    }
                                    echo implode(', ', $addon_texts);
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($order['customer_notes']): ?>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border-gray);">
                        <p style="color: var(--text-gray); margin: 0; font-size: 14px;">
                            <strong>Customer Notes:</strong> <?php echo htmlspecialchars($order['customer_notes']); ?>
                        </p>
                    </div>
                <?php endif; ?>
                
                <!-- Status Update Form -->
                <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--border-gray);">
                    <?php 
                    // Check if order payment method is cash
                    $is_cash_payment = false;
                    $payment_method_value = null;
                    try {
                        $columns = $conn->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
                        $has_payment_method_in_orders = in_array('payment_method', $columns);
                        
                        if ($has_payment_method_in_orders) {
                            $stmt = $conn->prepare("SELECT payment_method FROM orders WHERE order_id = ?");
                            $stmt->execute([$order['order_id']]);
                            $order_payment = $stmt->fetch(PDO::FETCH_ASSOC);
                            $payment_method_value = $order_payment['payment_method'] ?? null;
                        }
                        
                        if (!$payment_method_value) {
                            try {
                                $payment_columns = $conn->query("SHOW COLUMNS FROM payments")->fetchAll(PDO::FETCH_COLUMN);
                                if (in_array('payment_method', $payment_columns)) {
                                    $stmt = $conn->prepare("SELECT payment_method FROM payments WHERE order_id = ? LIMIT 1");
                                    $stmt->execute([$order['order_id']]);
                                    $payment_data = $stmt->fetch(PDO::FETCH_ASSOC);
                                    $payment_method_value = $payment_data['payment_method'] ?? null;
                                }
                            } catch (Exception $e) {
                                error_log("Error checking payment method: " . $e->getMessage());
                            }
                        }
                        
                        $is_cash_payment = ($payment_method_value && (stripos($payment_method_value, 'cash') !== false || strtolower($payment_method_value) === 'cash'));
                    } catch (Exception $e) {
                        error_log("Error checking payment method: " . $e->getMessage());
                    }
                    
                    // Determine next status and button text
                    $current_status = $order['order_status'] ?? 'pending';
                    $next_status_label = 'Update';
                    $can_update = true;
                    
                    if ($is_cash_payment) {
                        switch ($current_status) {
                            case 'pending':
                                $next_status_label = 'Move to Customer Cash Payment';
                                break;
                            case 'customer_cash_payment':
                                $next_status_label = 'Move to Processing';
                                break;
                            case 'processing':
                                $next_status_label = 'Move to Ready';
                                break;
                            case 'ready':
                                $next_status_label = 'Move to Completed';
                                break;
                            case 'completed':
                                $can_update = false;
                                break;
                            case 'cancelled':
                                $can_update = false;
                                break;
                        }
                    } else {
                        switch ($current_status) {
                            case 'pending':
                                $next_status_label = 'Move to Processing';
                                break;
                            case 'processing':
                                $next_status_label = 'Move to Ready';
                                break;
                            case 'ready':
                                $next_status_label = 'Move to Completed';
                                break;
                            case 'completed':
                                $can_update = false;
                                break;
                            case 'cancelled':
                                $can_update = false;
                                break;
                        }
                    }
                    ?>
                    
                    <?php if ($can_update && $order['order_status'] != 'cancelled'): ?>
                        <form method="POST" id="orderForm_<?php echo $order['order_id']; ?>" style="display: flex; gap: 10px; align-items: center;">
                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                            <input type="hidden" name="status" value="">
                            <input type="hidden" name="cash_amount_given" id="cash_amount_given_<?php echo $order['order_id']; ?>" value="">
                            
                            <?php if ($order['payment_status'] == 'unpaid' && $is_cash_payment && $current_status == 'pending'): ?>
                                <!-- Show cash payment modal for pending cash orders -->
                                <button type="button" onclick="showCashPaymentModal(<?php echo $order['order_id']; ?>, <?php echo $order['total_amount']; ?>)" class="btn btn-success" style="margin-right: 10px;">
                                    Process Cash Payment & Continue
                                </button>
                            <?php elseif ($order['payment_status'] == 'unpaid' && !$is_cash_payment && $current_status == 'pending'): ?>
                                <!-- Auto-process non-cash payment and continue -->
                                <input type="hidden" name="process_payment" value="1">
                                <button type="submit" name="update_status" value="1" class="btn btn-primary"><?php echo htmlspecialchars($next_status_label); ?></button>
                            <?php else: ?>
                                <!-- Normal progression -->
                                <button type="submit" name="update_status" value="1" class="btn btn-primary"><?php echo htmlspecialchars($next_status_label); ?></button>
                            <?php endif; ?>
                            
                            <button type="submit" name="cancel_order" value="1" class="btn btn-danger">Cancel Order</button>
                        </form>
                    <?php elseif ($order['order_status'] == 'completed'): ?>
                        <p style="color: var(--text-gray); font-style: italic;">Order completed</p>
                    <?php elseif ($order['order_status'] == 'cancelled'): ?>
                        <p style="color: var(--text-gray); font-style: italic;">Order cancelled</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Cash Payment Modal -->
<div id="cashPaymentModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000; overflow-y: auto;">
    <div style="max-width: 500px; margin: 50px auto; background: var(--primary-black); border: 1px solid var(--border-gray); border-radius: 10px; padding: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="color: var(--primary-white); margin: 0; font-size: 24px;">Cash Payment</h3>
            <button onclick="closeCashPaymentModal()" style="background: none; border: none; color: var(--primary-white); font-size: 28px; cursor: pointer; padding: 0; width: 30px; height: 30px;">&times;</button>
        </div>
        
        <div style="margin-bottom: 20px;">
            <p style="color: var(--text-gray); margin-bottom: 10px;">Order Total:</p>
            <p id="modalOrderTotal" style="color: #28a745; font-size: 28px; font-weight: bold; margin: 0;"></p>
        </div>
        
        <div class="form-group">
            <label for="cashAmountReceived">Amount Received *</label>
            <input type="number" id="cashAmountReceived" step="0.01" min="0" style="width: 100%;" oninput="calculateCashChange()" autofocus>
        </div>
        
        <div style="margin: 20px 0; padding: 15px; background: var(--accent-gray); border-radius: 8px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                <span style="color: var(--text-gray);">Change:</span>
                <span id="cashChangeAmount" style="color: var(--primary-white); font-size: 20px; font-weight: bold;">Rp 0</span>
            </div>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button onclick="processCashPayment()" class="btn btn-primary" style="flex: 1;">Confirm Payment</button>
            <button onclick="closeCashPaymentModal()" class="btn btn-secondary">Cancel</button>
        </div>
        
        <input type="hidden" id="modalOrderId" value="">
        <input type="hidden" id="modalOrderTotalValue" value="">
    </div>
</div>

<script>
let currentCashPaymentOrderId = null;

function showCashPaymentModal(orderId, orderTotal) {
    currentCashPaymentOrderId = orderId;
    document.getElementById('modalOrderId').value = orderId;
    document.getElementById('modalOrderTotalValue').value = orderTotal;
    document.getElementById('modalOrderTotal').textContent = formatCurrency(orderTotal);
    document.getElementById('cashAmountReceived').value = '';
    document.getElementById('cashChangeAmount').textContent = 'Rp 0';
    document.getElementById('cashPaymentModal').style.display = 'block';
    document.getElementById('cashAmountReceived').focus();
}

function closeCashPaymentModal() {
    document.getElementById('cashPaymentModal').style.display = 'none';
    currentCashPaymentOrderId = null;
    document.getElementById('cashAmountReceived').value = '';
}

function calculateCashChange() {
    const amountReceived = parseFloat(document.getElementById('cashAmountReceived').value) || 0;
    const orderTotal = parseFloat(document.getElementById('modalOrderTotalValue').value) || 0;
    const change = Math.max(0, amountReceived - orderTotal);
    document.getElementById('cashChangeAmount').textContent = formatCurrency(change);
    
    // Highlight if insufficient amount
    const changeElement = document.getElementById('cashChangeAmount');
    if (amountReceived > 0 && amountReceived < orderTotal) {
        changeElement.style.color = '#dc3545';
    } else {
        changeElement.style.color = 'var(--primary-white)';
    }
}

function processCashPayment() {
    const amountReceived = parseFloat(document.getElementById('cashAmountReceived').value) || 0;
    const orderTotal = parseFloat(document.getElementById('modalOrderTotalValue').value) || 0;
    const orderId = currentCashPaymentOrderId;
    
    if (!amountReceived || amountReceived <= 0) {
        alert('Please enter the amount received');
        return;
    }
    
    if (amountReceived < orderTotal) {
        alert('Amount received is less than order total. Please enter correct amount.');
        return;
    }
    
    // Find the form for this order
    const targetForm = document.getElementById('orderForm_' + orderId);
    if (!targetForm) {
        alert('Error: Could not find order form');
        closeCashPaymentModal();
        return;
    }
    
    // Set cash amount in hidden input
    const cashAmountInput = document.getElementById('cash_amount_given_' + orderId);
    if (!cashAmountInput) {
        alert('Error: Could not find cash amount input field');
        closeCashPaymentModal();
        return;
    }
    
    cashAmountInput.value = amountReceived;
    
    // Create/update hidden input for update_status
    let updateStatusInput = targetForm.querySelector('input[name="update_status"]');
    if (!updateStatusInput) {
        updateStatusInput = document.createElement('input');
        updateStatusInput.type = 'hidden';
        updateStatusInput.name = 'update_status';
        updateStatusInput.value = '1';
        targetForm.appendChild(updateStatusInput);
    } else {
        updateStatusInput.value = '1';
    }
    
    // Close modal
    closeCashPaymentModal();
    
    // Submit form
    setTimeout(function() {
        targetForm.submit();
    }, 100);
}

function formatCurrency(amount) {
    return 'Rp ' + amount.toLocaleString('id-ID');
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCashPaymentModal();
    }
});
</script>

<?php include 'includes/footer.php'; ?>

