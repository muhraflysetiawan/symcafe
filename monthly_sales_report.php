<?php
require_once 'config/config.php';
requireLogin();
requireCafeSetup();
requireRole(['owner']);

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

// Get café information for print header
$stmt = $conn->prepare("SELECT cafe_name, address, phone FROM cafes WHERE cafe_id = ?");
$stmt->execute([$cafe_id]);
$cafe = $stmt->fetch(PDO::FETCH_ASSOC);

// Get selected month (default to current month)
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$year = date('Y', strtotime($selected_month . '-01'));
$month = date('m', strtotime($selected_month . '-01'));

// Total monthly revenue
$stmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COUNT(*) as total_orders
    FROM orders 
    WHERE cafe_id = ? 
    AND YEAR(created_at) = ? 
    AND MONTH(created_at) = ?
    AND payment_status = 'paid'
");
$stmt->execute([$cafe_id, $year, $month]);
$monthly_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Gross income (before tax) - sum of subtotals
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(subtotal), 0) as gross_income
    FROM orders 
    WHERE cafe_id = ? 
    AND YEAR(created_at) = ? 
    AND MONTH(created_at) = ?
    AND payment_status = 'paid'
");
$stmt->execute([$cafe_id, $year, $month]);
$gross_income = $stmt->fetch(PDO::FETCH_ASSOC)['gross_income'];

// Total tax and discount
$stmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(tax), 0) as total_tax,
        COALESCE(SUM(discount), 0) as total_discount
    FROM orders 
    WHERE cafe_id = ? 
    AND YEAR(created_at) = ? 
    AND MONTH(created_at) = ?
    AND payment_status = 'paid'
");
$stmt->execute([$cafe_id, $year, $month]);
$tax_discount = $stmt->fetch(PDO::FETCH_ASSOC);
$net_revenue = $monthly_stats['total_revenue'] - $tax_discount['total_tax'];

