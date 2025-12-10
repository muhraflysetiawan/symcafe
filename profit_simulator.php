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

if (!$item_id) {
    header('Location: products.php');
    exit();
}

// Get product info
$stmt = $conn->prepare("SELECT * FROM menu_items WHERE item_id = ? AND cafe_id = ?");
$stmt->execute([$item_id, $cafe_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: products.php');
    exit();
}

// Calculate ingredient cost
$ingredient_cost = calculateProductIngredientCost($conn, $item_id);

// Get pricing info
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
        'max_price' => $ingredient_cost * 2.5
    ];
}

$current_price = $product['price'];
$current_margin = ($ingredient_cost > 0) ? (($current_price - $ingredient_cost) / $ingredient_cost) * 100 : 0;

// Ensure pricing values are safe (prevent division by zero)
if ($ingredient_cost <= 0) {
    $ingredient_cost = 0.01; // Set minimum to prevent division errors
}
if (!$pricing || $pricing['ingredient_cost'] <= 0) {
    $margin = 40;
    $suggested = calculateSuggestedPrice($ingredient_cost, $margin);
    $pricing = [
        'ingredient_cost' => $ingredient_cost,
        'desired_margin_percent' => $margin,
        'suggested_price' => $suggested,
        'min_price' => $ingredient_cost * 1.2,
        'max_price' => $ingredient_cost * 2.5
    ];
} else {
    // Ensure pricing values are safe
    if ($pricing['ingredient_cost'] <= 0) {
        $pricing['ingredient_cost'] = 0.01;
    }
    if ($pricing['min_price'] <= 0) {
        $pricing['min_price'] = $pricing['ingredient_cost'] * 1.2;
    }
    if ($pricing['max_price'] <= 0) {
        $pricing['max_price'] = $pricing['ingredient_cost'] * 2.5;
    }
    if ($pricing['suggested_price'] <= 0) {
        $pricing['suggested_price'] = calculateSuggestedPrice($pricing['ingredient_cost'], $pricing['desired_margin_percent']);
    }
}

$page_title = 'Profit Simulator';
include 'includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">
    Profit Simulator: <?php echo htmlspecialchars($product['item_name']); ?>
    <a href="smart_pricing.php?item_id=<?php echo $item_id; ?>" class="btn btn-secondary btn-sm" style="margin-left: 15px;">← Back to Pricing</a>
</h2>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
        <div style="color: var(--text-gray); font-size: 14px; margin-bottom: 5px;">Ingredient Cost</div>
        <div style="color: var(--primary-white); font-size: 28px; font-weight: bold;" id="ingredient_cost_display"><?php echo formatCurrency($ingredient_cost); ?></div>
    </div>
    <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
        <div style="color: var(--text-gray); font-size: 14px; margin-bottom: 5px;">Current Selling Price</div>
        <div style="color: var(--primary-white); font-size: 28px; font-weight: bold;" id="current_price_display"><?php echo formatCurrency($current_price); ?></div>
    </div>
    <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
        <div style="color: var(--text-gray); font-size: 14px; margin-bottom: 5px;">Current Margin</div>
        <div style="color: <?php echo $current_margin >= 40 ? '#28a745' : ($current_margin >= 20 ? '#ffc107' : '#dc3545'); ?>; font-size: 28px; font-weight: bold;" id="current_margin_display"><?php echo number_format($current_margin, 1); ?>%</div>
    </div>
</div>

