<?php
require_once 'config/config.php';
requireLogin();
requireCafeSetup();

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

// Get café info
$stmt = $conn->prepare("SELECT * FROM cafes WHERE cafe_id = ?");
$stmt->execute([$cafe_id]);
$cafe = $stmt->fetch(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM menu_items WHERE cafe_id = ?");
$stmt->execute([$cafe_id]);
$total_products = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE cafe_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$cafe_id]);
$today_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT SUM(total_amount) as total FROM orders WHERE cafe_id = ? AND DATE(created_at) = CURDATE() AND payment_status = 'paid'");
$stmt->execute([$cafe_id]);
$today_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE cafe_id = ? AND role = 'cashier'");
$stmt->execute([$cafe_id]);
$total_cashiers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get favorite products (most purchased)
$stmt = $conn->prepare("
    SELECT 
        mi.item_id,
        mi.item_name,
        mi.price,
        COALESCE(SUM(oi.quantity), 0) as total_purchased
    FROM menu_items mi
    LEFT JOIN order_items oi ON mi.item_id = oi.item_id
    LEFT JOIN orders o ON oi.order_id = o.order_id AND o.cafe_id = ?
    WHERE mi.cafe_id = ?
    GROUP BY mi.item_id, mi.item_name, mi.price
    HAVING total_purchased > 0
    ORDER BY total_purchased DESC
    LIMIT 5
");
$stmt->execute([$cafe_id, $cafe_id]);
$favorite_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available vouchers for cashiers
$available_vouchers = [];
try {
    $stmt = $conn->prepare("
        SELECT voucher_code, discount_amount, min_order_amount, max_order_amount, valid_until, usage_limit, used_count
        FROM vouchers 
        WHERE cafe_id = ? AND is_active = 1 
        AND (valid_from IS NULL OR valid_from <= CURDATE())
        AND (valid_until IS NULL OR valid_until >= CURDATE())
        AND (usage_limit IS NULL OR used_count < usage_limit)
        ORDER BY discount_amount DESC
        LIMIT 5
    ");
    $stmt->execute([$cafe_id]);
    $available_vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist
    $available_vouchers = [];
}

$page_title = 'Dashboard';
include 'includes/header.php';

// Get active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">Dashboard</h2>

<!-- Dashboard Tabs -->
<div class="dashboard-tabs-container">
    <div class="dashboard-tabs">
        <a href="?tab=overview" class="dashboard-tab <?php echo $active_tab == 'overview' ? 'active' : ''; ?>">
            <i class="fas fa-chart-pie"></i>
            <span>Overview</span>
        </a>
        <?php if ($_SESSION['user_role'] == 'owner'): ?>
        <a href="?tab=recipes" class="dashboard-tab <?php echo $active_tab == 'recipes' ? 'active' : ''; ?>">
            <i class="fas fa-book-open"></i>
            <span>Recipes</span>
        </a>
        <a href="?tab=pricing" class="dashboard-tab <?php echo $active_tab == 'pricing' ? 'active' : ''; ?>">
            <i class="fas fa-tags"></i>
            <span>Smart Pricing</span>
        </a>
        <a href="?tab=engineering" class="dashboard-tab <?php echo $active_tab == 'engineering' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Menu Engineering</span>
        </a>
        <a href="?tab=sales" class="dashboard-tab <?php echo $active_tab == 'sales' ? 'active' : ''; ?>">
            <i class="fas fa-file-invoice-dollar"></i>
            <span>Sales Reports</span>
        </a>
        <a href="?tab=settings" class="dashboard-tab <?php echo $active_tab == 'settings' ? 'active' : ''; ?>">
            <i class="fas fa-sliders-h"></i>
            <span>Settings</span>
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($active_tab == 'overview'): ?>

<div class="dashboard-grid">
    <div class="dashboard-card card-primary">
        <i class="fas fa-coffee card-bg-icon"></i>
        <div class="card-header">
            <div class="card-title">Products</div>
        </div>
        <div class="card-value"><?php echo $total_products; ?></div>
        <div class="card-subtitle">Menu items in your café</div>
    </div>
    
    <div class="dashboard-card card-success">
        <i class="fas fa-clipboard-list card-bg-icon"></i>
        <div class="card-header">
            <div class="card-title">Today's Orders</div>
        </div>
        <div class="card-value"><?php echo $today_orders; ?></div>
        <div class="card-subtitle">Orders processed today</div>
    </div>
    
    <div class="dashboard-card card-warning">
        <i class="fas fa-dollar-sign card-bg-icon"></i>
        <div class="card-header">
            <div class="card-title">Today's Revenue</div>
        </div>
        <div class="card-value"><?php echo formatCurrency($today_revenue); ?></div>
        <div class="card-subtitle">Total sales today</div>
    </div>
    
    <?php if ($_SESSION['user_role'] == 'owner'): ?>
    <div class="dashboard-card card-danger">
        <i class="fas fa-users card-bg-icon"></i>
        <div class="card-header">
            <div class="card-title">Cashiers</div>
        </div>
        <div class="card-value"><?php echo $total_cashiers; ?></div>
        <div class="card-subtitle">Active cashier accounts</div>
    </div>
    <?php endif; ?>
</div>

<div class="table-container" style="margin-top: 30px;">
    <div class="table-header">
        <div class="table-title">Café Information</div>
        <?php if ($_SESSION['user_role'] == 'owner'): ?>
            <a href="forms/cafe_edit.php" class="btn btn-primary btn-sm">Edit Information</a>
        <?php endif; ?>
    </div>
    <table class="table">
        <tbody>
            <tr>
                <td style="font-weight: 600; color: var(--primary-white);">Café Name</td>
                <td><?php echo htmlspecialchars($cafe['cafe_name']); ?></td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--primary-white);">Address</td>
                <td><?php echo !empty($cafe['address']) ? htmlspecialchars($cafe['address']) : 'Not set'; ?></td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--primary-white);">Description</td>
                <td><?php echo !empty($cafe['description']) ? htmlspecialchars($cafe['description']) : 'Not set'; ?></td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--primary-white);">Phone</td>
                <td><?php echo !empty($cafe['phone']) ? htmlspecialchars($cafe['phone']) : 'Not set'; ?></td>
            </tr>
        </tbody>
    </table>