// Monthly sales graph data (daily breakdown)
$stmt = $conn->prepare("
    SELECT 
        DAY(created_at) as day,
        SUM(total_amount) as daily_revenue,
        COUNT(*) as daily_orders
    FROM orders 
    WHERE cafe_id = ? 
    AND YEAR(created_at) = ? 
    AND MONTH(created_at) = ?
    AND payment_status = 'paid'
    GROUP BY DAY(created_at)
    ORDER BY day
");
$stmt->execute([$cafe_id, $year, $month]);
$daily_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare chart data
$days = [];
$revenues = [];
$orders = [];
for ($i = 1; $i <= date('t', strtotime($selected_month . '-01')); $i++) {
    $days[] = $i;
    $revenues[] = 0;
    $orders[] = 0;
}
foreach ($daily_sales as $sale) {
    $day_index = (int)$sale['day'] - 1;
    if ($day_index >= 0 && $day_index < count($revenues)) {
        $revenues[$day_index] = (float)$sale['daily_revenue'];
        $orders[$day_index] = (int)$sale['daily_orders'];
    }
}

// Top 10 best-selling products
$stmt = $conn->prepare("
    SELECT 
        mi.item_name,
        mi.price,
        SUM(oi.quantity) as total_quantity,
        SUM(oi.subtotal) as total_revenue
    FROM order_items oi
    JOIN menu_items mi ON oi.item_id = mi.item_id
    JOIN orders o ON oi.order_id = o.order_id
    WHERE o.cafe_id = ? 
    AND YEAR(o.created_at) = ? 
    AND MONTH(o.created_at) = ?
    AND o.payment_status = 'paid'
    GROUP BY mi.item_id, mi.item_name, mi.price
    ORDER BY total_quantity DESC
    LIMIT 10
");
$stmt->execute([$cafe_id, $year, $month]);
$top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sales by category
$stmt = $conn->prepare("
    SELECT 
        mc.category_name,
        COALESCE(SUM(oi.subtotal), 0) as category_revenue,
        COALESCE(SUM(oi.quantity), 0) as category_quantity,
        COUNT(DISTINCT o.order_id) as category_orders
    FROM menu_categories mc
    LEFT JOIN menu_items mi ON mc.category_id = mi.category_id
    LEFT JOIN order_items oi ON mi.item_id = oi.item_id
    LEFT JOIN orders o ON oi.order_id = o.order_id 
        AND o.cafe_id = ? 
        AND YEAR(o.created_at) = ? 
        AND MONTH(o.created_at) = ?
        AND o.payment_status = 'paid'
    WHERE mc.cafe_id = ?
    GROUP BY mc.category_id, mc.category_name
    HAVING category_revenue > 0
    ORDER BY category_revenue DESC
");
$stmt->execute([$cafe_id, $year, $month, $cafe_id]);
$category_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Monthly Sales Report';
include 'includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">Monthly Sales Report</h2>

<!-- Month Selector -->
<div class="form-container" style="margin-bottom: 24px;">
    <form method="GET" style="display: flex; gap: 15px; align-items: end;">
        <div class="form-group" style="margin-bottom: 0; flex: 1;">
            <label for="month">Select Month</label>
            <input type="month" id="month" name="month" value="<?php echo htmlspecialchars($selected_month); ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Generate Report</button>
        <button type="button" class="btn btn-secondary" onclick="printReport()">🖨️ Print Report</button>
    </form>
</div>

<!-- Print Area -->
<div id="printArea" style="display: none;">
    <div style="text-align: center; padding: 20px; border-bottom: 2px solid #000;">
        <h1 style="margin: 0; font-size: 24px; font-weight: bold;">MONTHLY SALES REPORT</h1>
        <p style="margin: 5px 0; font-size: 14px;"><?php echo htmlspecialchars($cafe['cafe_name'] ?? 'Café'); ?></p>
        <p style="margin: 5px 0; font-size: 12px;"><?php echo date('F Y', strtotime($selected_month . '-01')); ?></p>
        <p style="margin: 5px 0; font-size: 11px;">Printed: <?php echo date('d/m/Y H:i:s'); ?></p>
    </div>
    
    <div style="padding: 20px;">
        <!-- Summary Section -->
        <div style="margin-bottom: 30px;">
            <h2 style="font-size: 18px; border-bottom: 1px solid #000; padding-bottom: 5px; margin-bottom: 15px;">SUMMARY</h2>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Total Revenue:</strong></td>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd; text-align: right;"><?php echo formatCurrency($monthly_stats['total_revenue']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Total Orders:</strong></td>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd; text-align: right;"><?php echo number_format($monthly_stats['total_orders']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Gross Income:</strong></td>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd; text-align: right;"><?php echo formatCurrency($gross_income); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Total Tax:</strong></td>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd; text-align: right;"><?php echo formatCurrency($tax_discount['total_tax']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Total Discount:</strong></td>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd; text-align: right;"><?php echo formatCurrency($tax_discount['total_discount']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px;"><strong>Net Revenue:</strong></td>
                    <td style="padding: 8px; text-align: right; font-weight: bold;"><?php echo formatCurrency($net_revenue); ?></td>
                </tr>
            </table>
        </div>
        
        <!-- Chart Image -->
        <div style="margin-bottom: 30px; text-align: center;">
            <h2 style="font-size: 18px; border-bottom: 1px solid #000; padding-bottom: 5px; margin-bottom: 15px;">DAILY SALES TREND</h2>
            <img id="chartImage" src="" alt="Sales Chart" style="max-width: 100%; height: auto;">
        </div>
        
        <!-- Top Products -->
        <div style="margin-bottom: 30px;">
            <h2 style="font-size: 18px; border-bottom: 1px solid #000; padding-bottom: 5px; margin-bottom: 15px;">TOP 10 BEST-SELLING PRODUCTS</h2>
            <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                <thead>
                    <tr style="background: #f0f0f0;">
                        <th style="padding: 8px; border: 1px solid #000; text-align: left;">Rank</th>
                        <th style="padding: 8px; border: 1px solid #000; text-align: left;">Product Name</th>
                        <th style="padding: 8px; border: 1px solid #000; text-align: right;">Price</th>
                        <th style="padding: 8px; border: 1px solid #000; text-align: right;">Quantity</th>
                        <th style="padding: 8px; border: 1px solid #000; text-align: right;">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($top_products)): ?>
                        <tr>
                            <td colspan="5" style="padding: 8px; border: 1px solid #000; text-align: center;">No sales data for this month</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($top_products as $index => $product): ?>
                            <tr>
                                <td style="padding: 8px; border: 1px solid #000;">#<?php echo $index + 1; ?></td>
                                <td style="padding: 8px; border: 1px solid #000;"><?php echo htmlspecialchars($product['item_name']); ?></td>
                                <td style="padding: 8px; border: 1px solid #000; text-align: right;"><?php echo formatCurrency($product['price']); ?></td>
                                <td style="padding: 8px; border: 1px solid #000; text-align: right;"><?php echo number_format($product['total_quantity']); ?></td>
                                <td style="padding: 8px; border: 1px solid #000; text-align: right;"><?php echo formatCurrency($product['total_revenue']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Sales by Category -->
        <div>
            <h2 style="font-size: 18px; border-bottom: 1px solid #000; padding-bottom: 5px; margin-bottom: 15px;">SALES BY CATEGORY</h2>
            <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                <thead>
                    <tr style="background: #f0f0f0;">
                        <th style="padding: 8px; border: 1px solid #000; text-align: left;">Category</th>
                        <th style="padding: 8px; border: 1px solid #000; text-align: right;">Orders</th>
                        <th style="padding: 8px; border: 1px solid #000; text-align: right;">Quantity</th>
                        <th style="padding: 8px; border: 1px solid #000; text-align: right;">Revenue</th>
                        <th style="padding: 8px; border: 1px solid #000; text-align: right;">Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($category_sales)): ?>
                        <tr>
                            <td colspan="5" style="padding: 8px; border: 1px solid #000; text-align: center;">No category sales data for this month</td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $total_category_revenue = array_sum(array_column($category_sales, 'category_revenue'));
                        foreach ($category_sales as $category): 
                            $percentage = $total_category_revenue > 0 ? ($category['category_revenue'] / $total_category_revenue) * 100 : 0;
                        ?>
                            <tr>
                                <td style="padding: 8px; border: 1px solid #000;"><?php echo htmlspecialchars($category['category_name']); ?></td>
                                <td style="padding: 8px; border: 1px solid #000; text-align: right;"><?php echo number_format($category['category_orders']); ?></td>
                                <td style="padding: 8px; border: 1px solid #000; text-align: right;"><?php echo number_format($category['category_quantity']); ?></td>
                                <td style="padding: 8px; border: 1px solid #000; text-align: right;"><?php echo formatCurrency($category['category_revenue']); ?></td>
                                <td style="padding: 8px; border: 1px solid #000; text-align: right;"><?php echo number_format($percentage, 1); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div style="text-align: center; padding: 20px; border-top: 2px solid #000; margin-top: 30px;">
        <p style="margin: 0; font-size: 11px;">End of Report</p>
    </div>
</div>

<!-- Summary Cards -->
<div class="dashboard-grid" style="margin-bottom: 32px;">
    <div class="dashboard-card card-warning">
        <i class="fas fa-dollar-sign card-bg-icon"></i>
        <div class="card-header">
            <div class="card-title">Total Revenue</div>
        </div>
        <div class="card-value"><?php echo formatCurrency($monthly_stats['total_revenue']); ?></div>
        <div class="card-subtitle">Net revenue for <?php echo date('F Y', strtotime($selected_month . '-01')); ?></div>
    </div>
    
    <div class="dashboard-card card-success">
        <i class="fas fa-clipboard-list card-bg-icon"></i>
        <div class="card-header">
            <div class="card-title">Total Orders</div>
        </div>
        <div class="card-value"><?php echo number_format($monthly_stats['total_orders']); ?></div>
        <div class="card-subtitle">Completed orders this month</div>
    </div>
    
    <div class="dashboard-card card-info">
        <i class="fas fa-chart-bar card-bg-icon"></i>
        <div class="card-header">
            <div class="card-title">Gross Income</div>
        </div>
        <div class="card-value"><?php echo formatCurrency($gross_income); ?></div>
        <div class="card-subtitle">Before tax and discounts</div>
    </div>
    
    <div class="dashboard-card card-primary">
        <i class="fas fa-money-bill-wave card-bg-icon"></i>
        <div class="card-header">
            <div class="card-title">Net Revenue</div>
        </div>
        <div class="card-value"><?php echo formatCurrency($net_revenue); ?></div>
        <div class="card-subtitle">After tax (<?php echo formatCurrency($tax_discount['total_tax']); ?> tax)</div>
    </div>
</div>

<!-- Monthly Sales Graph -->
<div class="table-container" style="margin-bottom: 32px;">
    <div class="table-header">
        <div class="table-title">Daily Sales Trend - <?php echo date('F Y', strtotime($selected_month . '-01')); ?></div>
    </div>
    <div style="padding: 24px;">
        <canvas id="salesChart" style="max-height: 400px;"></canvas>
    </div>
</div>

<!-- Top 10 Best-Selling Products -->
<div class="table-container" style="margin-bottom: 32px;">
    <div class="table-header">
        <div class="table-title">Top 10 Best-Selling Products</div>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>Rank</th>
                <th>Product Name</th>
                <th>Price</th>
                <th>Quantity Sold</th>
                <th>Total Revenue</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($top_products)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: var(--text-gray);">No sales data for this month</td>
                </tr>
            <?php else: ?>
                <?php foreach ($top_products as $index => $product): ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--primary-white);">#<?php echo $index + 1; ?></td>
                        <td style="font-weight: 600; color: var(--primary-white);"><?php echo htmlspecialchars($product['item_name']); ?></td>
                        <td><?php echo formatCurrency($product['price']); ?></td>
                        <td><span class="badge badge-info"><?php echo number_format($product['total_quantity']); ?> items</span></td>
                        <td style="font-weight: 600; color: var(--primary-white);"><?php echo formatCurrency($product['total_revenue']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Sales by Category -->
<div class="table-container">
    <div class="table-header">
        <div class="table-title">Sales by Category</div>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>Category</th>
                <th>Orders</th>
                <th>Quantity Sold</th>
                <th>Revenue</th>
                <th>Percentage</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($category_sales)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: var(--text-gray);">No category sales data for this month</td>
                </tr>
            <?php else: ?>
                <?php 
                $total_category_revenue = array_sum(array_column($category_sales, 'category_revenue'));
                foreach ($category_sales as $category): 
                    $percentage = $total_category_revenue > 0 ? ($category['category_revenue'] / $total_category_revenue) * 100 : 0;
                ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--primary-white);"><?php echo htmlspecialchars($category['category_name']); ?></td>
                        <td><?php echo number_format($category['category_orders']); ?></td>
                        <td><span class="badge badge-info"><?php echo number_format($category['category_quantity']); ?> items</span></td>
                        <td style="font-weight: 600; color: var(--primary-white);"><?php echo formatCurrency($category['category_revenue']); ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="flex: 1; background: var(--border-gray); height: 8px; border-radius: 4px; overflow: hidden;">
                                    <div style="background: var(--primary-white); height: 100%; width: <?php echo $percentage; ?>%; transition: width 0.3s ease;"></div>
                                </div>
                                <span style="min-width: 50px; text-align: right; font-weight: 600;"><?php echo number_format($percentage, 1); ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// Sales Chart
const ctx = document.getElementById('salesChart').getContext('2d');
const salesChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($days); ?>,
        datasets: [
            {
                label: 'Daily Revenue (Rp)',
                data: <?php echo json_encode($revenues); ?>,
                borderColor: '#FFFFFF',
                backgroundColor: 'rgba(255, 255, 255, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                yAxisID: 'y'
            },
            {
                label: 'Daily Orders',
                data: <?php echo json_encode($orders); ?>,
                borderColor: '#CCCCCC',
                backgroundColor: 'rgba(204, 204, 204, 0.1)',
                borderWidth: 2,
                fill: false,
                tension: 0.4,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                labels: {
                    color: '#CCCCCC',
                    font: {
                        size: 14
                    }
                }
            },
            tooltip: {
                backgroundColor: 'rgba(30, 41, 59, 0.95)',
                titleColor: '#FFFFFF',
                bodyColor: '#CCCCCC',
                borderColor: '#404040',
                borderWidth: 1,
                padding: 12,
                callbacks: {
                    label: function(context) {
                        if (context.datasetIndex === 0) {
                            return 'Revenue: Rp ' + context.parsed.y.toLocaleString('id-ID');
                        } else {
                            return 'Orders: ' + context.parsed.y;
                        }
                    }
                }
            }
        },
        scales: {
            x: {
                ticks: {
                    color: '#CCCCCC'
                },
                grid: {
                    color: 'rgba(255, 255, 255, 0.05)'
                }
            },
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                ticks: {
                    color: '#FFFFFF',
                    callback: function(value) {
                        return 'Rp ' + value.toLocaleString('id-ID');
                    }
                },
                grid: {
                    color: 'rgba(255, 255, 255, 0.05)'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                ticks: {
                    color: '#CCCCCC'
                },
                grid: {
                    drawOnChartArea: false
                }
            }
        }
    }
});