<div class="form-container">
    <div style="margin-bottom: 30px;">
        <h3 style="color: var(--primary-white); margin-bottom: 20px;">Price Simulation</h3>
        
        <div class="form-group">
            <label for="price_slider">Selling Price: <span id="price_value" style="color: var(--primary-white); font-weight: 600;"><?php echo formatCurrency($current_price); ?></span></label>
            <input type="range" id="price_slider" min="<?php echo max(1, $pricing['min_price']); ?>" 
                   max="<?php echo $pricing['max_price']; ?>" 
                   step="100" 
                   value="<?php echo $current_price; ?>"
                   oninput="updatePriceSimulation()"
                   style="width: 100%; margin: 10px 0;">
            <div style="display: flex; justify-content: space-between; color: var(--text-gray); font-size: 12px;">
                <span>Min: <?php echo formatCurrency($pricing['min_price']); ?></span>
                <span>Max: <?php echo formatCurrency($pricing['max_price']); ?></span>
            </div>
        </div>
        
        <div class="form-group" style="margin-top: 30px;">
            <label for="cost_slider">Ingredient Cost: <span id="cost_value" style="color: var(--primary-white); font-weight: 600;"><?php echo formatCurrency($ingredient_cost); ?></span></label>
            <input type="range" id="cost_slider" min="0" 
                   max="<?php echo $ingredient_cost * 2; ?>" 
                   step="100" 
                   value="<?php echo $ingredient_cost; ?>"
                   oninput="updateCostSimulation()"
                   style="width: 100%; margin: 10px 0;">
        </div>
    </div>
    
    <!-- Profit Scenarios -->
    <div style="margin-top: 40px;">
        <h3 style="color: var(--primary-white); margin-bottom: 20px;">Profit Scenarios</h3>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <!-- Low Price Scenario -->
            <div id="low_price_scenario" style="background: rgba(220, 53, 69, 0.1); border: 2px solid #dc3545; padding: 20px; border-radius: 8px;">
                <h4 style="color: #dc3545; margin-bottom: 15px;">Low Price (Small Margin)</h4>
                <div style="margin-bottom: 10px;">
                    <div style="color: var(--text-gray); font-size: 12px;">Price</div>
                    <div style="color: var(--primary-white); font-size: 24px; font-weight: bold;" id="low_price"><?php echo formatCurrency($pricing['min_price']); ?></div>
                </div>
                <div style="margin-bottom: 10px;">
                    <div style="color: var(--text-gray); font-size: 12px;">Margin</div>
                    <div style="color: #dc3545; font-size: 20px; font-weight: bold;" id="low_margin">20.0%</div>
                </div>
                <div style="margin-bottom: 10px;">
                    <div style="color: var(--text-gray); font-size: 12px;">Profit per Unit</div>
                    <div style="color: var(--primary-white); font-size: 18px; font-weight: 600;" id="low_profit"><?php echo formatCurrency($pricing['min_price'] - $ingredient_cost); ?></div>
                </div>
                <div>
                    <div style="color: var(--text-gray); font-size: 12px;">ROI</div>
                    <div style="color: var(--primary-white); font-size: 16px;" id="low_roi"><?php echo $ingredient_cost > 0 ? number_format((($pricing['min_price'] - $ingredient_cost) / $ingredient_cost) * 100, 1) : '0.0'; ?>%</div>
                </div>
            </div>
            
            <!-- Recommended Price Scenario -->
            <div id="recommended_scenario" style="background: rgba(40, 167, 69, 0.1); border: 2px solid #28a745; padding: 20px; border-radius: 8px;">
                <h4 style="color: #28a745; margin-bottom: 15px;">Recommended Price</h4>
                <div style="margin-bottom: 10px;">
                    <div style="color: var(--text-gray); font-size: 12px;">Price</div>
                    <div style="color: var(--primary-white); font-size: 24px; font-weight: bold;" id="recommended_price"><?php echo formatCurrency($pricing['suggested_price']); ?></div>
                </div>
                <div style="margin-bottom: 10px;">
                    <div style="color: var(--text-gray); font-size: 12px;">Margin</div>
                    <div style="color: #28a745; font-size: 20px; font-weight: bold;" id="recommended_margin"><?php echo number_format($pricing['desired_margin_percent'], 1); ?>%</div>
                </div>
                <div style="margin-bottom: 10px;">
                    <div style="color: var(--text-gray); font-size: 12px;">Profit per Unit</div>
                    <div style="color: var(--primary-white); font-size: 18px; font-weight: 600;" id="recommended_profit"><?php echo formatCurrency($pricing['suggested_price'] - $ingredient_cost); ?></div>
                </div>
                <div>
                    <div style="color: var(--text-gray); font-size: 12px;">ROI</div>
                    <div style="color: var(--primary-white); font-size: 16px;" id="recommended_roi"><?php echo number_format($pricing['desired_margin_percent'], 1); ?>%</div>
                </div>
            </div>
            
            <!-- High Price Scenario -->
            <div id="high_price_scenario" style="background: rgba(255, 193, 7, 0.1); border: 2px solid #ffc107; padding: 20px; border-radius: 8px;">
                <h4 style="color: #ffc107; margin-bottom: 15px;">High Price (Premium)</h4>
                <div style="margin-bottom: 10px;">
                    <div style="color: var(--text-gray); font-size: 12px;">Price</div>
                    <div style="color: var(--primary-white); font-size: 24px; font-weight: bold;" id="high_price"><?php echo formatCurrency($pricing['max_price']); ?></div>
                </div>
                <div style="margin-bottom: 10px;">
                    <div style="color: var(--text-gray); font-size: 12px;">Margin</div>
                    <div style="color: #ffc107; font-size: 20px; font-weight: bold;" id="high_margin">150.0%</div>
                </div>
                <div style="margin-bottom: 10px;">
                    <div style="color: var(--text-gray); font-size: 12px;">Profit per Unit</div>
                    <div style="color: var(--primary-white); font-size: 18px; font-weight: 600;" id="high_profit"><?php echo formatCurrency($pricing['max_price'] - $ingredient_cost); ?></div>
                </div>
                <div>
                    <div style="color: var(--text-gray); font-size: 12px;">ROI</div>
                    <div style="color: var(--primary-white); font-size: 16px;" id="high_roi">150.0%</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Current Simulation Results -->
    <div style="margin-top: 40px; padding: 20px; background: var(--accent-gray); border-radius: 8px;">
        <h3 style="color: var(--primary-white); margin-bottom: 20px;">Current Simulation Results</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div>
                <div style="color: var(--text-gray); font-size: 12px;">Simulated Price</div>
                <div style="color: var(--primary-white); font-size: 24px; font-weight: bold;" id="sim_price"><?php echo formatCurrency($current_price); ?></div>
            </div>
            <div>
                <div style="color: var(--text-gray); font-size: 12px;">Simulated Cost</div>
                <div style="color: var(--primary-white); font-size: 24px; font-weight: bold;" id="sim_cost"><?php echo formatCurrency($ingredient_cost); ?></div>
            </div>
            <div>
                <div style="color: var(--text-gray); font-size: 12px;">Profit per Unit</div>
                <div style="color: #28a745; font-size: 24px; font-weight: bold;" id="sim_profit"><?php echo formatCurrency($current_price - $ingredient_cost); ?></div>
            </div>
            <div>
                <div style="color: var(--text-gray); font-size: 12px;">Profit Margin</div>
                <div style="color: var(--primary-white); font-size: 24px; font-weight: bold;" id="sim_margin"><?php echo number_format($current_margin, 1); ?>%</div>
            </div>
            <div>
                <div style="color: var(--text-gray); font-size: 12px;">ROI</div>
                <div style="color: var(--primary-white); font-size: 24px; font-weight: bold;" id="sim_roi"><?php echo number_format($current_margin, 1); ?>%</div>
            </div>
        </div>
    </div>
