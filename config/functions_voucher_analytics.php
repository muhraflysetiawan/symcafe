<?php
/**
 * Voucher Analytics Helper Functions
 */

/**
 * Calculate voucher performance metrics
 */
function calculateVoucherPerformance($conn, $voucher_id, $start_date, $end_date) {
    try {
        // Get voucher info
        $stmt = $conn->prepare("SELECT cafe_id FROM vouchers WHERE voucher_id = ?");
        $stmt->execute([$voucher_id]);
        $voucher_info = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$voucher_info) return null;
        $cafe_id = $voucher_info['cafe_id'];
        
        // Get all orders using this voucher (from voucher_usage_log or orders table)
        $columns = $conn->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
        $has_voucher_column = in_array('voucher_id', $columns);
        
        if ($has_voucher_column) {
            // Use orders table if voucher_id column exists
            $stmt = $conn->prepare("
                SELECT o.*, oi.*, c.customer_id
                FROM orders o
                JOIN order_items oi ON o.order_id = oi.order_id
                LEFT JOIN customers c ON o.customer_id = c.customer_id
                WHERE o.voucher_id = ? 
                AND DATE(o.created_at) BETWEEN ? AND ?
                AND o.payment_status = 'paid'
            ");
            $stmt->execute([$voucher_id, $start_date, $end_date]);
            $voucher_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Use voucher_usage_log table
            $stmt = $conn->prepare("
                SELECT vul.*, o.*, oi.*, c.customer_id
                FROM voucher_usage_log vul
                JOIN orders o ON vul.order_id = o.order_id
                JOIN order_items oi ON o.order_id = oi.order_id
                LEFT JOIN customers c ON o.customer_id = c.customer_id
                WHERE vul.voucher_id = ?
                AND DATE(vul.used_at) BETWEEN ? AND ?
                AND o.payment_status = 'paid'
            ");
            $stmt->execute([$voucher_id, $start_date, $end_date]);
            $voucher_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Get orders without voucher for comparison
        $stmt = $conn->prepare("
            SELECT o.*, oi.*
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            WHERE o.cafe_id = ?
            AND (o.voucher_id IS NULL OR o.voucher_id != ?)
            AND DATE(o.created_at) BETWEEN ? AND ?
            AND o.payment_status = 'paid'
        ");
        $stmt->execute([$cafe_id, $voucher_id, $start_date, $end_date]);
        $non_voucher_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate metrics
        $total_uses = count(array_unique(array_column($voucher_orders, 'order_id')));
        $unique_customers = count(array_unique(array_filter(array_column($voucher_orders, 'customer_id'))));
        
        // Calculate redemption rate (uses / total orders in period)
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total_orders
            FROM orders
            WHERE cafe_id = ?
            AND DATE(created_at) BETWEEN ? AND ?
            AND payment_status = 'paid'
        ");
        $stmt->execute([$cafe_id, $start_date, $end_date]);
        $total_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total_orders'];
        $redemption_rate = $total_orders > 0 ? ($total_uses / $total_orders) * 100 : 0;
        
        // Calculate totals
        $total_discount = 0;
        $total_revenue_before = 0;
        $total_revenue_after = 0;
        $order_totals_with = [];
        $order_totals_without = [];
        $hour_counts = [];
        $day_counts = [];
        
        // Process voucher orders
        $processed_orders = [];
        foreach ($voucher_orders as $order) {
            $order_id = $order['order_id'];
            if (!isset($processed_orders[$order_id])) {
                $processed_orders[$order_id] = [
                    'subtotal' => 0,
                    'discount' => $order['discount'] ?? 0,
                    'total' => $order['total_amount'] ?? 0,
                    'customer_id' => $order['customer_id'],
                    'created_at' => $order['created_at']
                ];
            }
            $processed_orders[$order_id]['subtotal'] += $order['subtotal'] ?? 0;
        }
        
        foreach ($processed_orders as $order) {
            $total_revenue_before += $order['subtotal'];
            $total_discount += $order['discount'];
            $total_revenue_after += $order['total'];
            $order_totals_with[] = $order['total'];
            
            // Get hour and day from order or usage log
            if (isset($order['used_at'])) {
                $hour = (int)date('H', strtotime($order['used_at']));
                $day = date('l', strtotime($order['used_at']));
            } elseif (isset($order['hour_of_day'])) {
                $hour = (int)$order['hour_of_day'];
                $day = $order['day_of_week'] ?? date('l');
            } else {
                $hour = (int)date('H', strtotime($order['created_at']));
                $day = date('l', strtotime($order['created_at']));
            }
            $hour_counts[$hour] = ($hour_counts[$hour] ?? 0) + 1;
            $day_counts[$day] = ($day_counts[$day] ?? 0) + 1;
        }
        
        // Process non-voucher orders
        $processed_non_voucher = [];
        foreach ($non_voucher_orders as $order) {
            $order_id = $order['order_id'];
            if (!isset($processed_non_voucher[$order_id])) {
                $processed_non_voucher[$order_id] = $order['total_amount'] ?? 0;
            }
        }
        $order_totals_without = array_values($processed_non_voucher);
        
        // Calculate averages
        $avg_order_with = count($order_totals_with) > 0 ? array_sum($order_totals_with) / count($order_totals_with) : 0;
        $avg_order_without = count($order_totals_without) > 0 ? array_sum($order_totals_without) / count($order_totals_without) : 0;
        
        // Find peak hour and day
        $peak_hour = !empty($hour_counts) ? array_search(max($hour_counts), $hour_counts) : null;
        $peak_day = !empty($day_counts) ? array_search(max($day_counts), $day_counts) : null;
        
        // Calculate transaction growth
        $columns = $conn->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
        $has_voucher_column = in_array('voucher_id', $columns);
        
        if ($has_voucher_column) {
            $stmt = $conn->prepare("
                SELECT COUNT(DISTINCT DATE(created_at)) as days_with_voucher
                FROM orders
                WHERE voucher_id = ?
                AND DATE(created_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$voucher_id, $start_date, $end_date]);
            $days_with_voucher = $stmt->fetch(PDO::FETCH_ASSOC)['days_with_voucher'];
        } else {
            $stmt = $conn->prepare("
                SELECT COUNT(DISTINCT DATE(used_at)) as days_with_voucher
                FROM voucher_usage_log
                WHERE voucher_id = ?
                AND DATE(used_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$voucher_id, $start_date, $end_date]);
            $days_with_voucher = $stmt->fetch(PDO::FETCH_ASSOC)['days_with_voucher'];
        }
        
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT DATE(created_at)) as total_days
            FROM orders
            WHERE cafe_id = ?
            AND DATE(created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$cafe_id, $start_date, $end_date]);
        $total_days = $stmt->fetch(PDO::FETCH_ASSOC)['total_days'];
        
        $transaction_growth = $total_days > 0 ? (($days_with_voucher / $total_days) * 100) : 0;
        
        return [
            'total_uses' => $total_uses,
            'unique_customers' => $unique_customers,
            'redemption_rate' => round($redemption_rate, 2),
            'total_discount_given' => $total_discount,
            'total_revenue_before_discount' => $total_revenue_before,
            'total_revenue_after_discount' => $total_revenue_after,
            'avg_order_value_with_voucher' => round($avg_order_with, 2),
            'avg_order_value_without_voucher' => round($avg_order_without, 2),
            'transaction_growth' => round($transaction_growth, 2),
            'peak_hour' => $peak_hour,
            'peak_day' => $peak_day,
            'hour_distribution' => $hour_counts,
            'day_distribution' => $day_counts
        ];
    } catch (Exception $e) {
        error_log("Error calculating voucher performance: " . $e->getMessage());
        return null;
    }
}

/**
 * Calculate profit impact of a voucher
 */
function calculateVoucherProfitImpact($conn, $voucher_id, $start_date, $end_date) {
    try {
        require_once __DIR__ . '/functions_inventory.php';
        
        // Get orders with voucher
        $stmt = $conn->prepare("
            SELECT o.*, oi.*
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            WHERE o.voucher_id = ?
            AND DATE(o.created_at) BETWEEN ? AND ?
            AND o.payment_status = 'paid'
        ");
        $stmt->execute([$voucher_id, $start_date, $end_date]);
        $voucher_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get orders without voucher for comparison
        $stmt = $conn->prepare("
            SELECT o.*, oi.*
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            WHERE o.cafe_id = (SELECT cafe_id FROM vouchers WHERE voucher_id = ?)
            AND (o.voucher_id IS NULL OR o.voucher_id != ?)
            AND DATE(o.created_at) BETWEEN ? AND ?
            AND o.payment_status = 'paid'
        ");
        $stmt->execute([$voucher_id, $voucher_id, $start_date, $end_date]);
        $non_voucher_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate revenue and COGS for voucher orders
        $revenue_before = 0;
        $revenue_after = 0;
        $discount_given = 0;
        $cogs_with_voucher = 0;
        
        $processed_voucher = [];
        foreach ($voucher_orders as $order) {
            $order_id = $order['order_id'];
            if (!isset($processed_voucher[$order_id])) {
                $processed_voucher[$order_id] = [
                    'subtotal' => 0,
                    'discount' => $order['discount'] ?? 0,
                    'total' => $order['total_amount'] ?? 0
                ];
            }
            $processed_voucher[$order_id]['subtotal'] += $order['subtotal'] ?? 0;
            
            // Calculate COGS for this item
            $item_id = $order['item_id'];
            $ingredient_cost = calculateProductIngredientCost($conn, $item_id);
            $cogs_with_voucher += ($ingredient_cost * ($order['quantity'] ?? 0));
        }
        
        foreach ($processed_voucher as $order) {
            $revenue_before += $order['subtotal'];
            $discount_given += $order['discount'];
            $revenue_after += $order['total'];
        }
        
        // Calculate revenue and COGS for non-voucher orders
        $revenue_without_voucher = 0;
        $cogs_without_voucher = 0;
        
        $processed_non_voucher = [];
        foreach ($non_voucher_orders as $order) {
            $order_id = $order['order_id'];
            if (!isset($processed_non_voucher[$order_id])) {
                $processed_non_voucher[$order_id] = $order['total_amount'] ?? 0;
            }
            
            $item_id = $order['item_id'];
            $ingredient_cost = calculateProductIngredientCost($conn, $item_id);
            $cogs_without_voucher += ($ingredient_cost * ($order['quantity'] ?? 0));
        }
        $revenue_without_voucher = array_sum(array_values($processed_non_voucher));
        
        // Calculate profits
        $gross_profit_before = $revenue_before - $cogs_with_voucher;
        $gross_profit_after = $revenue_after - $cogs_with_voucher;
        
        // Calculate margins
        $profit_margin_before = $revenue_before > 0 ? (($gross_profit_before / $revenue_before) * 100) : 0;
        $profit_margin_after = $revenue_after > 0 ? (($gross_profit_after / $revenue_after) * 100) : 0;
        
        // Calculate difference
        $profit_difference = $gross_profit_after - $gross_profit_before;
        $profit_impact_percent = $gross_profit_before > 0 ? (($profit_difference / $gross_profit_before) * 100) : 0;
        
        // Generate recommendation
        $recommendation = 'continue';
        if ($profit_impact_percent < -10) {
            $recommendation = 'discontinue';
        } elseif ($profit_impact_percent < 0) {
            $recommendation = 'adjust';
        }
        
        return [
            'total_revenue_before_discount' => round($revenue_before, 2),
            'total_discount_given' => round($discount_given, 2),
            'net_revenue_after_discount' => round($revenue_after, 2),
            'gross_profit_before_voucher' => round($gross_profit_before, 2),
            'gross_profit_after_voucher' => round($gross_profit_after, 2),
            'profit_margin_before' => round($profit_margin_before, 2),
            'profit_margin_after' => round($profit_margin_after, 2),
            'cogs_before' => round($cogs_with_voucher, 2),
            'cogs_after' => round($cogs_with_voucher, 2),
            'profit_difference' => round($profit_difference, 2),
            'profit_impact_percent' => round($profit_impact_percent, 2),
            'recommendation' => $recommendation
        ];
    } catch (Exception $e) {
        error_log("Error calculating profit impact: " . $e->getMessage());
        return null;
    }
}

/**
 * Calculate product impact of a voucher
 */
function calculateVoucherProductImpact($conn, $voucher_id, $start_date, $end_date) {
    try {
        require_once __DIR__ . '/functions_inventory.php';
        
        // Get voucher info
        $stmt = $conn->prepare("SELECT cafe_id FROM vouchers WHERE voucher_id = ?");
        $stmt->execute([$voucher_id]);
        $voucher_info = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$voucher_info) return [];
        $cafe_id = $voucher_info['cafe_id'];
        
        // Check if orders table has voucher_id column
        $columns = $conn->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
        $has_voucher_column = in_array('voucher_id', $columns);
        
        // Get products sold with voucher
        if ($has_voucher_column) {
            $stmt = $conn->prepare("
                SELECT oi.item_id, SUM(oi.quantity) as total_quantity, SUM(oi.subtotal) as total_revenue
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.order_id
                WHERE o.voucher_id = ?
                AND DATE(o.created_at) BETWEEN ? AND ?
                AND o.payment_status = 'paid'
                GROUP BY oi.item_id
            ");
            $stmt->execute([$voucher_id, $start_date, $end_date]);
            $products_with_voucher = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Use voucher_usage_log
            $stmt = $conn->prepare("
                SELECT oi.item_id, SUM(oi.quantity) as total_quantity, SUM(oi.subtotal) as total_revenue
                FROM voucher_usage_log vul
                JOIN orders o ON vul.order_id = o.order_id
                JOIN order_items oi ON o.order_id = oi.order_id
                WHERE vul.voucher_id = ?
                AND DATE(vul.used_at) BETWEEN ? AND ?
                AND o.payment_status = 'paid'
                GROUP BY oi.item_id
            ");
            $stmt->execute([$voucher_id, $start_date, $end_date]);
            $products_with_voucher = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Get products sold without voucher
        $stmt = $conn->prepare("
            SELECT oi.item_id, SUM(oi.quantity) as total_quantity, SUM(oi.subtotal) as total_revenue
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            WHERE o.cafe_id = ?
            AND (o.voucher_id IS NULL OR o.voucher_id != ?)
            AND DATE(o.created_at) BETWEEN ? AND ?
            AND o.payment_status = 'paid'
            GROUP BY oi.item_id
        ");
        $stmt->execute([$cafe_id, $voucher_id, $start_date, $end_date]);
        $products_without_voucher = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Combine data
        $product_impact = [];
        
        // Process products with voucher
        foreach ($products_with_voucher as $prod) {
            $item_id = $prod['item_id'];
            $ingredient_cost = calculateProductIngredientCost($conn, $item_id);
            $profit = $prod['total_revenue'] - ($ingredient_cost * $prod['total_quantity']);
            
            $product_impact[$item_id] = [
                'item_id' => $item_id,
                'units_sold_with_voucher' => $prod['total_quantity'],
                'units_sold_without_voucher' => 0,
                'revenue_with_voucher' => $prod['total_revenue'],
                'revenue_without_voucher' => 0,
                'profit_with_voucher' => $profit,
                'profit_without_voucher' => 0,
                'ingredient_cost' => $ingredient_cost
            ];
        }
        
        // Process products without voucher
        foreach ($products_without_voucher as $prod) {
            $item_id = $prod['item_id'];
            if (!isset($product_impact[$item_id])) {
                $ingredient_cost = calculateProductIngredientCost($conn, $item_id);
                $profit = $prod['total_revenue'] - ($ingredient_cost * $prod['total_quantity']);
                
                $product_impact[$item_id] = [
                    'item_id' => $item_id,
                    'units_sold_with_voucher' => 0,
                    'units_sold_without_voucher' => $prod['total_quantity'],
                    'revenue_with_voucher' => 0,
                    'revenue_without_voucher' => $prod['total_revenue'],
                    'profit_with_voucher' => 0,
                    'profit_without_voucher' => $profit,
                    'ingredient_cost' => $ingredient_cost
                ];
            } else {
                $ingredient_cost = $product_impact[$item_id]['ingredient_cost'];
                $profit = $prod['total_revenue'] - ($ingredient_cost * $prod['total_quantity']);
                
                $product_impact[$item_id]['units_sold_without_voucher'] = $prod['total_quantity'];
                $product_impact[$item_id]['revenue_without_voucher'] = $prod['total_revenue'];
                $product_impact[$item_id]['profit_without_voucher'] = $profit;
            }
        }
        
        // Calculate additional metrics
        foreach ($product_impact as &$impact) {
            $impact['ingredient_cost_increase'] = ($impact['units_sold_with_voucher'] - $impact['units_sold_without_voucher']) * $impact['ingredient_cost'];
            
            // Price sensitivity score (higher sales with voucher = more sensitive)
            $total_units = $impact['units_sold_with_voucher'] + $impact['units_sold_without_voucher'];
            $impact['price_sensitivity_score'] = $total_units > 0 ? (($impact['units_sold_with_voucher'] / $total_units) * 100) : 0;
        }
        
        return array_values($product_impact);
    } catch (Exception $e) {
        error_log("Error calculating product impact: " . $e->getMessage());
        return [];
    }
}

?>

