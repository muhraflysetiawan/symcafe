<?php
require_once 'config/config.php';
requireLogin();
requireCafeSetup();
requireRole(['owner']);

require_once 'config/functions_product_analytics.php';

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

// Get analytics settings
$settings = getAnalyticsSettings($conn, $cafe_id);

// Ensure all required settings keys exist with defaults
if (!$settings || !isset($settings['trend_period_weeks'])) {
    // If settings don't exist, create them with defaults
    $settings = [
        'low_demand_threshold' => $settings['low_demand_threshold'] ?? 10,
        'high_demand_threshold' => $settings['high_demand_threshold'] ?? 50,
        'trend_period_weeks' => $settings['trend_period_weeks'] ?? 4,
        'profit_margin_warning' => $settings['profit_margin_warning'] ?? 20.00,
        'auto_recommendations_enabled' => $settings['auto_recommendations_enabled'] ?? 1
    ];
    // Try to get settings again or use defaults
    $settings = getAnalyticsSettings($conn, $cafe_id) ?: $settings;
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {
    $low_threshold = (int)$_POST['low_demand_threshold'];
    $high_threshold = (int)$_POST['high_demand_threshold'];
    $trend_weeks = (int)$_POST['trend_period_weeks'];
    $profit_warning = (float)$_POST['profit_margin_warning'];
    $auto_rec = isset($_POST['auto_recommendations_enabled']) ? 1 : 0;
    
    $stmt = $conn->prepare("
        UPDATE product_analytics_settings 
        SET low_demand_threshold = ?, high_demand_threshold = ?, 
            trend_period_weeks = ?, profit_margin_warning = ?, 
            auto_recommendations_enabled = ?
        WHERE cafe_id = ?
    ");
    $stmt->execute([$low_threshold, $high_threshold, $trend_weeks, $profit_warning, $auto_rec, $cafe_id]);
    $settings = getAnalyticsSettings($conn, $cafe_id);
    $success_message = "Settings updated successfully!";
}

// Get active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';
$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

// Generate recommendations if auto-recommendations is enabled
if (isset($settings['auto_recommendations_enabled']) && $settings['auto_recommendations_enabled']) {
    $low_demand_products = detectLowDemandProducts($conn, $cafe_id, $settings);
    $high_demand_products = detectHighDemandProducts($conn, $cafe_id, $settings);
    
    // Generate and save recommendations
    foreach ($low_demand_products as $product) {
        $recommendations = generateLowDemandRecommendations($conn, $cafe_id, $product);
        saveRecommendations($conn, $cafe_id, $product['item_id'], $recommendations);
    }
    
    foreach ($high_demand_products as $product) {
        $recommendations = generateHighDemandRecommendations($conn, $cafe_id, $product);
        saveRecommendations($conn, $cafe_id, $product['item_id'], $recommendations);
    }
} else {
    $low_demand_products = detectLowDemandProducts($conn, $cafe_id, $settings);
    $high_demand_products = detectHighDemandProducts($conn, $cafe_id, $settings);
}

// Get all recommendations
$stmt = $conn->prepare("
    SELECT pr.*, mi.item_name, mc.category_name
    FROM product_recommendations pr
    INNER JOIN menu_items mi ON pr.item_id = mi.item_id
    LEFT JOIN menu_categories mc ON mi.category_id = mc.category_id
    WHERE pr.cafe_id = ? AND pr.status = 'pending'
    ORDER BY 
        CASE pr.priority 
            WHEN 'urgent' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'medium' THEN 3 
            WHEN 'low' THEN 4 
        END,
        pr.created_at DESC
");
$stmt->execute([$cafe_id]);
$all_recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Product Performance Analytics';
include 'includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">📊 Product Performance Analytics</h2>

<?php if (isset($success_message)): ?>
    <div class="alert alert-success" style="margin-bottom: 20px;"><?php echo htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<!-- Analytics Tabs -->
<div class="subnav-container">
    <div class="subnav-tabs">
        <a href="?tab=overview" class="subnav-tab <?php echo $active_tab == 'overview' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Overview</span>
        </a>
        <a href="?tab=low_demand" class="subnav-tab <?php echo $active_tab == 'low_demand' ? 'active' : ''; ?>">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Low-Demand Products</span>
        </a>
        <a href="?tab=high_demand" class="subnav-tab <?php echo $active_tab == 'high_demand' ? 'active' : ''; ?>">
            <i class="fas fa-star"></i>
            <span>High-Demand Products</span>
        </a>
        <a href="?tab=recommendations" class="subnav-tab <?php echo $active_tab == 'recommendations' ? 'active' : ''; ?>">
            <i class="fas fa-lightbulb"></i>
            <span>Recommendations</span>
        </a>
        <a href="?tab=simulator" class="subnav-tab <?php echo $active_tab == 'simulator' ? 'active' : ''; ?>">
            <i class="fas fa-calculator"></i>
            <span>Profit Simulator</span>
        </a>
        <a href="?tab=settings" class="subnav-tab <?php echo $active_tab == 'settings' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a>
    </div>
</div>

<?php if ($active_tab == 'overview'): ?>
    <!-- Overview Tab -->
    <div class="dashboard-grid">
        <div class="dashboard-card card-danger">
            <i class="fas fa-exclamation-triangle card-bg-icon"></i>
            <div class="card-header">
                <div class="card-title">Low-Demand Products</div>
            </div>
            <div class="card-value"><?php echo count($low_demand_products); ?></div>
            <div class="card-subtitle">Products below threshold</div>
        </div>
        
        <div class="dashboard-card card-success">
            <i class="fas fa-star card-bg-icon"></i>
            <div class="card-header">
                <div class="card-title">High-Demand Products</div>
            </div>
            <div class="card-value"><?php echo count($high_demand_products); ?></div>
            <div class="card-subtitle">Products above threshold</div>
        </div>
        
        <div class="dashboard-card card-warning">
            <i class="fas fa-lightbulb card-bg-icon"></i>
            <div class="card-header">
                <div class="card-title">Pending Recommendations</div>
            </div>
            <div class="card-value"><?php echo count($all_recommendations); ?></div>
            <div class="card-subtitle">Actionable insights available</div>
        </div>
        
        <div class="dashboard-card card-info">
            <i class="fas fa-chart-line card-bg-icon"></i>
            <div class="card-header">
                <div class="card-title">Analysis Period</div>
            </div>
            <div class="card-value"><?php echo $settings['trend_period_weeks'] ?? 4; ?> weeks</div>
            <div class="card-subtitle">Trend detection period</div>
        </div>
    </div>
    
    <!-- Quick Stats Chart -->
    <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px; margin-top: 30px;">
        <h3 style="color: var(--primary-white); margin-bottom: 20px;">Product Performance Distribution</h3>
        <canvas id="overviewChart" style="max-height: 300px;"></canvas>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    const ctx = document.getElementById('overviewChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Low-Demand', 'High-Demand', 'Normal'],
            datasets: [{
                data: [
                    <?php echo count($low_demand_products); ?>,
                    <?php echo count($high_demand_products); ?>,
                    <?php 
                    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM menu_items WHERE cafe_id = ?");
                    $stmt->execute([$cafe_id]);
                    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                    echo max(0, $total - count($low_demand_products) - count($high_demand_products));
                    ?>
                ],
                backgroundColor: ['#dc3545', '#28a745', '#6c757d'],
                borderColor: ['#fff', '#fff', '#fff'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { 
                    position: 'bottom',
                    labels: { color: '#FFFFFF', padding: 15 }
                }
            }
        }
    });
    </script>

<?php elseif ($active_tab == 'low_demand'): ?>
    <!-- Low-Demand Products Tab -->
    <div style="margin-bottom: 20px;">
        <p style="color: var(--text-gray);">
            Products with weekly average sales below <strong><?php echo $settings['low_demand_threshold'] ?? 10; ?> units</strong> 
            (analyzed over <?php echo $settings['trend_period_weeks'] ?? 4; ?> weeks)
        </p>
    </div>
    
    <?php if (empty($low_demand_products)): ?>
        <div style="padding: 40px; text-align: center; color: var(--text-gray);">
            <p>✅ No low-demand products detected. All products are performing well!</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Weekly Avg</th>
                        <th>Total Units</th>
                        <th>Revenue</th>
                        <th>Profit Margin</th>
                        <th>Trend</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($low_demand_products as $product): ?>
                        <tr>
                            <td style="font-weight: 600; color: var(--primary-white);">
                                <?php echo htmlspecialchars($product['item_name']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?></td>
                            <td><?php echo formatCurrency($product['price']); ?></td>
                            <td>
                                <span class="badge badge-danger"><?php echo $product['weekly_avg']; ?> units/week</span>
                            </td>
                            <td><?php echo number_format($product['units_sold']); ?></td>
                            <td><?php echo formatCurrency($product['revenue']); ?></td>
                            <td>
                                <span style="color: <?php echo $product['profit_margin'] < 20 ? '#dc3545' : ($product['profit_margin'] < 30 ? '#ffc107' : '#28a745'); ?>; font-weight: 600;">
                                    <?php echo number_format($product['profit_margin'], 1); ?>%
                                </span>
                            </td>
                            <td>
                                <?php if ($product['trend']['direction'] == 'down'): ?>
                                    <span class="badge badge-danger">↓ <?php echo $product['trend']['percentage']; ?>%</span>
                                <?php elseif ($product['trend']['direction'] == 'up'): ?>
                                    <span class="badge badge-success">↑ <?php echo $product['trend']['percentage']; ?>%</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">→ Stable</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?tab=details&item_id=<?php echo $product['item_id']; ?>" class="btn btn-primary btn-sm">View Details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php elseif ($active_tab == 'high_demand'): ?>
    <!-- High-Demand Products Tab -->
    <div style="margin-bottom: 20px;">
        <p style="color: var(--text-gray);">
            Products with weekly average sales above <strong><?php echo $settings['high_demand_threshold'] ?? 50; ?> units</strong> 
            (analyzed over <?php echo $settings['trend_period_weeks'] ?? 4; ?> weeks)
        </p>
    </div>
    
    <?php if (empty($high_demand_products)): ?>
        <div style="padding: 40px; text-align: center; color: var(--text-gray);">
            <p>No high-demand products detected yet. Keep monitoring your sales!</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Weekly Avg</th>
                        <th>Total Units</th>
                        <th>Revenue</th>
                        <th>Profit Margin</th>
                        <th>Trend</th>
                        <th>Stock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($high_demand_products as $product): ?>
                        <tr>
                            <td style="font-weight: 600; color: var(--primary-white);">
                                <?php echo htmlspecialchars($product['item_name']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?></td>
                            <td><?php echo formatCurrency($product['price']); ?></td>
                            <td>
                                <span class="badge badge-success"><?php echo $product['weekly_avg']; ?> units/week</span>
                            </td>
                            <td><?php echo number_format($product['units_sold']); ?></td>
                            <td><?php echo formatCurrency($product['revenue']); ?></td>
                            <td>
                                <span style="color: <?php echo $product['profit_margin'] < 20 ? '#dc3545' : ($product['profit_margin'] < 30 ? '#ffc107' : '#28a745'); ?>; font-weight: 600;">
                                    <?php echo number_format($product['profit_margin'], 1); ?>%
                                </span>
                            </td>
                            <td>
                                <?php if ($product['trend']['direction'] == 'up'): ?>
                                    <span class="badge badge-success">↑ <?php echo $product['trend']['percentage']; ?>%</span>
                                <?php elseif ($product['trend']['direction'] == 'down'): ?>
                                    <span class="badge badge-danger">↓ <?php echo $product['trend']['percentage']; ?>%</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">→ Stable</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="color: <?php echo $product['stock'] < 20 ? '#dc3545' : '#28a745'; ?>; font-weight: 600;">
                                    <?php echo $product['stock']; ?>
                                </span>
                            </td>
                            <td>
                                <a href="?tab=details&item_id=<?php echo $product['item_id']; ?>" class="btn btn-primary btn-sm">View Details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php elseif ($active_tab == 'details' && $item_id > 0): ?>
    <!-- Product Details Tab -->
    <?php
    $stmt = $conn->prepare("
        SELECT mi.*, mc.category_name 
        FROM menu_items mi
        LEFT JOIN menu_categories mc ON mi.category_id = mc.category_id
        WHERE mi.item_id = ? AND mi.cafe_id = ?
    ");
    $stmt->execute([$item_id, $cafe_id]);
    $product_detail = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product_detail) {
        echo "<div class='alert alert-error'>Product not found.</div>";
    } else {
        $weeks = $settings['trend_period_weeks'] ?? 4;
        $period_start = date('Y-m-d', strtotime("-$weeks weeks monday"));
        $period_end = date('Y-m-d');
        
        $sales = calculateProductSales($conn, $cafe_id, $item_id, $period_start, $period_end);
        $profit_data = calculateProductProfit($conn, $item_id, $sales['units_sold'], $sales['revenue']);
        $trend = detectTrend($conn, $cafe_id, $item_id, $weeks);
        $weekly_sales = getWeeklySales($conn, $cafe_id, $item_id, $weeks);
        $monthly_sales = getMonthlySales($conn, $cafe_id, $item_id, 6);
        $peak_hours = getPeakHours($conn, $cafe_id, $item_id, $period_start, $period_end);
        
        // Get recommendations for this product
        $stmt = $conn->prepare("
            SELECT * FROM product_recommendations 
            WHERE cafe_id = ? AND item_id = ? AND status = 'pending'
            ORDER BY 
                CASE priority 
                    WHEN 'urgent' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'medium' THEN 3 
                    WHEN 'low' THEN 4 
                END
        ");
        $stmt->execute([$cafe_id, $item_id]);
        $product_recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    
    <div style="margin-bottom: 20px;">
        <a href="?tab=<?php echo in_array($item_id, array_column($low_demand_products, 'item_id')) ? 'low_demand' : 'high_demand'; ?>" class="btn btn-secondary">← Back</a>
    </div>
    
    <h3 style="color: var(--primary-white); margin-bottom: 20px;"><?php echo htmlspecialchars($product_detail['item_name']); ?></h3>
    
    <!-- Product Stats -->
    <div class="dashboard-grid" style="margin-bottom: 30px;">
        <div class="dashboard-card card-warning">
            <i class="fas fa-dollar-sign card-bg-icon"></i>
            <div class="card-header">
                <div class="card-title">Total Revenue</div>
            </div>
            <div class="card-value"><?php echo formatCurrency($sales['revenue']); ?></div>
        </div>
        <div class="dashboard-card card-success">
            <i class="fas fa-box card-bg-icon"></i>
            <div class="card-header">
                <div class="card-title">Units Sold</div>
            </div>
            <div class="card-value"><?php echo number_format($sales['units_sold']); ?></div>
        </div>
        <div class="dashboard-card card-primary">
            <i class="fas fa-money-bill-wave card-bg-icon"></i>
            <div class="card-header">
                <div class="card-title">Profit</div>
            </div>
            <div class="card-value"><?php echo formatCurrency($profit_data['profit']); ?></div>
        </div>
        <div class="dashboard-card card-info">
            <span class="card-status-badge <?php echo $profit_data['profit_margin'] < 20 ? 'status-danger' : ($profit_data['profit_margin'] < 30 ? 'status-warning' : 'status-available'); ?>">
                <i class="fas fa-<?php echo $profit_data['profit_margin'] < 20 ? 'exclamation-triangle' : ($profit_data['profit_margin'] < 30 ? 'exclamation-circle' : 'check-circle'); ?>"></i>
                <?php echo $profit_data['profit_margin'] < 20 ? 'Low' : ($profit_data['profit_margin'] < 30 ? 'Moderate' : 'Good'); ?>
            </span>
            <i class="fas fa-chart-pie card-bg-icon"></i>
            <div class="card-header">
                <div class="card-title">Profit Margin</div>
            </div>
            <div class="card-value" style="color: <?php echo $profit_data['profit_margin'] < 20 ? '#dc3545' : ($profit_data['profit_margin'] < 30 ? '#ffc107' : '#28a745'); ?>;">
                <?php echo number_format($profit_data['profit_margin'], 1); ?>%
            </div>
        </div>
    </div>
    
    <!-- Charts -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
        <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
            <h4 style="color: var(--primary-white); margin-bottom: 15px;">Weekly Sales Trend</h4>
            <canvas id="weeklyChart" style="max-height: 250px;"></canvas>
        </div>
        <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
            <h4 style="color: var(--primary-white); margin-bottom: 15px;">Peak Hours</h4>
            <canvas id="peakHoursChart" style="max-height: 250px;"></canvas>
        </div>
    </div>
    
    <!-- Monthly Sales -->
    <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px; margin-bottom: 30px;">
        <h4 style="color: var(--primary-white); margin-bottom: 15px;">Monthly Sales</h4>
        <canvas id="monthlyChart" style="max-height: 300px;"></canvas>
    </div>
    
    <!-- Recommendations -->
    <?php if (!empty($product_recommendations)): ?>
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">Recommendations for this Product</div>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Priority</th>
                        <th>Type</th>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Suggested Value</th>
                        <th>Impact</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($product_recommendations as $rec): ?>
                        <tr>
                            <td>
                                <span class="badge badge-<?php 
                                    echo $rec['priority'] == 'urgent' ? 'danger' : 
                                        ($rec['priority'] == 'high' ? 'warning' : 
                                        ($rec['priority'] == 'medium' ? 'info' : 'secondary')); 
                                ?>">
                                    <?php echo ucfirst($rec['priority']); ?>
                                </span>
                            </td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $rec['recommendation_type'])); ?></td>
                            <td style="font-weight: 600; color: var(--primary-white);"><?php echo htmlspecialchars($rec['title']); ?></td>
                            <td><?php echo htmlspecialchars($rec['description']); ?></td>
                            <td>
                                <?php if ($rec['suggested_value']): ?>
                                    <?php echo formatCurrency($rec['suggested_value']); ?>
                                <?php else: ?>
                                    <span style="color: var(--text-gray);">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 12px; color: var(--text-gray);"><?php echo htmlspecialchars($rec['estimated_impact']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    // Weekly Sales Chart
    const weeklyData = <?php echo json_encode($weekly_sales); ?>;
    const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
    new Chart(weeklyCtx, {
        type: 'line',
        data: {
            labels: weeklyData.map(d => d.week_label),
            datasets: [{
                label: 'Units Sold',
                data: weeklyData.map(d => d.units_sold),
                borderColor: '#FFFFFF',
                backgroundColor: 'rgba(255, 255, 255, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: '#FFFFFF' } }
            },
            scales: {
                x: { ticks: { color: '#FFFFFF', maxRotation: 45 }, grid: { color: 'rgba(255,255,255,0.1)' } },
                y: { ticks: { color: '#FFFFFF' }, grid: { color: 'rgba(255,255,255,0.1)' } }
            }
        }
    });
    
    // Peak Hours Chart
    const peakHours = <?php echo json_encode($peak_hours); ?>;
    const peakCtx = document.getElementById('peakHoursChart').getContext('2d');
    const hourLabels = Array.from({length: 24}, (_, i) => i + ':00');
    new Chart(peakCtx, {
        type: 'bar',
        data: {
            labels: hourLabels,
            datasets: [{
                label: 'Units Sold',
                data: peakHours,
                backgroundColor: 'rgba(255, 255, 255, 0.5)',
                borderColor: '#FFFFFF',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: '#FFFFFF' } }
            },
            scales: {
                x: { ticks: { color: '#FFFFFF', maxRotation: 45 }, grid: { color: 'rgba(255,255,255,0.1)' } },
                y: { ticks: { color: '#FFFFFF' }, grid: { color: 'rgba(255,255,255,0.1)' } }
            }
        }
    });
    
    // Monthly Sales Chart
    const monthlyData = <?php echo json_encode($monthly_sales); ?>;
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: monthlyData.map(d => d.month_label),
            datasets: [{
                label: 'Revenue',
                data: monthlyData.map(d => d.revenue),
                backgroundColor: 'rgba(40, 167, 69, 0.5)',
                borderColor: '#28a745',
                borderWidth: 2
            }, {
                label: 'Profit',
                data: monthlyData.map(d => d.profit),
                backgroundColor: 'rgba(255, 193, 7, 0.5)',
                borderColor: '#ffc107',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: '#FFFFFF' } }
            },
            scales: {
                x: { ticks: { color: '#FFFFFF' }, grid: { color: 'rgba(255,255,255,0.1)' } },
                y: { ticks: { color: '#FFFFFF' }, grid: { color: 'rgba(255,255,255,0.1)' } }
            }
        }
    });
    </script>
    
    <?php } ?>

