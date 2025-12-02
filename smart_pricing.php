<?php
require_once 'config/config.php';
requireLogin();
requireCafeSetup();
requireRole(['owner']);

require_once 'config/functions_inventory.php';

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

// Get all products if no specific item
if ($item_id) {
    $stmt = $conn->prepare("SELECT * FROM menu_items WHERE item_id = ? AND cafe_id = ?");
    $stmt->execute([$item_id, $cafe_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $product = null;
}

$message = '';
$message_type = '';

// Handle pricing update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_pricing'])) {
    $item_id = (int)$_POST['item_id'];
    $desired_margin = (float)$_POST['desired_margin'];
    $competitor_price = !empty($_POST['competitor_price']) ? (float)$_POST['competitor_price'] : null;
    
    try {
        $ingredient_cost = calculateProductIngredientCost($conn, $item_id);
        $suggested_price = calculateSuggestedPrice($ingredient_cost, $desired_margin);
        $min_price = $ingredient_cost * 1.2;
        $max_price = $ingredient_cost * 2.5;
        
        // Apply psychological pricing
        $psychological_price = applyPsychologicalPricing($suggested_price);
        
        $stmt = $conn->prepare("
            INSERT INTO product_pricing (item_id, ingredient_cost, desired_margin_percent, suggested_price, min_price, max_price, competitor_price, psychological_price, last_calculated_at, auto_update_enabled)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)
            ON DUPLICATE KEY UPDATE
                ingredient_cost = VALUES(ingredient_cost),
                desired_margin_percent = VALUES(desired_margin_percent),
                suggested_price = VALUES(suggested_price),
                min_price = VALUES(min_price),
                max_price = VALUES(max_price),
                competitor_price = VALUES(competitor_price),
                psychological_price = VALUES(psychological_price),
                last_calculated_at = NOW()
        ");
        $stmt->execute([$item_id, $ingredient_cost, $desired_margin, $suggested_price, $min_price, $max_price, $competitor_price, $psychological_price]);
        
        $message = 'Pricing updated successfully';
        $message_type = 'success';
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get products with pricing
if ($item_id && $product) {
    $ingredient_cost = calculateProductIngredientCost($conn, $item_id);
    $stmt = $conn->prepare("SELECT * FROM product_pricing WHERE item_id = ?");
    $stmt->execute([$item_id]);
    $pricing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pricing) {
        // Create default pricing
        $margin = 40;
        $suggested = calculateSuggestedPrice($ingredient_cost, $margin);
        $pricing = [
            'ingredient_cost' => $ingredient_cost,
            'desired_margin_percent' => $margin,
            'suggested_price' => $suggested,
            'min_price' => $ingredient_cost * 1.2,
            'max_price' => $ingredient_cost * 2.5,
            'competitor_price' => null,
            'psychological_price' => applyPsychologicalPricing($suggested)
        ];
    }
} else {
    // List all products
    $stmt = $conn->prepare("
        SELECT mi.*, pp.*
        FROM menu_items mi
        LEFT JOIN product_pricing pp ON mi.item_id = pp.item_id
        WHERE mi.cafe_id = ?
        ORDER BY mi.item_name
    ");
    $stmt->execute([$cafe_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$page_title = 'Smart Pricing';
include 'includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">Smart Pricing System</h2>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type == 'error' ? 'error' : 'success'; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($item_id && $product): ?>
    <!-- Single Product Pricing -->
    <div style="margin-bottom: 20px;">
        <a href="smart_pricing.php" class="btn btn-secondary btn-sm">← Back to All Products</a>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
            <div style="color: var(--text-gray); font-size: 14px; margin-bottom: 5px;">Ingredient Cost</div>
            <div style="color: var(--primary-white); font-size: 28px; font-weight: bold;"><?php echo formatCurrency($pricing['ingredient_cost']); ?></div>
        </div>
        <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
            <div style="color: var(--text-gray); font-size: 14px; margin-bottom: 5px;">Current Price</div>
            <div style="color: var(--primary-white); font-size: 28px; font-weight: bold;"><?php echo formatCurrency($product['price']); ?></div>
        </div>
        <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
            <div style="color: var(--text-gray); font-size: 14px; margin-bottom: 5px;">Suggested Price</div>
            <div style="color: #28a745; font-size: 28px; font-weight: bold;"><?php echo formatCurrency($pricing['suggested_price']); ?></div>
        </div>
        <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
            <div style="color: var(--text-gray); font-size: 14px; margin-bottom: 5px;">Psychological Price</div>
            <div style="color: #ffc107; font-size: 28px; font-weight: bold;"><?php echo formatCurrency($pricing['psychological_price']); ?></div>
        </div>
    </div>
    
    <div class="form-container">
        <form method="POST">
            <input type="hidden" name="item_id" value="<?php echo $item_id; ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="desired_margin">Desired Profit Margin (%)</label>
                    <input type="number" id="desired_margin" name="desired_margin" step="0.1" min="0" max="100" 
                           value="<?php echo $pricing['desired_margin_percent']; ?>" 
                           onchange="calculatePrice()" required>
                </div>
                
                <div class="form-group">
                    <label for="competitor_price">Competitor Price (Optional)</label>
                    <input type="number" id="competitor_price" name="competitor_price" step="0.01" min="0" 
                           value="<?php echo $pricing['competitor_price'] ?? ''; ?>">
                </div>
            </div>
            
            <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px; margin-top: 20px;">
                <h3 style="color: var(--primary-white); margin-bottom: 15px;">Price Recommendations</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div>
                        <div style="color: var(--text-gray); font-size: 12px;">Minimum Price</div>
                        <div style="color: var(--primary-white); font-size: 20px; font-weight: 600;"><?php echo formatCurrency($pricing['min_price']); ?></div>
                        <div style="color: var(--text-gray); font-size: 11px;">20% margin</div>
                    </div>
                    <div>
                        <div style="color: var(--text-gray); font-size: 12px;">Recommended Price</div>
                        <div style="color: #28a745; font-size: 20px; font-weight: 600;" id="recommended_price"><?php echo formatCurrency($pricing['suggested_price']); ?></div>
                        <div style="color: var(--text-gray); font-size: 11px;" id="recommended_margin"><?php echo number_format($pricing['desired_margin_percent'], 1); ?>% margin</div>
                    </div>
                    <div>
                        <div style="color: var(--text-gray); font-size: 12px;">Maximum Price</div>
                        <div style="color: var(--primary-white); font-size: 20px; font-weight: 600;"><?php echo formatCurrency($pricing['max_price']); ?></div>
                        <div style="color: var(--text-gray); font-size: 11px;">150% margin</div>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 20px;">
                <button type="submit" name="update_pricing" class="btn btn-primary">Update Pricing</button>
                <a href="profit_simulator.php?item_id=<?php echo $item_id; ?>" class="btn btn-secondary" style="margin-left: 10px;">Open Profit Simulator</a>
            </div>
        </form>
    </div>
    
    <script>
    function calculatePrice() {
        const margin = parseFloat(document.getElementById('desired_margin').value) || 0;
        const ingredientCost = <?php echo $pricing['ingredient_cost']; ?>;
        
        if (margin > 0 && ingredientCost > 0) {
            const suggestedPrice = ingredientCost / (1 - (margin / 100));
            document.getElementById('recommended_price').textContent = 'Rp ' + suggestedPrice.toLocaleString('id-ID');
            document.getElementById('recommended_margin').textContent = margin.toFixed(1) + '% margin';
        }
    }
    </script>
    
<?php else: ?>
    <!-- Product List -->
    <div class="table-container">
        <div class="table-header">
            <div class="table-title">Products Pricing Overview</div>
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
                <?php foreach ($products as $prod): ?>
                    <?php
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
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