</div>

<script>
const baseIngredientCost = <?php echo $ingredient_cost; ?>;
const baseMinPrice = <?php echo $pricing['min_price']; ?>;
const baseMaxPrice = <?php echo $pricing['max_price']; ?>;
const baseSuggestedPrice = <?php echo $pricing['suggested_price']; ?>;
const baseMargin = <?php echo $pricing['desired_margin_percent']; ?>;

function updatePriceSimulation() {
    const price = parseFloat(document.getElementById('price_slider').value);
    const cost = parseFloat(document.getElementById('cost_slider').value);
    
    document.getElementById('price_value').textContent = formatCurrency(price);
    document.getElementById('current_price_display').textContent = formatCurrency(price);
    
    calculateProfit(price, cost);
}

function updateCostSimulation() {
    const cost = parseFloat(document.getElementById('cost_slider').value);
    const price = parseFloat(document.getElementById('price_slider').value);
    
    document.getElementById('cost_value').textContent = formatCurrency(cost);
    document.getElementById('ingredient_cost_display').textContent = formatCurrency(cost);
    
    // Update min/max prices based on new cost
    const newMinPrice = cost * 1.2;
    const newMaxPrice = cost * 2.5;
    document.getElementById('price_slider').min = Math.max(1, newMinPrice);
    document.getElementById('price_slider').max = newMaxPrice;
    
    // Update scenario prices
    document.getElementById('low_price').textContent = formatCurrency(newMinPrice);
    document.getElementById('high_price').textContent = formatCurrency(newMaxPrice);
    
    // Recalculate recommended price (prevent division by zero)
    const safeCost = cost > 0 ? cost : 0.01;
    const recommendedPrice = safeCost / (1 - (baseMargin / 100));
    document.getElementById('recommended_price').textContent = formatCurrency(recommendedPrice);
    
    calculateProfit(price, cost);
}

function calculateProfit(price, cost) {
    const profit = price - cost;
    const margin = cost > 0 ? ((price - cost) / cost) * 100 : 0;
    const roi = cost > 0 ? ((profit / cost) * 100) : 0;
    
    // Update simulation results
    document.getElementById('sim_price').textContent = formatCurrency(price);
    document.getElementById('sim_cost').textContent = formatCurrency(cost);
    document.getElementById('sim_profit').textContent = formatCurrency(profit);
    document.getElementById('sim_margin').textContent = margin.toFixed(1) + '%';
    document.getElementById('sim_roi').textContent = roi.toFixed(1) + '%';
    
    // Update current margin display
    const marginColor = margin >= 40 ? '#28a745' : (margin >= 20 ? '#ffc107' : '#dc3545');
    document.getElementById('current_margin_display').textContent = margin.toFixed(1) + '%';
    document.getElementById('current_margin_display').style.color = marginColor;
    
    // Update scenario profits (prevent division by zero)
    const safeCost = cost > 0 ? cost : 0.01;
    const lowPrice = safeCost * 1.2;
    const highPrice = safeCost * 2.5;
    const recommendedPrice = safeCost / (1 - (baseMargin / 100));
    
    document.getElementById('low_profit').textContent = formatCurrency(lowPrice - safeCost);
    document.getElementById('low_margin').textContent = '20.0%';
    document.getElementById('low_roi').textContent = '20.0%';
    
    document.getElementById('recommended_profit').textContent = formatCurrency(recommendedPrice - safeCost);
    document.getElementById('recommended_margin').textContent = baseMargin.toFixed(1) + '%';
    document.getElementById('recommended_roi').textContent = baseMargin.toFixed(1) + '%';
    
    document.getElementById('high_profit').textContent = formatCurrency(highPrice - safeCost);
    document.getElementById('high_margin').textContent = '150.0%';
    document.getElementById('high_roi').textContent = '150.0%';
}

function formatCurrency(amount) {
    return 'Rp ' + Math.round(amount).toLocaleString('id-ID');
}
</script>

<?php include 'includes/footer.php'; ?>