<?php elseif ($active_tab == 'recommendations'): ?>
    <!-- Recommendations Tab -->
    <?php if (empty($all_recommendations)): ?>
        <div style="padding: 40px; text-align: center; color: var(--text-gray);">
            <p>No recommendations available at this time.</p>
            <p style="margin-top: 10px;">Recommendations are generated automatically when products are detected as low or high demand.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Priority</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Suggested Value</th>
                        <th>Impact</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_recommendations as $rec): ?>
                        <tr>
                            <td>
                                <span class="badge badge-<?php 
                                    echo $rec['priority'] == 'urgent' ? 'danger' : 
                                        ($rec['priority'] == 'high' ? 'warning' : 
                                        ($rec['priority'] == 'medium' ? 'info' : 'secondary')); 
                                ?>">
                                    <?php echo ucfirst($rec['priority']); ?>
                                </span>
                            </td>
                            <td style="font-weight: 600; color: var(--primary-white);">
                                <?php echo htmlspecialchars($rec['item_name']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($rec['category_name'] ?? 'Uncategorized'); ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $rec['recommendation_type'])); ?></td>
                            <td><?php echo htmlspecialchars($rec['title']); ?></td>
                            <td style="font-size: 12px; color: var(--text-gray);"><?php echo htmlspecialchars($rec['description']); ?></td>
                            <td>
                                <?php if ($rec['suggested_value']): ?>
                                    <?php echo formatCurrency($rec['suggested_value']); ?>
                                <?php else: ?>
                                    <span style="color: var(--text-gray);">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 12px; color: var(--text-gray);"><?php echo htmlspecialchars($rec['estimated_impact']); ?></td>
                            <td>
                                <a href="?tab=details&item_id=<?php echo $rec['item_id']; ?>" class="btn btn-primary btn-sm">View Product</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php elseif ($active_tab == 'simulator'): ?>
    <!-- Profit Simulator Tab -->
    <?php
    // Get all products for selection
    $stmt = $conn->prepare("
        SELECT mi.item_id, mi.item_name, mi.price, mc.category_name
        FROM menu_items mi
        LEFT JOIN menu_categories mc ON mi.category_id = mc.category_id
        WHERE mi.cafe_id = ?
        ORDER BY mi.item_name
    ");
    $stmt->execute([$cafe_id]);
    $all_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $simulation_result = null;
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simulate'])) {
        $sim_item_id = (int)$_POST['item_id'];
        $new_price = (float)$_POST['new_price'];
        $demand_change = (float)$_POST['demand_change'];
        
        $simulation_result = simulateProfitImpact($conn, $cafe_id, $sim_item_id, $new_price, $demand_change);
    }
    ?>
    
    <div class="form-container">
        <h3 style="color: var(--primary-white); margin-bottom: 20px;">Profit Impact Simulator</h3>
        <p style="color: var(--text-gray); margin-bottom: 20px;">
            Simulate the profit impact of changing a product's price. Adjust the new price and expected demand change to see projected results.
        </p>
        
        <form method="POST">
            <input type="hidden" name="simulate" value="1">
            
            <div class="form-group">
                <label for="item_id">Select Product *</label>
                <select id="item_id" name="item_id" required>
                    <option value="">-- Select Product --</option>
                    <?php foreach ($all_products as $prod): ?>
                        <option value="<?php echo $prod['item_id']; ?>" 
                                data-price="<?php echo $prod['price']; ?>"
                                <?php echo isset($_POST['item_id']) && $_POST['item_id'] == $prod['item_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($prod['item_name']); ?> - <?php echo formatCurrency($prod['price']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="new_price">New Price *</label>
                <input type="number" id="new_price" name="new_price" step="0.01" min="0" required
                       value="<?php echo isset($_POST['new_price']) ? $_POST['new_price'] : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="demand_change">Expected Demand Change (%) *</label>
                <input type="number" id="demand_change" name="demand_change" step="0.1" required
                       value="<?php echo isset($_POST['demand_change']) ? $_POST['demand_change'] : '0'; ?>">
                <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">
                    Positive value = increase in demand, Negative value = decrease in demand
                </p>
            </div>
            
            <button type="submit" class="btn btn-primary">Run Simulation</button>
        </form>
        
        <?php if ($simulation_result): ?>
            <div style="margin-top: 30px; background: var(--accent-gray); padding: 20px; border-radius: 8px;">
                <h4 style="color: var(--primary-white); margin-bottom: 20px;">Simulation Results</h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <h5 style="color: var(--primary-white); margin-bottom: 10px;">Current Scenario</h5>
                        <table class="table">
                            <tr>
                                <td>Price:</td>
                                <td><?php echo formatCurrency($simulation_result['current']['price']); ?></td>
                            </tr>
                            <tr>
                                <td>Units Sold:</td>
                                <td><?php echo number_format($simulation_result['current']['units']); ?></td>
                            </tr>
                            <tr>
                                <td>Revenue:</td>
                                <td><?php echo formatCurrency($simulation_result['current']['revenue']); ?></td>
                            </tr>
                            <tr>
                                <td>Profit:</td>
                                <td><?php echo formatCurrency($simulation_result['current']['profit']); ?></td>
                            </tr>
                            <tr>
                                <td>Profit Margin:</td>
                                <td><?php echo number_format($simulation_result['current']['profit_margin'], 2); ?>%</td>
                            </tr>
                        </table>
                    </div>
                    
                    <div>
                        <h5 style="color: var(--primary-white); margin-bottom: 10px;">Simulated Scenario</h5>
                        <table class="table">
                            <tr>
                                <td>Price:</td>
                                <td><?php echo formatCurrency($simulation_result['simulated']['price']); ?></td>
                            </tr>
                            <tr>
                                <td>Units Sold:</td>
                                <td><?php echo number_format($simulation_result['simulated']['units']); ?></td>
                            </tr>
                            <tr>
                                <td>Revenue:</td>
                                <td><?php echo formatCurrency($simulation_result['simulated']['revenue']); ?></td>
                            </tr>
                            <tr>
                                <td>Profit:</td>
                                <td><?php echo formatCurrency($simulation_result['simulated']['profit']); ?></td>
                            </tr>
                            <tr>
                                <td>Profit Margin:</td>
                                <td><?php echo number_format($simulation_result['simulated']['profit_margin'], 2); ?>%</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div style="background: var(--primary-black); padding: 15px; border-radius: 8px;">
                    <h5 style="color: var(--primary-white); margin-bottom: 10px;">Impact Analysis</h5>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <div style="color: var(--text-gray); font-size: 14px;">Revenue Change</div>
                            <div style="color: <?php echo $simulation_result['impact']['revenue_change'] >= 0 ? '#28a745' : '#dc3545'; ?>; font-size: 24px; font-weight: bold;">
                                <?php echo $simulation_result['impact']['revenue_change'] >= 0 ? '+' : ''; ?>
                                <?php echo formatCurrency($simulation_result['impact']['revenue_change']); ?>
                                (<?php echo $simulation_result['impact']['revenue_change_percent'] >= 0 ? '+' : ''; ?>
                                <?php echo number_format($simulation_result['impact']['revenue_change_percent'], 2); ?>%)
                            </div>
                        </div>
                        <div>
                            <div style="color: var(--text-gray); font-size: 14px;">Profit Change</div>
                            <div style="color: <?php echo $simulation_result['impact']['profit_change'] >= 0 ? '#28a745' : '#dc3545'; ?>; font-size: 24px; font-weight: bold;">
                                <?php echo $simulation_result['impact']['profit_change'] >= 0 ? '+' : ''; ?>
                                <?php echo formatCurrency($simulation_result['impact']['profit_change']); ?>
                                (<?php echo $simulation_result['impact']['profit_change_percent'] >= 0 ? '+' : ''; ?>
                                <?php echo number_format($simulation_result['impact']['profit_change_percent'], 2); ?>%)
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
    // Auto-fill current price when product is selected
    document.getElementById('item_id').addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        const price = selected.getAttribute('data-price');
        if (price) {
            document.getElementById('new_price').value = price;
        }
    });
    </script>