</div>

<?php if (!empty($favorite_products)): ?>
<div class="table-container" style="margin-top: 30px;">
    <div class="table-header">
        <div class="table-title">Favorite Products (Most Purchased)</div>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>Product Name</th>
                <th>Price</th>
                <th>Total Purchased</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($favorite_products as $product): ?>
                <tr>
                    <td style="font-weight: 600; color: var(--primary-white);"><?php echo htmlspecialchars($product['item_name']); ?></td>
                    <td><?php echo formatCurrency($product['price']); ?></td>
                    <td><span class="badge badge-info"><?php echo number_format($product['total_purchased']); ?> items</span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($available_vouchers)): ?>
<div class="table-container" style="margin-top: 30px;">
    <div class="table-header">
        <div class="table-title">Available Discount Vouchers</div>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>Voucher Code</th>
                <th>Discount</th>
                <th>Conditions</th>
                <th>Valid Until</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($available_vouchers as $voucher): ?>
                <tr>
                    <td style="font-weight: 600; color: var(--primary-white);">
                        <code style="background: var(--primary-black); padding: 4px 8px; border-radius: 4px; color: var(--primary-white); font-size: 14px;"><?php echo htmlspecialchars($voucher['voucher_code']); ?></code>
                    </td>
                    <td style="color: #28a745; font-weight: 600;"><?php echo formatCurrency($voucher['discount_amount']); ?></td>
                    <td style="font-size: 12px; color: var(--text-gray);">
                        <?php if ($voucher['min_order_amount'] > 0): ?>
                            Min: <?php echo formatCurrency($voucher['min_order_amount']); ?><br>
                        <?php endif; ?>
                        <?php if ($voucher['max_order_amount']): ?>
                            Max: <?php echo formatCurrency($voucher['max_order_amount']); ?><br>
                        <?php endif; ?>
                        <?php if ($voucher['usage_limit']): ?>
                            <?php echo ($voucher['usage_limit'] - $voucher['used_count']); ?> uses left
                        <?php endif; ?>
                    </td>
                    <td style="font-size: 12px; color: var(--text-gray);">
                        <?php echo $voucher['valid_until'] ? date('d M Y', strtotime($voucher['valid_until'])) : 'No expiry'; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php elseif ($active_tab == 'recipes' && $_SESSION['user_role'] == 'owner'): ?>
    <!-- Recipes Tab Content -->
    <?php
    // Get products with recipes
    require_once 'config/functions_inventory.php';
    $stmt = $conn->prepare("
        SELECT mi.item_id, mi.item_name, 
               COUNT(pr.recipe_id) as recipe_count,
               COALESCE(pp.ingredient_cost, 0) as ingredient_cost
        FROM menu_items mi
        LEFT JOIN product_recipes pr ON mi.item_id = pr.item_id
        LEFT JOIN product_pricing pp ON mi.item_id = pp.item_id
        WHERE mi.cafe_id = ?
        GROUP BY mi.item_id, mi.item_name, pp.ingredient_cost
        ORDER BY mi.item_name
    ");
    $stmt->execute([$cafe_id]);
    $products_with_recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    
    <div class="table-container">
        <div class="table-header">
            <div class="table-title">Product Recipes</div>
            <a href="sub_recipes.php" class="btn btn-secondary btn-sm">Manage Sub-Recipes</a>
        </div>
        
        <?php if (empty($products_with_recipes)): ?>
            <div style="padding: 20px; text-align: center; color: var(--text-gray);">
                No products found. <a href="products.php" style="color: var(--primary-white);">Add products first</a>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Recipe Items</th>
                        <th>Ingredient Cost</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products_with_recipes as $prod): ?>
                        <tr>
                            <td style="font-weight: 600; color: var(--primary-white);"><?php echo htmlspecialchars($prod['item_name']); ?></td>
                            <td><?php echo $prod['recipe_count']; ?> ingredients</td>
                            <td><?php echo formatCurrency($prod['ingredient_cost']); ?></td>
                            <td>
                                <a href="product_recipes.php?item_id=<?php echo $prod['item_id']; ?>" class="btn btn-primary btn-sm">View/Edit Recipe</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

