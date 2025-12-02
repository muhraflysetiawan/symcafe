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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cart_data = json_decode($_POST['cart_data'] ?? '[]', true);
    $cafe_id = (int)($_POST['cafe_id'] ?? 0);
    $order_type = sanitizeInput($_POST['order_type'] ?? 'take-away');
    $payment_method = sanitizeInput($_POST['payment_method'] ?? 'cash');
    $customer_notes = sanitizeInput($_POST['customer_notes'] ?? '');
    $subtotal = (float)($_POST['subtotal'] ?? 0);
    $tax = (float)($_POST['tax'] ?? 0);
    $total = (float)($_POST['total'] ?? 0);
    
    if (empty($cart_data) || $cafe_id <= 0 || $total <= 0) {
        $_SESSION['error'] = 'Invalid order data';
        header('Location: customer_checkout.php');
        exit();
    }
    
    try {
        $conn->beginTransaction();
        
        // Verify cafe exists
        $stmt = $conn->prepare("SELECT cafe_id FROM cafes WHERE cafe_id = ?");
        $stmt->execute([$cafe_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Store not found");
        }
        
        // Get customer info from users table
        $stmt = $conn->prepare("SELECT name, email FROM users WHERE user_id = ? AND role = 'customer'");
        $stmt->execute([$customer_id]);
        $customer_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$customer_user) {
            throw new Exception("Customer account not found");
        }
        
        // Create or get customer record in customers table (for foreign key constraint)
        // The orders table customer_id references customers table, not users table
        $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE cafe_id = ? AND name = ? LIMIT 1");
        $stmt->execute([$cafe_id, $customer_user['name']]);
        $existing_customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $customer_record_id = null;
        if ($existing_customer) {
            $customer_record_id = $existing_customer['customer_id'];
        } else {
            // Create customer record
            $stmt = $conn->prepare("INSERT INTO customers (cafe_id, name) VALUES (?, ?)");
            $stmt->execute([$cafe_id, $customer_user['name']]);
            $customer_record_id = $conn->lastInsertId();
        }
        
        // Determine payment status based on payment method
        // Cash = unpaid (needs confirmation), all other methods = paid immediately
        $payment_status = (strtolower($payment_method) === 'cash' || stripos($payment_method, 'cash') !== false) ? 'unpaid' : 'paid';
        
        // Check and add payment_method column to orders table if it doesn't exist
        try {
            $columns = $conn->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('payment_method', $columns)) {
                // Add payment_method column to orders table
                $conn->exec("ALTER TABLE orders ADD COLUMN payment_method VARCHAR(50) NULL AFTER payment_status");
                // Reload columns after adding
                $columns = $conn->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
            }
        } catch (Exception $e) {
            error_log("Error checking/adding payment_method column to orders: " . $e->getMessage());
        }
        
        // Create order
        $columns = $conn->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
        $has_order_source = in_array('order_source', $columns);
        $has_order_status = in_array('order_status', $columns);
        $has_customer_notes = in_array('customer_notes', $columns);
        $has_payment_method = in_array('payment_method', $columns);
        
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
            // Fallback for older schema
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
            
            // Save variations if any
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
                    // Table might not exist yet
                    error_log("Error saving variations: " . $e->getMessage());
                }
            }
            
            // Save add-ons if any
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
                    // Table might not exist yet
                    error_log("Error saving addons: " . $e->getMessage());
                }
            }
            
            // Update product stock (reduce stock after order is placed)
            $stmt = $conn->prepare("UPDATE menu_items SET stock = stock - ? WHERE item_id = ? AND cafe_id = ?");
            $stmt->execute([$quantity, $item_id, $cafe_id]);
            
            // Auto-update status if stock reaches 0
            $stmt = $conn->prepare("UPDATE menu_items SET status = 'unavailable' WHERE item_id = ? AND cafe_id = ? AND stock <= 0");
            $stmt->execute([$item_id, $cafe_id]);
        }
        
        // Create payment record if payment is already paid (non-cash payments)
        if ($payment_status === 'paid') {
            try {
                // Check if payments table exists and has payment_method column
                $payment_columns = $conn->query("SHOW COLUMNS FROM payments")->fetchAll(PDO::FETCH_COLUMN);
                $has_payment_method = in_array('payment_method', $payment_columns);
                
                if ($has_payment_method) {
                    $stmt = $conn->prepare("INSERT INTO payments (order_id, payment_method, amount) VALUES (?, ?, ?)");
                    $stmt->execute([$order_id, $payment_method, $total]);
                } else {
                    // Fallback for older schema
                    $stmt = $conn->prepare("INSERT INTO payments (order_id, amount) VALUES (?, ?)");
                    $stmt->execute([$order_id, $total]);
                }
            } catch (Exception $e) {
                // Payment table might not exist or error - log but don't fail order
                error_log("Could not create payment record: " . $e->getMessage());
            }
        }
        
        // Create notification for cashier
        try {
            $stmt = $conn->prepare("
                INSERT INTO order_notifications (order_id, customer_id, notification_type, message) 
                VALUES (?, ?, 'order_placed', ?)
            ");
            $message = "New order #{$order_id} placed by customer";
            $stmt->execute([$order_id, $customer_id, $message]);
        } catch (Exception $e) {
            // Notification table might not exist yet
            error_log("Could not create notification: " . $e->getMessage());
        }
        
        $conn->commit();
        
        // Clear cart from session and localStorage (will be cleared via redirect)
        $_SESSION['customer_cart'] = [];
        unset($_SESSION['selected_cafe_id']);
        
        // Store success message
        $success_msg = $payment_status === 'paid' 
            ? "Order placed and paid successfully! Order #{$order_id}" 
            : "Order placed successfully! Order #{$order_id}. Please pay at the store.";
        $_SESSION['success'] = $success_msg;
        
        // Redirect with cart cleared flag
        header('Location: customer_orders.php?cart_cleared=1');
        exit();
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['error'] = 'Order failed: ' . $e->getMessage();
        error_log("Customer order error: " . $e->getMessage());
        header('Location: customer_checkout.php');
        exit();
    }
} else {
    header('Location: customer_menu.php');
    exit();
}
?>