// Print Report Function
function printReport() {
    // Get original chart dimensions from the rendered chart
    const originalCanvas = document.getElementById('salesChart');
    // Get the actual rendered size of the chart
    const chartWidth = originalCanvas.width || 800;
    const chartHeight = originalCanvas.height || 400;
    
    // Create a print-friendly chart with darker colors
    const printCanvas = document.createElement('canvas');
    printCanvas.id = 'printChart';
    printCanvas.width = chartWidth;
    printCanvas.height = chartHeight;
    printCanvas.style.display = 'none';
    document.body.appendChild(printCanvas);
    
    const printCtx = printCanvas.getContext('2d');
    
    // Get original chart data
    const originalData = salesChart.data;
    
    // Create print chart with darker colors
    const printChart = new Chart(printCtx, {
        type: 'line',
        data: {
            labels: originalData.labels,
            datasets: [
                {
                    label: 'Daily Revenue (Rp)',
                    data: originalData.datasets[0].data,
                    borderColor: '#000000', // Black for revenue line
                    backgroundColor: 'rgba(0, 0, 0, 0.1)', // Light black fill
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Daily Orders',
                    data: originalData.datasets[1].data,
                    borderColor: '#333333', // Dark gray for orders line
                    backgroundColor: 'rgba(51, 51, 51, 0.1)', // Light gray fill
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: false,
            maintainAspectRatio: false,
            aspectRatio: chartHeight / chartWidth,
            plugins: {
                legend: {
                    labels: {
                        color: '#000000', // Black text
                        font: {
                            size: 14
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#000000',
                    bodyColor: '#000000',
                    borderColor: '#000000',
                    borderWidth: 1,
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            if (context.datasetIndex === 0) {
                                return 'Revenue: Rp ' + context.parsed.y.toLocaleString('id-ID');
                            } else {
                                return 'Orders: ' + context.parsed.y;
                            }
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        color: '#000000' // Black text
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.2)' // Dark grid lines
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    ticks: {
                        color: '#000000', // Black text
                        callback: function(value) {
                            return 'Rp ' + value.toLocaleString('id-ID');
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.2)' // Dark grid lines
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    ticks: {
                        color: '#000000' // Black text
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
    
    // Wait for chart to render, then convert to image
    setTimeout(() => {
        const chartImage = printCanvas.toDataURL('image/png');
        document.getElementById('chartImage').src = chartImage;
        
        // Destroy print chart
        printChart.destroy();
        document.body.removeChild(printCanvas);
        
        // Show print area
        const printArea = document.getElementById('printArea');
        const originalDisplay = printArea.style.display;
        printArea.style.display = 'block';
        
        // Wait a bit for image to load, then print
        setTimeout(() => {
            window.print();
            // Restore original display after printing
            setTimeout(() => {
                printArea.style.display = originalDisplay;
            }, 100);
        }, 500);
    }, 1000);
}
</script>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    
    #printArea, #printArea * {
        visibility: visible;
    }
    
    #printArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        background: white;
        color: black;
    }
    
    .dashboard-grid,
    .table-container:not(#printArea .table-container),
    .form-container,
    .sidebar,
    .top-nav,
    .btn,
    h2:not(#printArea h2) {
        display: none !important;
    }
    
    #printArea table {
        page-break-inside: avoid;
    }
    
    #printArea h2 {
        page-break-after: avoid;
    }
}

@media screen {
    #printArea {
        display: none;
    }
}
</style>

<?php include 'includes/footer.php'; ?>