<?php elseif ($active_tab == 'pricing' && $_SESSION['user_role'] == 'owner'): ?>
    <!-- Smart Pricing Tab Content -->
    <?php
    $stmt = $conn->prepare("
        SELECT mi.*, pp.*
        FROM menu_items mi
        LEFT JOIN product_pricing pp ON mi.item_id = pp.item_id
        WHERE mi.cafe_id = ?
        ORDER BY mi.item_name
    ");
    $stmt->execute([$cafe_id]);
    $pricing_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    
    <div class="table-container">
        <div class="table-header">
            <div class="table-title">Smart Pricing Overview</div>
        </div>
        
        <table class="table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Ingredient Cost</th>
                    <th>Current Price</th>
                    <th>Suggested Price</th>
                    <th>Margin</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pricing_products as $prod): ?>
                    <?php
                    require_once 'config/functions_inventory.php';
                    $ingredient_cost = $prod['ingredient_cost'] ?? calculateProductIngredientCost($conn, $prod['item_id']);
                    $current_price = $prod['price'];
                    $suggested = $prod['suggested_price'] ?? 0;
                    $margin = $ingredient_cost > 0 ? (($current_price - $ingredient_cost) / $ingredient_cost) * 100 : 0;
                    ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--primary-white);"><?php echo htmlspecialchars($prod['item_name']); ?></td>
                        <td><?php echo formatCurrency($ingredient_cost); ?></td>
                        <td><?php echo formatCurrency($current_price); ?></td>
                        <td>
                            <?php if ($suggested > 0): ?>
                                <span style="color: #28a745;"><?php echo formatCurrency($suggested); ?></span>
                            <?php else: ?>
                                <span style="color: var(--text-gray);">Not calculated</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="color: <?php echo $margin >= 40 ? '#28a745' : ($margin >= 20 ? '#ffc107' : '#dc3545'); ?>; font-weight: 600;">
                                <?php echo number_format($margin, 1); ?>%
                            </span>
                        </td>
                        <td>
                            <a href="smart_pricing.php?item_id=<?php echo $prod['item_id']; ?>" class="btn btn-primary btn-sm">Configure</a>
                            <a href="profit_simulator.php?item_id=<?php echo $prod['item_id']; ?>" class="btn btn-secondary btn-sm">Simulator</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($active_tab == 'engineering' && $_SESSION['user_role'] == 'owner'): ?>
    <!-- Menu Engineering Tab Content -->
    <div style="text-align: center; padding: 40px;">
        <h3 style="color: var(--primary-white); margin-bottom: 20px;">Menu Engineering Analysis</h3>
        <p style="color: var(--text-gray); margin-bottom: 30px;">Analyze your menu performance and optimize profitability</p>
        <a href="menu_engineering.php" class="btn btn-primary">Open Menu Engineering Dashboard</a>
    </div>

<?php elseif ($active_tab == 'sales' && $_SESSION['user_role'] == 'owner'): ?>
    <!-- Sales Reports Tab Content -->
    <div style="text-align: center; padding: 40px;">
        <h3 style="color: var(--primary-white); margin-bottom: 20px;">Monthly Sales Reports</h3>
        <p style="color: var(--text-gray); margin-bottom: 30px;">View detailed sales analytics and reports</p>
        <a href="monthly_sales_report.php" class="btn btn-primary">Open Sales Reports</a>
    </div>

