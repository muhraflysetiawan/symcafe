<?php
require_once 'config/config.php';
requireLogin();
requireCafeSetup();
requireRole(['owner']);

require_once 'config/functions_inventory.php';

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

// Get date range
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'); // Last day of current month

// Calculate menu engineering data for all products
try {
    // Get all products with sales data
    $stmt = $conn->prepare("
        SELECT 
            mi.item_id,
            mi.item_name,
            mi.price,
            COALESCE(pp.ingredient_cost, 0) as ingredient_cost,
            COALESCE(SUM(oi.quantity), 0) as total_sales,
            COALESCE(SUM(oi.subtotal), 0) as total_revenue,
            COALESCE(SUM(oi.quantity * COALESCE(pp.ingredient_cost, 0)), 0) as total_cost,
            COALESCE(SUM(oi.subtotal - (oi.quantity * COALESCE(pp.ingredient_cost, 0))), 0) as contribution_margin
        FROM menu_items mi
        LEFT JOIN product_pricing pp ON mi.item_id = pp.item_id
        LEFT JOIN order_items oi ON mi.item_id = oi.item_id
        LEFT JOIN orders o ON oi.order_id = o.order_id
        WHERE mi.cafe_id = ?
        AND (o.created_at IS NULL OR (DATE(o.created_at) BETWEEN ? AND ?))
        GROUP BY mi.item_id, mi.item_name, mi.price, pp.ingredient_cost
        ORDER BY total_sales DESC
    ");
    $stmt->execute([$cafe_id, $start_date, $end_date]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate averages for quadrant classification
    $total_products = count($products);
    if ($total_products > 0) {
        $avg_popularity = array_sum(array_column($products, 'total_sales')) / $total_products;
        $avg_profitability = array_sum(array_column($products, 'contribution_margin')) / $total_products;
    } else {
        $avg_popularity = 0;
        $avg_profitability = 0;
    }
    
    // Classify products into quadrants
    foreach ($products as &$product) {
        $is_popular = $product['total_sales'] >= $avg_popularity;
        $is_profitable = $product['contribution_margin'] >= $avg_profitability;
        
        if ($is_popular && $is_profitable) {
            $product['quadrant'] = 'star';
            $product['recommendation'] = 'Keep and promote';
        } elseif ($is_popular && !$is_profitable) {
            $product['quadrant'] = 'plowhorse';
            $product['recommendation'] = 'Increase price or reduce cost';
        } elseif (!$is_popular && $is_profitable) {
            $product['quadrant'] = 'puzzle';
            $product['recommendation'] = 'Promote more';
        } else {
            $product['quadrant'] = 'dog';
            $product['recommendation'] = 'Consider removing or redesigning';
        }
    }
    
    // Group by quadrant
    $quadrants = [
        'star' => [],
        'plowhorse' => [],
        'puzzle' => [],
        'dog' => []
    ];
    
    foreach ($products as $product) {
        $quadrants[$product['quadrant']][] = $product;
    }
    
} catch (Exception $e) {
    $products = [];
    $quadrants = ['star' => [], 'plowhorse' => [], 'puzzle' => [], 'dog' => []];
    $avg_popularity = 0;
    $avg_profitability = 0;
    error_log("Error calculating menu engineering data: " . $e->getMessage());
}

$page_title = 'Menu Engineering';
include 'includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">Menu Engineering Dashboard</h2>

<div class="form-container" style="margin-bottom: 30px;">
    <form method="GET" style="display: flex; gap: 15px; align-items: end;">
        <div class="form-group" style="flex: 1;">
            <label for="start_date">Start Date</label>
            <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" required>
        </div>
        <div class="form-group" style="flex: 1;">
            <label for="end_date">End Date</label>
            <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" required>
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary">Update Analysis</button>
        </div>
    </form>
</div>

<!-- Menu Engineering Matrix -->
<div style="margin-bottom: 40px;">
    <h3 style="color: var(--primary-white); margin-bottom: 20px;">Menu Engineering Matrix</h3>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; max-width: 1000px; margin: 0 auto;">
        <!-- High Profitability -->
        <div>
            <div style="text-align: center; color: var(--primary-white); font-weight: 600; margin-bottom: 10px;">High Profitability</div>
            
            <!-- Puzzle (High Profit, Low Popularity) -->
            <div style="background: rgba(255, 193, 7, 0.1); border: 2px solid #ffc107; padding: 15px; border-radius: 8px; margin-bottom: 15px; min-height: 200px;">
                <h4 style="color: #ffc107; margin-bottom: 10px;">🔍 Puzzle</h4>
                <p style="color: var(--text-gray); font-size: 12px; margin-bottom: 15px;">High Profit, Low Popularity</p>
                <?php if (empty($quadrants['puzzle'])): ?>
                    <p style="color: var(--text-gray); font-size: 12px;">No products in this category</p>
                <?php else: ?>
                    <?php foreach ($quadrants['puzzle'] as $item): ?>
                        <div style="background: var(--accent-gray); padding: 10px; border-radius: 5px; margin-bottom: 8px;">
                            <div style="color: var(--primary-white); font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($item['item_name']); ?></div>
                            <div style="color: var(--text-gray); font-size: 11px; margin-top: 3px;">
                                Sales: <?php echo $item['total_sales']; ?> | 
                                Margin: <?php echo formatCurrency($item['contribution_margin']); ?>
                            </div>
                            <div style="color: #ffc107; font-size: 11px; margin-top: 3px; font-weight: 600;">
                                💡 <?php echo $item['recommendation']; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Star (High Profit, High Popularity) -->
            <div style="background: rgba(40, 167, 69, 0.1); border: 2px solid #28a745; padding: 15px; border-radius: 8px; min-height: 200px;">
                <h4 style="color: #28a745; margin-bottom: 10px;">⭐ Star</h4>
                <p style="color: var(--text-gray); font-size: 12px; margin-bottom: 15px;">High Profit, High Popularity</p>
                <?php if (empty($quadrants['star'])): ?>
                    <p style="color: var(--text-gray); font-size: 12px;">No products in this category</p>
                <?php else: ?>
                    <?php foreach ($quadrants['star'] as $item): ?>
                        <div style="background: var(--accent-gray); padding: 10px; border-radius: 5px; margin-bottom: 8px;">
                            <div style="color: var(--primary-white); font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($item['item_name']); ?></div>
                            <div style="color: var(--text-gray); font-size: 11px; margin-top: 3px;">
                                Sales: <?php echo $item['total_sales']; ?> | 
                                Margin: <?php echo formatCurrency($item['contribution_margin']); ?>
                            </div>
                            <div style="color: #28a745; font-size: 11px; margin-top: 3px; font-weight: 600;">
                                ✅ <?php echo $item['recommendation']; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Low Profitability -->
        <div>
            <div style="text-align: center; color: var(--primary-white); font-weight: 600; margin-bottom: 10px;">Low Profitability</div>
            
            <!-- Dog (Low Profit, Low Popularity) -->
            <div style="background: rgba(220, 53, 69, 0.1); border: 2px solid #dc3545; padding: 15px; border-radius: 8px; margin-bottom: 15px; min-height: 200px;">
                <h4 style="color: #dc3545; margin-bottom: 10px;">🐕 Dog</h4>
                <p style="color: var(--text-gray); font-size: 12px; margin-bottom: 15px;">Low Profit, Low Popularity</p>
                <?php if (empty($quadrants['dog'])): ?>
                    <p style="color: var(--text-gray); font-size: 12px;">No products in this category</p>
                <?php else: ?>
                    <?php foreach ($quadrants['dog'] as $item): ?>
                        <div style="background: var(--accent-gray); padding: 10px; border-radius: 5px; margin-bottom: 8px;">
                            <div style="color: var(--primary-white); font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($item['item_name']); ?></div>
                            <div style="color: var(--text-gray); font-size: 11px; margin-top: 3px;">
                                Sales: <?php echo $item['total_sales']; ?> | 
                                Margin: <?php echo formatCurrency($item['contribution_margin']); ?>
                            </div>
                            <div style="color: #dc3545; font-size: 11px; margin-top: 3px; font-weight: 600;">
                                ⚠️ <?php echo $item['recommendation']; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Plowhorse (Low Profit, High Popularity) -->
            <div style="background: rgba(108, 117, 125, 0.1); border: 2px solid #6c757d; padding: 15px; border-radius: 8px; min-height: 200px;">
                <h4 style="color: #6c757d; margin-bottom: 10px;">🐴 Plowhorse</h4>
                <p style="color: var(--text-gray); font-size: 12px; margin-bottom: 15px;">Low Profit, High Popularity</p>
                <?php if (empty($quadrants['plowhorse'])): ?>
                    <p style="color: var(--text-gray); font-size: 12px;">No products in this category</p>
                <?php else: ?>
                    <?php foreach ($quadrants['plowhorse'] as $item): ?>
                        <div style="background: var(--accent-gray); padding: 10px; border-radius: 5px; margin-bottom: 8px;">
                            <div style="color: var(--primary-white); font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($item['item_name']); ?></div>
                            <div style="color: var(--text-gray); font-size: 11px; margin-top: 3px;">
                                Sales: <?php echo $item['total_sales']; ?> | 
                                Margin: <?php echo formatCurrency($item['contribution_margin']); ?>
                            </div>
                            <div style="color: #6c757d; font-size: 11px; margin-top: 3px; font-weight: 600;">
                                💰 <?php echo $item['recommendation']; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div style="text-align: center; margin-top: 20px; color: var(--text-gray); font-size: 12px;">
        <div style="margin-bottom: 10px;">
            <strong>High Popularity</strong> ← → <strong>Low Popularity</strong>
        </div>
        <div>Average Popularity: <?php echo number_format($avg_popularity, 1); ?> sales | Average Profitability: <?php echo formatCurrency($avg_profitability); ?></div>
    </div>
</div>

<!-- Detailed Product List -->
<div class="table-container">
    <div class="table-header">
        <div class="table-title">Product Performance Details</div>
    </div>
    
    <table class="table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Total Sales</th>
                <th>Total Revenue</th>
                <th>Total Cost</th>
                <th>Contribution Margin</th>
                <th>Quadrant</th>
                <th>Recommendation</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td style="font-weight: 600; color: var(--primary-white);"><?php echo htmlspecialchars($product['item_name']); ?></td>
                    <td><?php echo $product['total_sales']; ?></td>
                    <td><?php echo formatCurrency($product['total_revenue']); ?></td>
                    <td><?php echo formatCurrency($product['total_cost']); ?></td>
                    <td style="color: <?php echo $product['contribution_margin'] >= 0 ? '#28a745' : '#dc3545'; ?>; font-weight: 600;">
                        <?php echo formatCurrency($product['contribution_margin']); ?>
                    </td>
                    <td>
                        <?php
                        $badge_colors = [
                            'star' => 'success',
                            'plowhorse' => 'secondary',
                            'puzzle' => 'warning',
                            'dog' => 'danger'
                        ];
                        $badge_labels = [
                            'star' => '⭐ Star',
                            'plowhorse' => '🐴 Plowhorse',
                            'puzzle' => '🔍 Puzzle',
                            'dog' => '🐕 Dog'
                        ];
                        ?>
                        <span class="badge badge-<?php echo $badge_colors[$product['quadrant']]; ?>">
                            <?php echo $badge_labels[$product['quadrant']]; ?>
                        </span>
                    </td>
                    <td style="font-size: 12px; color: var(--text-gray);"><?php echo $product['recommendation']; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>

