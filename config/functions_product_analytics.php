<?php
/**
 * Product Performance Analytics Functions
 * Handles all analytics calculations, recommendations, and data processing
 */

/**
 * Get or create analytics settings for a café
 */
function getAnalyticsSettings($conn, $cafe_id) {
    try {
        $stmt = $conn->prepare("SELECT * FROM product_analytics_settings WHERE cafe_id = ?");
        $stmt->execute([$cafe_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$settings) {
            // Create default settings
            try {
                $stmt = $conn->prepare("
                    INSERT INTO product_analytics_settings 
                    (cafe_id, low_demand_threshold, high_demand_threshold, trend_period_weeks, profit_margin_warning, auto_recommendations_enabled)
                    VALUES (?, 10, 50, 4, 20.00, 1)
                ");
                $stmt->execute([$cafe_id]);
                return getAnalyticsSettings($conn, $cafe_id);
            } catch (PDOException $e) {
                // Table might not exist, return defaults
                error_log("Product Analytics: Could not create settings - " . $e->getMessage());
                return [
                    'low_demand_threshold' => 10,
                    'high_demand_threshold' => 50,
                    'trend_period_weeks' => 4,
                    'profit_margin_warning' => 20.00,
                    'auto_recommendations_enabled' => 1
                ];
            }
        }
        
        // Ensure all keys exist
        return [
            'low_demand_threshold' => $settings['low_demand_threshold'] ?? 10,
            'high_demand_threshold' => $settings['high_demand_threshold'] ?? 50,
            'trend_period_weeks' => $settings['trend_period_weeks'] ?? 4,
            'profit_margin_warning' => $settings['profit_margin_warning'] ?? 20.00,
            'auto_recommendations_enabled' => $settings['auto_recommendations_enabled'] ?? 1
        ];
    } catch (PDOException $e) {
        // Table doesn't exist, return defaults
        error_log("Product Analytics: Settings table not found - " . $e->getMessage());
        return [
            'low_demand_threshold' => 10,
            'high_demand_threshold' => 50,
            'trend_period_weeks' => 4,
            'profit_margin_warning' => 20.00,
            'auto_recommendations_enabled' => 1
        ];
    }
}

/**
 * Calculate product sales for a specific period
 */
function calculateProductSales($conn, $cafe_id, $item_id, $period_start, $period_end) {
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(oi.quantity), 0) as units_sold,
            COALESCE(SUM(oi.subtotal), 0) as revenue,
            COUNT(DISTINCT o.order_id) as order_count
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.order_id
        WHERE o.cafe_id = ? 
        AND oi.item_id = ?
        AND o.payment_status = 'paid'
        AND DATE(o.created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$cafe_id, $item_id, $period_start, $period_end]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function calculateProductProfit($conn, $item_id, $units_sold, $revenue) {

    $stmt = $conn->prepare("SELECT cost_per_unit, price FROM menu_items WHERE item_id = ?");
    $stmt->execute([$item_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $cost_per_unit = $product['cost_per_unit'] ?? 0;
    $profit = $revenue - ($cost_per_unit * $units_sold);
    $profit_margin = $revenue > 0 ? (($profit / $revenue) * 100) : 0;
    
    return [
        'profit' => $profit,
        'profit_margin' => $profit_margin,
        'cost_per_unit' => $cost_per_unit
    ];
}
function detectTrend($conn, $cafe_id, $item_id, $weeks = 4) {
    $periods = [];
    $end_date = date('Y-m-d');
    
    for ($i = $weeks - 1; $i >= 0; $i--) {
        $week_start = date('Y-m-d', strtotime("-$i weeks monday"));
        $week_end = date('Y-m-d', strtotime("$week_start +6 days"));
        
        $sales = calculateProductSales($conn, $cafe_id, $item_id, $week_start, $week_end);
        $periods[] = $sales['units_sold'];
    }
    
    if (count($periods) < 2) {
        return ['direction' => 'stable', 'percentage' => 0];
    }
    
    // Calculate trend using linear regression
    $n = count($periods);
    $sum_x = 0;
    $sum_y = 0;
    $sum_xy = 0;
    $sum_x2 = 0;
    
    for ($i = 0; $i < $n; $i++) {
        $x = $i + 1;
        $y = $periods[$i];
        $sum_x += $x;
        $sum_y += $y;
        $sum_xy += $x * $y;
        $sum_x2 += $x * $x;
    }
    
    $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_x2 - $sum_x * $sum_x);
    $avg_y = $sum_y / $n;
    
    $trend_percentage = $avg_y > 0 ? (($slope / $avg_y) * 100) : 0;
    
    if ($trend_percentage > 5) {
        return ['direction' => 'up', 'percentage' => round($trend_percentage, 2)];
    } elseif ($trend_percentage < -5) {
        return ['direction' => 'down', 'percentage' => round(abs($trend_percentage), 2)];
    } else {
        return ['direction' => 'stable', 'percentage' => round(abs($trend_percentage), 2)];
    }
}

/**
 * Get peak hours for a product
 */
function getPeakHours($conn, $cafe_id, $item_id, $period_start, $period_end) {
    $stmt = $conn->prepare("
        SELECT 
            HOUR(o.created_at) as hour,
            SUM(oi.quantity) as total_quantity
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.order_id
        WHERE o.cafe_id = ? 
        AND oi.item_id = ?
        AND o.payment_status = 'paid'
        AND DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY HOUR(o.created_at)
        ORDER BY total_quantity DESC
    ");
    $stmt->execute([$cafe_id, $item_id, $period_start, $period_end]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hours = array_fill(0, 24, 0);
    foreach ($results as $row) {
        $hours[$row['hour']] = (int)$row['total_quantity'];
    }
    
    return $hours;
}

/**
 * Get weekly sales data for a product
 */
function getWeeklySales($conn, $cafe_id, $item_id, $weeks = 8) {
    $data = [];
    $end_date = date('Y-m-d');
    
    for ($i = $weeks - 1; $i >= 0; $i--) {
        $week_start = date('Y-m-d', strtotime("-$i weeks monday"));
        $week_end = date('Y-m-d', strtotime("$week_start +6 days"));
        
        $sales = calculateProductSales($conn, $cafe_id, $item_id, $week_start, $week_end);
        $profit_data = calculateProductProfit($conn, $item_id, $sales['units_sold'], $sales['revenue']);
        
        $data[] = [
            'week_start' => $week_start,
            'week_end' => $week_end,
            'week_label' => date('M d', strtotime($week_start)) . ' - ' . date('M d', strtotime($week_end)),
            'units_sold' => (int)$sales['units_sold'],
            'revenue' => (float)$sales['revenue'],
            'profit' => (float)$profit_data['profit'],
            'profit_margin' => (float)$profit_data['profit_margin']
        ];
    }
    
    return $data;
}

/**
 * Get monthly sales data for a product
 */
function getMonthlySales($conn, $cafe_id, $item_id, $months = 6) {
    $data = [];
    
    for ($i = $months - 1; $i >= 0; $i--) {
        $month_start = date('Y-m-01', strtotime("-$i months"));
        $month_end = date('Y-m-t', strtotime($month_start));
        
        $sales = calculateProductSales($conn, $cafe_id, $item_id, $month_start, $month_end);
        $profit_data = calculateProductProfit($conn, $item_id, $sales['units_sold'], $sales['revenue']);
        
        $data[] = [
            'month_start' => $month_start,
            'month_end' => $month_end,
            'month_label' => date('M Y', strtotime($month_start)),
            'units_sold' => (int)$sales['units_sold'],
            'revenue' => (float)$sales['revenue'],
            'profit' => (float)$profit_data['profit'],
            'profit_margin' => (float)$profit_data['profit_margin']
        ];
    }
    
    return $data;
}

/**
 * Detect low-demand products
 */
function detectLowDemandProducts($conn, $cafe_id, $settings = null) {
    if (!$settings) {
        $settings = getAnalyticsSettings($conn, $cafe_id);
    }
    
    $weeks = $settings['trend_period_weeks'];
    $threshold = $settings['low_demand_threshold'];
    
    // Get all products
    $stmt = $conn->prepare("
        SELECT mi.item_id, mi.item_name, mi.price, mi.stock, mi.status, 
               mc.category_name, mi.cost_per_unit
        FROM menu_items mi
        LEFT JOIN menu_categories mc ON mi.category_id = mc.category_id
        WHERE mi.cafe_id = ?
        ORDER BY mi.item_name
    ");
    $stmt->execute([$cafe_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $low_demand = [];
    $end_date = date('Y-m-d');
    $period_start = date('Y-m-d', strtotime("-$weeks weeks monday"));
    
    foreach ($products as $product) {
        $sales = calculateProductSales($conn, $cafe_id, $product['item_id'], $period_start, $end_date);
        $weekly_avg = $sales['units_sold'] / $weeks;
        
        if ($weekly_avg < $threshold) {
            $trend = detectTrend($conn, $cafe_id, $product['item_id'], $weeks);
            $profit_data = calculateProductProfit($conn, $product['item_id'], $sales['units_sold'], $sales['revenue']);
            
            $low_demand[] = [
                'item_id' => $product['item_id'],
                'item_name' => $product['item_name'],
                'category' => $product['category_name'],
                'price' => $product['price'],
                'stock' => $product['stock'],
                'status' => $product['status'],
                'units_sold' => (int)$sales['units_sold'],
                'weekly_avg' => round($weekly_avg, 2),
                'revenue' => (float)$sales['revenue'],
                'profit' => (float)$profit_data['profit'],
                'profit_margin' => (float)$profit_data['profit_margin'],
                'trend' => $trend,
                'cost_per_unit' => $product['cost_per_unit'] ?? 0
            ];
        }
    }
    
    // Sort by weekly average (lowest first)
    usort($low_demand, function($a, $b) {
        return $a['weekly_avg'] <=> $b['weekly_avg'];
    });
    
    return $low_demand;
}

/**
 * Detect high-demand products
 */
function detectHighDemandProducts($conn, $cafe_id, $settings = null) {
    if (!$settings) {
        $settings = getAnalyticsSettings($conn, $cafe_id);
    }
    
    $weeks = $settings['trend_period_weeks'];
    $threshold = $settings['high_demand_threshold'];
    
    // Get all products
    $stmt = $conn->prepare("
        SELECT mi.item_id, mi.item_name, mi.price, mi.stock, mi.status, 
               mc.category_name, mi.cost_per_unit
        FROM menu_items mi
        LEFT JOIN menu_categories mc ON mi.category_id = mc.category_id
        WHERE mi.cafe_id = ?
        ORDER BY mi.item_name
    ");
    $stmt->execute([$cafe_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $high_demand = [];
    $end_date = date('Y-m-d');
    $period_start = date('Y-m-d', strtotime("-$weeks weeks monday"));
    
    foreach ($products as $product) {
        $sales = calculateProductSales($conn, $cafe_id, $product['item_id'], $period_start, $end_date);
        $weekly_avg = $sales['units_sold'] / $weeks;
        
        if ($weekly_avg >= $threshold) {
            $trend = detectTrend($conn, $cafe_id, $product['item_id'], $weeks);
            $profit_data = calculateProductProfit($conn, $product['item_id'], $sales['units_sold'], $sales['revenue']);
            $peak_hours = getPeakHours($conn, $cafe_id, $product['item_id'], $period_start, $end_date);
            
            $high_demand[] = [
                'item_id' => $product['item_id'],
                'item_name' => $product['item_name'],
                'category' => $product['category_name'],
                'price' => $product['price'],
                'stock' => $product['stock'],
                'status' => $product['status'],
                'units_sold' => (int)$sales['units_sold'],
                'weekly_avg' => round($weekly_avg, 2),
                'revenue' => (float)$sales['revenue'],
                'profit' => (float)$profit_data['profit'],
                'profit_margin' => (float)$profit_data['profit_margin'],
                'trend' => $trend,
                'peak_hours' => $peak_hours,
                'cost_per_unit' => $product['cost_per_unit'] ?? 0
            ];
        }
    }
    
    // Sort by weekly average (highest first)
    usort($high_demand, function($a, $b) {
        return $b['weekly_avg'] <=> $a['weekly_avg'];
    });
    
    return $high_demand;
}

/**
 * Generate recommendations for low-demand products
 */
function generateLowDemandRecommendations($conn, $cafe_id, $product) {
    $recommendations = [];
    $priority = 'medium';
    
    // Check profit margin
    if ($product['profit_margin'] < 20) {
        $priority = 'high';
        $recommendations[] = [
            'type' => 'price_reduction',
            'priority' => $priority,
            'title' => 'Consider Price Reduction',
            'description' => "Low profit margin ({$product['profit_margin']}%) suggests pricing may be too high. Reducing price by 10-15% could increase demand.",
            'suggested_value' => $product['price'] * 0.90,
            'estimated_impact' => 'Could increase sales by 20-30% while maintaining similar profit per unit'
        ];
    }
    
    // Check if downtrend
    if ($product['trend']['direction'] == 'down' && $product['trend']['percentage'] > 10) {
        $priority = 'high';
        $recommendations[] = [
            'type' => 'discount',
            'priority' => $priority,
            'title' => 'Create Discount Promotion',
            'description' => "Product shows {$product['trend']['percentage']}% downtrend. A limited-time discount could boost sales.",
            'suggested_value' => $product['price'] * 0.85,
            'estimated_impact' => 'Temporary discount could reverse the downtrend and attract customers'
        ];
    }
    
    // Check stock levels
    if ($product['stock'] > 50 && $product['weekly_avg'] < 5) {
        $recommendations[] = [
            'type' => 'bundle',
            'priority' => 'medium',
            'title' => 'Create Bundle Offer',
            'description' => "High stock with low sales. Bundle with popular items to increase visibility and sales.",
            'suggested_value' => null,
            'estimated_impact' => 'Bundling can increase sales by 15-25% and reduce inventory'
        ];
    }
    
    // If very low sales
    if ($product['weekly_avg'] < 2) {
        $priority = 'urgent';
        $recommendations[] = [
            'type' => 'repositioning',
            'priority' => $priority,
            'title' => 'Reposition Product',
            'description' => "Extremely low sales ({$product['weekly_avg']} units/week). Consider moving to a more visible category or highlighting in menu.",
            'suggested_value' => null,
            'estimated_impact' => 'Better positioning could increase visibility and sales'
        ];
        
        // Consider deletion if no sales for extended period
        if ($product['units_sold'] == 0) {
            $recommendations[] = [
                'type' => 'delete',
                'priority' => 'low',
                'title' => 'Consider Removing Product',
                'description' => "No sales recorded. Product may not be appealing to customers. Consider removing to simplify menu.",
                'suggested_value' => null,
                'estimated_impact' => 'Removing underperforming products can improve menu focus and reduce costs'
            ];
        }
    }
    
    // Always suggest voucher
    $recommendations[] = [
        'type' => 'voucher',
        'priority' => 'medium',
        'title' => 'Create Product-Specific Voucher',
        'description' => "Create a voucher specifically for this product to drive sales.",
        'suggested_value' => $product['price'] * 0.20, // 20% discount
        'estimated_impact' => 'Vouchers can increase product awareness and trial'
    ];
    
    return $recommendations;
}

/**
 * Generate recommendations for high-demand products
 */
function generateHighDemandRecommendations($conn, $cafe_id, $product) {
    $recommendations = [];
    $priority = 'medium';
    
    // Check if uptrend
    if ($product['trend']['direction'] == 'up' && $product['trend']['percentage'] > 10) {
        $priority = 'high';
        $recommendations[] = [
            'type' => 'price_increase',
            'priority' => $priority,
            'title' => 'Consider Price Increase',
            'description' => "Product shows {$product['trend']['percentage']}% uptrend. Small price increase (5-10%) could maximize profit without significantly affecting demand.",
            'suggested_value' => $product['price'] * 1.08,
            'estimated_impact' => 'Could increase revenue by 5-10% with minimal impact on sales volume'
        ];
    }
    
    // Check profit margin
    if ($product['profit_margin'] > 40 && $product['trend']['direction'] == 'up') {
        $recommendations[] = [
            'type' => 'premium_version',
            'priority' => 'medium',
            'title' => 'Create Premium Version',
            'description' => "High demand and profit margin. Consider creating a premium version with additional features/ingredients.",
            'suggested_value' => $product['price'] * 1.30,
            'estimated_impact' => 'Premium version can capture higher-value customers and increase average order value'
        ];
    }
    
    // Check stock levels
    if ($product['stock'] < 20 && $product['weekly_avg'] > 30) {
        $priority = 'high';
        $recommendations[] = [
            'type' => 'stock_optimization',
            'priority' => $priority,
            'title' => 'Increase Stock Levels',
            'description' => "High demand but low stock. Risk of stockouts. Increase stock to meet demand.",
            'suggested_value' => $product['weekly_avg'] * 2, // 2 weeks supply
            'estimated_impact' => 'Prevents stockouts and lost sales opportunities'
        ];
    }
    
    // Check peak hours for upsell opportunities
    $peak_hours_count = count(array_filter($product['peak_hours'], function($h) { return $h > 0; }));
    if ($peak_hours_count > 0) {
        $recommendations[] = [
            'type' => 'upsell',
            'priority' => 'medium',
            'title' => 'Upsell Opportunities',
            'description' => "Product has clear peak hours. Train staff to suggest complementary items during peak times.",
            'suggested_value' => null,
            'estimated_impact' => 'Upselling can increase average order value by 10-20%'
        ];
    }
    
    // Suggest bundle creation
    $recommendations[] = [
        'type' => 'bundle',
        'priority' => 'low',
        'title' => 'Create Bundle with Low-Demand Items',
        'description' => "Use this popular product to bundle with slower-moving items to increase their sales.",
        'suggested_value' => null,
        'estimated_impact' => 'Bundling can help move inventory and increase overall sales'
    ];
    
    return $recommendations;
}

/**
 * Save recommendations to database
 */
function saveRecommendations($conn, $cafe_id, $item_id, $recommendations) {
    // Delete existing pending recommendations for this product
    $stmt = $conn->prepare("
        DELETE FROM product_recommendations 
        WHERE cafe_id = ? AND item_id = ? AND status = 'pending'
    ");
    $stmt->execute([$cafe_id, $item_id]);
    
    // Insert new recommendations
    $stmt = $conn->prepare("
        INSERT INTO product_recommendations 
        (cafe_id, item_id, recommendation_type, priority, title, description, suggested_value, estimated_impact, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    
    foreach ($recommendations as $rec) {
        $stmt->execute([
            $cafe_id,
            $item_id,
            $rec['type'],
            $rec['priority'],
            $rec['title'],
            $rec['description'],
            $rec['suggested_value'],
            $rec['estimated_impact']
        ]);
    }
}

/**
 * Simulate profit impact of price change
 */
function simulateProfitImpact($conn, $cafe_id, $item_id, $new_price, $demand_change_percent = 0) {
    // Get current product data
    $stmt = $conn->prepare("
        SELECT mi.*, 
               COALESCE(SUM(oi.quantity), 0) as current_units_sold,
               COALESCE(SUM(oi.subtotal), 0) as current_revenue
        FROM menu_items mi
        LEFT JOIN order_items oi ON mi.item_id = oi.item_id
        LEFT JOIN orders o ON oi.order_id = o.order_id AND o.cafe_id = ? AND o.payment_status = 'paid'
        WHERE mi.item_id = ?
        GROUP BY mi.item_id
    ");
    $stmt->execute([$cafe_id, $item_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $current_price = $product['price'];
    $cost_per_unit = $product['cost_per_unit'] ?? 0;
    $current_units = (int)$product['current_units_sold'];
    $current_revenue = (float)$product['current_revenue'];
    
    // Calculate current profit
    $current_profit = $current_revenue - ($cost_per_unit * $current_units);
    $current_profit_margin = $current_revenue > 0 ? (($current_profit / $current_revenue) * 100) : 0;
    
    // Simulate new scenario
    $new_units = $current_units * (1 + ($demand_change_percent / 100));
    $new_revenue = $new_units * $new_price;
    $new_profit = $new_revenue - ($cost_per_unit * $new_units);
    $new_profit_margin = $new_revenue > 0 ? (($new_profit / $new_revenue) * 100) : 0;
    
    return [
        'current' => [
            'price' => $current_price,
            'units' => $current_units,
            'revenue' => $current_revenue,
            'profit' => $current_profit,
            'profit_margin' => $current_profit_margin
        ],
        'simulated' => [
            'price' => $new_price,
            'units' => round($new_units),
            'revenue' => round($new_revenue, 2),
            'profit' => round($new_profit, 2),
            'profit_margin' => round($new_profit_margin, 2)
        ],
        'impact' => [
            'revenue_change' => round($new_revenue - $current_revenue, 2),
            'revenue_change_percent' => round((($new_revenue - $current_revenue) / ($current_revenue > 0 ? $current_revenue : 1)) * 100, 2),
            'profit_change' => round($new_profit - $current_profit, 2),
            'profit_change_percent' => round((($new_profit - $current_profit) / ($current_profit > 0 ? $current_profit : 1)) * 100, 2)
        ]
    ];
}
?>