<?php elseif ($active_tab == 'settings' && $_SESSION['user_role'] == 'owner'): ?>
    <!-- Settings Tab Content -->
    <?php
    // Get current settings
    $stmt = $conn->prepare("SELECT * FROM cafe_settings WHERE cafe_id = ?");
    $stmt->execute([$cafe_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if tax_percentage column exists, if not add it
    try {
        $columns = $conn->query("SHOW COLUMNS FROM cafe_settings")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('tax_percentage', $columns)) {
            $conn->exec("ALTER TABLE cafe_settings ADD COLUMN tax_percentage DECIMAL(5,2) DEFAULT 10.00 AFTER accent_color");
            // Reload settings
            $stmt = $conn->prepare("SELECT * FROM cafe_settings WHERE cafe_id = ?");
            $stmt->execute([$cafe_id]);
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error checking/adding tax_percentage column: " . $e->getMessage());
    }
    
    if (!$settings) {
        $columns = $conn->query("SHOW COLUMNS FROM cafe_settings")->fetchAll(PDO::FETCH_COLUMN);
        $has_tax = in_array('tax_percentage', $columns);
        
        if ($has_tax) {
            $stmt = $conn->prepare("INSERT INTO cafe_settings (cafe_id, primary_color, secondary_color, accent_color, tax_percentage) VALUES (?, '#FFFFFF', '#0f172a', '#6366f1', 10.00)");
        } else {
            $stmt = $conn->prepare("INSERT INTO cafe_settings (cafe_id, primary_color, secondary_color, accent_color) VALUES (?, '#FFFFFF', '#0f172a', '#6366f1')");
        }
        $stmt->execute([$cafe_id]);
        $stmt = $conn->prepare("SELECT * FROM cafe_settings WHERE cafe_id = ?");
        $stmt->execute([$cafe_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Set default tax percentage if not set
    if (!isset($settings['tax_percentage']) || $settings['tax_percentage'] === null) {
        $settings['tax_percentage'] = 10.00;
    }
    
    $settings_error = '';
    $settings_success = '';
    
    // Handle settings update
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {
        $tax_percentage = isset($_POST['tax_percentage']) ? (float)$_POST['tax_percentage'] : 10.00;
        
        if ($tax_percentage < 0 || $tax_percentage > 100) {
            $settings_error = 'Tax percentage must be between 0 and 100';
        } else {
            // Check if tax_percentage column exists
            $columns = $conn->query("SHOW COLUMNS FROM cafe_settings")->fetchAll(PDO::FETCH_COLUMN);
            $has_tax = in_array('tax_percentage', $columns);
            
            $update_success = false;
            if ($has_tax) {
                $stmt = $conn->prepare("UPDATE cafe_settings SET tax_percentage = ? WHERE cafe_id = ?");
                $update_success = $stmt->execute([$tax_percentage, $cafe_id]);
            } else {
                // Column doesn't exist, add it first
                try {
                    $conn->exec("ALTER TABLE cafe_settings ADD COLUMN tax_percentage DECIMAL(5,2) DEFAULT 10.00 AFTER accent_color");
                    $stmt = $conn->prepare("UPDATE cafe_settings SET tax_percentage = ? WHERE cafe_id = ?");
                    $update_success = $stmt->execute([$tax_percentage, $cafe_id]);
                } catch (Exception $e) {
                    $settings_error = 'Failed to update tax percentage: ' . $e->getMessage();
                }
            }
            
            if ($update_success) {
                $settings_success = 'Tax percentage updated successfully. Changes will apply to POS and customer checkout.';
                // Reload settings
                $stmt = $conn->prepare("SELECT * FROM cafe_settings WHERE cafe_id = ?");
                $stmt->execute([$cafe_id]);
                $settings = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!isset($settings['tax_percentage']) || $settings['tax_percentage'] === null) {
                    $settings['tax_percentage'] = 10.00;
                }
            } else {
                $settings_error = 'Failed to update tax percentage';
            }
        }
    }
    ?>
    <div class="form-container">
        <h3 style="color: var(--primary-white); margin-bottom: 20px;">Café Settings</h3>
        
        <?php if ($settings_error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($settings_error); ?></div>
        <?php endif; ?>
        
        <?php if ($settings_success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($settings_success); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="update_settings" value="1">
            
            <div class="form-group">
                <label for="tax_percentage">Tax Percentage (%) *</label>
                <input type="number" id="tax_percentage" name="tax_percentage" step="0.01" min="0" max="100" value="<?php echo htmlspecialchars($settings['tax_percentage'] ?? 10.00); ?>" required>
                <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">
                    This tax percentage will be automatically applied in POS/Transaction and customer checkout. Example: 10 for 10% tax.
                </p>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Update Settings</button>
            </div>
        </form>
    </div>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>