<?php elseif ($active_tab == 'settings'): ?>
    <!-- Settings Tab -->
    <div class="form-container">
        <h3 style="color: var(--primary-white); margin-bottom: 20px;">Analytics Settings</h3>
        
        <form method="POST">
            <input type="hidden" name="update_settings" value="1">
            
            <div class="form-group">
                <label for="low_demand_threshold">Low-Demand Threshold (units/week) *</label>
                <input type="number" id="low_demand_threshold" name="low_demand_threshold" 
                       value="<?php echo $settings['low_demand_threshold'] ?? 10; ?>" min="1" required>
                <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">
                    Products with weekly average sales below this threshold will be flagged as low-demand.
                </p>
            </div>
            
            <div class="form-group">
                <label for="high_demand_threshold">High-Demand Threshold (units/week) *</label>
                <input type="number" id="high_demand_threshold" name="high_demand_threshold" 
                       value="<?php echo $settings['high_demand_threshold'] ?? 50; ?>" min="1" required>
                <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">
                    Products with weekly average sales above this threshold will be flagged as high-demand.
                </p>
            </div>
            
            <div class="form-group">
                <label for="trend_period_weeks">Trend Analysis Period (weeks) *</label>
                <input type="number" id="trend_period_weeks" name="trend_period_weeks" 
                       value="<?php echo $settings['trend_period_weeks'] ?? 4; ?>" min="2" max="12" required>
                <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">
                    Number of weeks to analyze for trend detection (2-12 weeks recommended).
                </p>
            </div>
            
            <div class="form-group">
                <label for="profit_margin_warning">Profit Margin Warning (%) *</label>
                <input type="number" id="profit_margin_warning" name="profit_margin_warning" 
                       value="<?php echo $settings['profit_margin_warning'] ?? 20.00; ?>" step="0.01" min="0" max="100" required>
                <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">
                    Products with profit margin below this percentage will trigger warnings.
                </p>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="auto_recommendations_enabled" value="1" 
                           <?php echo (isset($settings['auto_recommendations_enabled']) && $settings['auto_recommendations_enabled']) ? 'checked' : ''; ?>>
                    Enable Auto-Recommendations
                </label>
                <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">
                    Automatically generate recommendations for low and high-demand products.
                </p>
            </div>
            
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

