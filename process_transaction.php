<?php
require_once 'config/config.php';
requireLogin();
requireCafeSetup();

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cart_data = json_decode($_POST['cart_data'] ?? '[]', true);
    $subtotal = (float)($_POST['subtotal_value'] ?? 0);
    $discount = (float)($_POST['discount_value'] ?? 0);
    $tax = (float)($_POST['tax_value'] ?? 0);
    $total = (float)($_POST['total_value'] ?? 0);
    $order_type = sanitizeInput($_POST['order_type'] ?? 'dine-in');
    $payment_method = sanitizeInput($_POST['payment_method'] ?? 'cash');
    $customer_name = sanitizeInput($_POST['customer_name'] ?? '');
    $cash_amount_given = isset($_POST['cash_amount_given']) ? (float)$_POST['cash_amount_given'] : 0;
    $cash_change_amount = isset($_POST['cash_change_amount']) ? (float)$_POST['cash_change_amount'] : 0;
    $voucher_id = !empty($_POST['voucher_id']) ? (int)$_POST['voucher_id'] : null;
    $voucher_code = !empty($_POST['voucher_code']) ? strtoupper(trim(sanitizeInput($_POST['voucher_code']))) : '';
    $voucher_code_id = !empty($_POST['voucher_code_id']) ? (int)$_POST['voucher_code_id'] : null;
    
    if (empty($cart_data) || $total <= 0) {
        $_SESSION['error'] = 'Invalid transaction data';
        header('Location: pos.php');
        exit();
    }
    
    // Validate voucher if provided
    if ($voucher_id && $voucher_code) {
        try {
            // If voucher_code_id is provided, validate unique code
            if ($voucher_code_id) {
                $stmt = $conn->prepare("
                    SELECT vc.*, v.* 
                    FROM voucher_codes vc
                    INNER JOIN vouchers v ON vc.voucher_id = v.voucher_id
                    WHERE vc.code_id = ? AND vc.unique_code = ? AND v.cafe_id = ? 
                    AND v.is_active = 1
                    AND (v.valid_from IS NULL OR v.valid_from <= CURDATE())
                    AND (v.valid_until IS NULL OR v.valid_until >= CURDATE())
                ");
                $stmt->execute([$voucher_code_id, $voucher_code, $cafe_id]);
                $code_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$code_data) {
                    $_SESSION['error'] = 'Invalid or expired voucher code';
                    header('Location: pos.php');
                    exit();
                }
                
                // Check usage limit instead of is_used flag
                if ($code_data['usage_limit'] && $code_data['used_count'] >= $code_data['usage_limit']) {
                    $_SESSION['error'] = 'Voucher usage limit reached';
                    header('Location: pos.php');
                    exit();
                }
                
                $voucher = $code_data;
                
                // Validate minimum order amount
                if ($voucher['min_order_amount'] > 0 && $subtotal < $voucher['min_order_amount']) {
                    $_SESSION['error'] = 'Voucher requires minimum order of ' . formatCurrency($voucher['min_order_amount']);
                    header('Location: pos.php');
                    exit();
                }
                
                // Validate maximum order amount
                if ($voucher['max_order_amount'] && $subtotal > $voucher['max_order_amount']) {
                    $_SESSION['error'] = 'Voucher maximum order is ' . formatCurrency($voucher['max_order_amount']);
                    header('Location: pos.php');
                    exit();
                }
                
                // Validate applicable products
                if (!empty($voucher['applicable_products'])) {
                    $applicable_products = json_decode($voucher['applicable_products'], true);
                    if (is_array($applicable_products)) {
                        $cart_product_ids = array_column($cart_data, 'id');
                        $has_applicable = !empty(array_intersect($cart_product_ids, $applicable_products));
                        if (!$has_applicable) {
                            $_SESSION['error'] = 'Voucher is not applicable to selected products';
                            header('Location: pos.php');
                            exit();
                        }
                    }
                }
                
                // Ensure discount matches voucher
                if (abs($discount - $voucher['discount_amount']) > 0.01) {
                    $_SESSION['error'] = 'Voucher discount mismatch';
                    header('Location: pos.php');
                    exit();
                }
            } else {
                // Legacy voucher validation (for backward compatibility)
                $stmt = $conn->prepare("
                    SELECT * FROM vouchers 
                    WHERE voucher_id = ? AND cafe_id = ? AND is_active = 1
                    AND voucher_code = ?
                    AND (valid_from IS NULL OR valid_from <= CURDATE())
                    AND (valid_until IS NULL OR valid_until >= CURDATE())
                    AND (usage_limit IS NULL OR used_count < usage_limit)
                ");
                $stmt->execute([$voucher_id, $cafe_id, $voucher_code]);
                $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$voucher) {
                    $_SESSION['error'] = 'Invalid or expired voucher code';
                    header('Location: pos.php');
                    exit();
                }
                
                // Validate minimum order amount
                if ($voucher['min_order_amount'] > 0 && $subtotal < $voucher['min_order_amount']) {
                    $_SESSION['error'] = 'Voucher requires minimum order of ' . formatCurrency($voucher['min_order_amount']);
                    header('Location: pos.php');
                    exit();
                }
                
                // Validate maximum order amount
                if ($voucher['max_order_amount'] && $subtotal > $voucher['max_order_amount']) {
                    $_SESSION['error'] = 'Voucher maximum order is ' . formatCurrency($voucher['max_order_amount']);
                    header('Location: pos.php');
                    exit();
                }
                
                // Validate applicable products
                if (!empty($voucher['applicable_products'])) {
                    $applicable_products = json_decode($voucher['applicable_products'], true);
                    if (is_array($applicable_products)) {
                        $cart_product_ids = array_column($cart_data, 'id');
                        $has_applicable = !empty(array_intersect($cart_product_ids, $applicable_products));
                        if (!$has_applicable) {
                            $_SESSION['error'] = 'Voucher is not applicable to selected products';
                            header('Location: pos.php');
                            exit();
                        }
                    }
                }
                
                // Ensure discount matches voucher
                if (abs($discount - $voucher['discount_amount']) > 0.01) {
                    $_SESSION['error'] = 'Voucher discount mismatch';
                    header('Location: pos.php');
                    exit();
                }
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Voucher validation failed: ' . $e->getMessage();
            header('Location: pos.php');
            exit();
        }
    }
    
    try {
        $conn->beginTransaction();
        
        // Handle customer name
        $customer_id = null;
        if (!empty($customer_name)) {
            // Check if customer already exists
            $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE cafe_id = ? AND name = ? LIMIT 1");
            $stmt->execute([$cafe_id, $customer_name]);
            $existing_customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_customer) {
                $customer_id = $existing_customer['customer_id'];
                // Verify customer still exists (in case it was deleted)
                $verify_stmt = $conn->prepare("SELECT customer_id FROM customers WHERE customer_id = ? AND cafe_id = ?");
                $verify_stmt->execute([$customer_id, $cafe_id]);
                if (!$verify_stmt->fetch()) {
                    // Customer was deleted, create new one
                    $customer_id = null;
                }
            }
            
            if (!$customer_id) {
                // Create new customer
                try {
                    $stmt = $conn->prepare("INSERT INTO customers (cafe_id, name) VALUES (?, ?)");
                    $stmt->execute([$cafe_id, $customer_name]);
                    $customer_id = $conn->lastInsertId();
                    
                    if (!$customer_id || $customer_id <= 0) {
                        throw new Exception("Failed to create customer. Customer ID is invalid.");
                    }
                } catch (PDOException $e) {
                    throw new Exception("Failed to create customer: " . $e->getMessage());
                }
            }
        }
        
        // Create order (check if voucher_id column exists)
        $columns = $conn->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
        $has_voucher_column = in_array('voucher_id', $columns);
        
        // Validate foreign keys exist before inserting (within transaction)
        // Check cashier_id (user_id)
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $cashier_exists = $stmt->fetch();
        if (!$cashier_exists) {
            throw new Exception("Invalid cashier_id: User ID " . $_SESSION['user_id'] . " does not exist in users table. Please log out and log back in.");
        }
        
        // Check cafe_id
        $stmt = $conn->prepare("SELECT cafe_id FROM cafes WHERE cafe_id = ?");
        $stmt->execute([$cafe_id]);
        $cafe_exists = $stmt->fetch();
        if (!$cafe_exists) {
            throw new Exception("Invalid cafe_id: Cafe ID " . $cafe_id . " does not exist in cafes table. Please contact administrator.");
        }
        
        // Check customer_id if provided (should already be validated above, but double-check)
        if ($customer_id) {
            $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE customer_id = ? AND cafe_id = ?");
            $stmt->execute([$customer_id, $cafe_id]);
            $customer_exists = $stmt->fetch();
            if (!$customer_exists) {
                // Customer doesn't exist, set to null and continue without customer
                error_log("Warning: Customer ID {$customer_id} not found, proceeding without customer");
                $customer_id = null;
            }
        }
        
        // Insert order with proper error handling
        try {
            // Prepare the INSERT statement
            if ($has_voucher_column) {
                $sql = "INSERT INTO orders (cafe_id, cashier_id, customer_id, order_type, subtotal, discount, tax, total_amount, payment_status, voucher_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?)";
                $params = [$cafe_id, $_SESSION['user_id'], $customer_id, $order_type, $subtotal, $discount, $tax, $total, ($voucher_id > 0 ? $voucher_id : null)];
            } else {
                $sql = "INSERT INTO orders (cafe_id, cashier_id, customer_id, order_type, subtotal, discount, tax, total_amount, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'paid')";
                $params = [$cafe_id, $_SESSION['user_id'], $customer_id, $order_type, $subtotal, $discount, $tax, $total];
            }
            
            $stmt = $conn->prepare($sql);
            
            // Execute and check for errors immediately
            try {
                $result = $stmt->execute($params);
            } catch (PDOException $e) {
                // PDO will throw exception on constraint violation
                $error_code = $e->getCode();
                $error_msg = $e->getMessage();
                
                if ($error_code == 23000 || strpos($error_msg, 'foreign key constraint') !== false) {
                    throw new Exception("Foreign key constraint violation when creating order. Values: cafe_id={$cafe_id}, cashier_id={$_SESSION['user_id']}, customer_id=" . ($customer_id ?? 'NULL') . ". Original error: " . $error_msg);
                }
                throw $e;
            }
            
            // Check errorInfo even if execute() didn't throw
            $error_info = $stmt->errorInfo();
            if ($error_info[0] !== '00000' && $error_info[0] !== null && $error_info[0] !== '') {
                throw new Exception("SQL Error creating order: " . ($error_info[2] ?? 'Unknown error') . " (SQL State: " . $error_info[0] . ", Error Code: " . ($error_info[1] ?? 'N/A') . ")");
            }
            
            // Get the order_id
            $order_id = $conn->lastInsertId();
            
            // Validate that order was created successfully
            if (!$order_id || $order_id <= 0) {
                throw new Exception("Failed to create order. lastInsertId() returned invalid value: " . var_export($order_id, true) . ". SQL: " . $sql . ", Params: " . json_encode($params));
            }
            
            // Verify order actually exists in database (within transaction)
            $verify_stmt = $conn->prepare("SELECT order_id, cafe_id, cashier_id FROM orders WHERE order_id = ?");
            $verify_stmt->execute([$order_id]);
            $order_check = $verify_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order_check) {
                throw new Exception("Order was not found after creation. Order ID: " . $order_id . ". This means the INSERT failed silently. SQL: " . $sql);
            }
            
            // Double-check the order matches what we inserted
            if ($order_check['cafe_id'] != $cafe_id || $order_check['cashier_id'] != $_SESSION['user_id']) {
                throw new Exception("Order data mismatch. Expected cafe_id={$cafe_id}, cashier_id={$_SESSION['user_id']}, but got cafe_id={$order_check['cafe_id']}, cashier_id={$order_check['cashier_id']}");
            }
            
        } catch (PDOException $e) {
            // Re-throw PDO exceptions with more context
            throw new Exception("Database error creating order: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
        }
        
        // Log voucher usage if voucher was used
        if ($voucher_id > 0) {
            try {
                // Check if voucher_usage_log table exists
                $tables = $conn->query("SHOW TABLES LIKE 'voucher_usage_log'")->fetchAll();
                if (!empty($tables)) {
                    $stmt = $conn->prepare("
                        INSERT INTO voucher_usage_log (voucher_id, order_id, customer_id, discount_amount, order_total_before_discount, order_total_after_discount, hour_of_day, day_of_week)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $hour = (int)date('H');
                    $day = date('l');
                    $stmt->execute([$voucher_id, $order_id, $customer_id, $discount, $subtotal, $total, $hour, $day]);
                }
            } catch (Exception $e) {
                // Table might not exist yet
                error_log("Error logging voucher usage: " . $e->getMessage());
            }
        }
        
        // Add order items and update stock
        foreach ($cart_data as $item) {
            $item_id = (int)$item['id'];
            $quantity = (int)$item['quantity'];
            $price = (float)$item['price'];
            $subtotal_item = $price * $quantity;
            
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
            if (isset($item['addons']) && is_array($item['addons'])) {
                try {
                    $stmt = $conn->prepare("
                        INSERT INTO order_item_addons (order_item_id, addon_id, addon_name, addon_price) 
                        VALUES (?, ?, ?, ?)
                    ");
                    foreach ($item['addons'] as $addon) {
                        $addon_id = (int)$addon['addon_id'] ?? 0;
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
            
            // Update product stock
            $stmt = $conn->prepare("UPDATE menu_items SET stock = stock - ? WHERE item_id = ? AND cafe_id = ?");
            $stmt->execute([$quantity, $item_id, $cafe_id]);
            
            // Auto-update status if stock reaches 0
            $stmt = $conn->prepare("UPDATE menu_items SET status = 'unavailable' WHERE item_id = ? AND cafe_id = ? AND stock <= 0");
            $stmt->execute([$item_id, $cafe_id]);
            
            // Auto-deduct raw materials based on recipe (BOM)
            // This is optional - if it fails, we still allow the sale to proceed
            try {
                // Check if product_recipes table exists
                $tables = $conn->query("SHOW TABLES LIKE 'product_recipes'")->fetchAll();
                if (empty($tables)) {
                    // Recipe system not set up yet, skip
                    continue;
                }
                
                require_once 'config/functions_inventory.php';
                
                // Get recipe for this product
                $stmt = $conn->prepare("
                    SELECT pr.material_id, pr.sub_recipe_id, pr.quantity as recipe_quantity
                    FROM product_recipes pr
                    WHERE pr.item_id = ?
                ");
                $stmt->execute([$item_id]);
                $recipe_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Only process if recipe exists
                if (!empty($recipe_items)) {
                    foreach ($recipe_items as $recipe_item) {
                        $total_quantity_needed = $recipe_item['recipe_quantity'] * $quantity;
                        
                        if ($recipe_item['material_id']) {
                            // Direct material - deduct directly
                            $result = deductMaterialStock($conn, $order_item_id, $recipe_item['material_id'], $total_quantity_needed);
                            if (!$result['success']) {
                                // Log warning but don't fail transaction
                                error_log("Warning: Failed to deduct material {$recipe_item['material_id']} for product {$item_id}: " . ($result['error'] ?? 'Unknown error') . ". Sale will proceed without material deduction.");
                            }
                        } elseif ($recipe_item['sub_recipe_id']) {
                            // Sub-recipe - get ingredients and deduct each
                            try {
                                $stmt = $conn->prepare("
                                    SELECT material_id, quantity
                                    FROM sub_recipe_ingredients
                                    WHERE sub_recipe_id = ?
                                ");
                                $stmt->execute([$recipe_item['sub_recipe_id']]);
                                $sub_ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($sub_ingredients as $sub_ing) {
                                    $sub_quantity_needed = $sub_ing['quantity'] * $total_quantity_needed;
                                    $result = deductMaterialStock($conn, $order_item_id, $sub_ing['material_id'], $sub_quantity_needed);
                                    if (!$result['success']) {
                                        error_log("Warning: Failed to deduct material {$sub_ing['material_id']} from sub-recipe for product {$item_id}: " . ($result['error'] ?? 'Unknown error') . ". Sale will proceed.");
                                    }
                                }
                            } catch (Exception $sub_e) {
                                error_log("Warning: Error processing sub-recipe for product {$item_id}: " . $sub_e->getMessage() . ". Sale will proceed.");
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // Log error but don't fail transaction - recipe deduction is optional
                error_log("Warning: Error in auto-stock deduction for product {$item_id}: " . $e->getMessage() . ". Sale will proceed without material deduction.");
            }
        }
        
        // Create payment record (verify order_id exists first)
        if (!$order_id || $order_id <= 0) {
            throw new Exception("Cannot create payment: Invalid order_id (" . var_export($order_id, true) . "). Order was not created successfully.");
        }
        
        // Triple-check order exists before inserting payment (within the same transaction)
        $check_order = $conn->prepare("SELECT order_id, cafe_id, cashier_id, total_amount FROM orders WHERE order_id = ?");
        $check_order->execute([$order_id]);
        $order_data = $check_order->fetch(PDO::FETCH_ASSOC);
        
        if (!$order_data) {
            // Order doesn't exist - this should never happen if our checks above worked
            // But let's verify the order was actually inserted by checking the last few orders
            $debug_stmt = $conn->prepare("SELECT order_id, cafe_id, cashier_id, created_at FROM orders WHERE cafe_id = ? ORDER BY order_id DESC LIMIT 5");
            $debug_stmt->execute([$cafe_id]);
            $recent_orders = $debug_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            throw new Exception("Cannot create payment: Order ID {$order_id} does not exist in orders table. Recent orders for cafe_id {$cafe_id}: " . json_encode($recent_orders) . ". The order INSERT must have failed or was rolled back.");
        }
        
        // Verify the order data matches what we expect
        if ($order_data['cafe_id'] != $cafe_id) {
            throw new Exception("Order data mismatch: Expected cafe_id={$cafe_id}, but order has cafe_id={$order_data['cafe_id']}");
        }
        
        // Insert payment
        try {
            // Check if payments table has amount_given and change_amount columns
            $payment_columns = $conn->query("SHOW COLUMNS FROM payments")->fetchAll(PDO::FETCH_COLUMN);
            $has_payment_method = in_array('payment_method', $payment_columns);
            $has_amount_given = in_array('amount_given', $payment_columns);
            $has_change = in_array('change_amount', $payment_columns);
            
            $is_cash = (stripos($payment_method, 'cash') !== false || strtolower($payment_method) === 'cash');
            
            if ($has_payment_method && $has_amount_given && $has_change && $is_cash && $cash_amount_given > 0) {
                $stmt = $conn->prepare("INSERT INTO payments (order_id, payment_method, amount, amount_given, change_amount) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$order_id, $payment_method, $total, $cash_amount_given, $cash_change_amount]);
            } elseif ($has_payment_method) {
                $stmt = $conn->prepare("INSERT INTO payments (order_id, payment_method, amount) VALUES (?, ?, ?)");
                $stmt->execute([$order_id, $payment_method, $total]);
            } else {
                $stmt = $conn->prepare("INSERT INTO payments (order_id, amount) VALUES (?, ?)");
                $stmt->execute([$order_id, $total]);
            }
            
            // Check for errors
            $error_info = $stmt->errorInfo();
            if ($error_info[0] !== '00000' && $error_info[0] !== null && $error_info[0] !== '') {
                throw new Exception("Failed to create payment: " . ($error_info[2] ?? 'Unknown error') . " (SQL State: " . $error_info[0] . "). Order ID {$order_id} exists: " . json_encode($order_data));
            }
        } catch (PDOException $e) {
            $error_code = $e->getCode();
            $error_msg = $e->getMessage();
            
            if ($error_code == 23000 || strpos($error_msg, 'foreign key constraint') !== false) {
                // Foreign key constraint failed - verify order still exists
                $verify_again = $conn->prepare("SELECT order_id FROM orders WHERE order_id = ?");
                $verify_again->execute([$order_id]);
                $still_exists = $verify_again->fetch();
                
                if (!$still_exists) {
                    throw new Exception("Payment insert failed: Order ID {$order_id} does not exist. The order may have been deleted or rolled back. Original error: " . $error_msg);
                } else {
                    throw new Exception("Payment insert failed with foreign key constraint error, but order {$order_id} exists. This is unexpected. Error: " . $error_msg);
                }
            }
            
            throw new Exception("Database error creating payment for order_id {$order_id}: " . $error_msg . ". Order exists: " . json_encode($order_data));
        }
        
        // Handle voucher usage
        if ($voucher_id) {
            // Update usage count for the voucher
            $stmt = $conn->prepare("UPDATE vouchers SET used_count = used_count + 1 WHERE voucher_id = ? AND cafe_id = ?");
            $stmt->execute([$voucher_id, $cafe_id]);
            
            // If QR code was used, mark it in the voucher_codes table (but don't delete it)
            if ($voucher_code_id) {
                try {
                    $stmt = $conn->prepare("
                        UPDATE voucher_codes 
                        SET is_used = 1, used_at = NOW(), used_by_order_id = ? 
                        WHERE code_id = ? AND voucher_id = ?
                    ");
                    $stmt->execute([$order_id, $voucher_code_id, $voucher_id]);
                } catch (Exception $e) {
                    error_log("Error updating voucher code usage: " . $e->getMessage());
                }
            }
        }
        
        $conn->commit();
        
        header('Location: receipt.php?order_id=' . $order_id);
        exit();
        
    } catch (Exception $e) {
        // Only rollback if transaction is active
        if ($conn->inTransaction()) {
            try {
                $conn->rollBack();
            } catch (Exception $rollback_error) {
                error_log("Error during rollback: " . $rollback_error->getMessage());
            }
        }
        
        // Log detailed error information
        $error_details = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'session_user_id' => $_SESSION['user_id'] ?? 'N/A',
            'cafe_id' => $cafe_id ?? 'N/A',
            'customer_id' => $customer_id ?? 'N/A',
            'voucher_id' => $voucher_id ?? 'N/A'
        ];
        error_log("Transaction error details: " . json_encode($error_details, JSON_PRETTY_PRINT));
        
        $_SESSION['error'] = 'Transaction failed: ' . $e->getMessage();
        header('Location: pos.php');
        exit();
    }
} else {
    header('Location: pos.php');
    exit();
}
?>

