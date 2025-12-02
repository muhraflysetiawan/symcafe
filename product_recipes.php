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

// Calculate current ingredient cost
$ingredient_cost = calculateProductIngredientCost($conn, $item_id);

// Get recipe items
$stmt = $conn->prepare("
    SELECT pr.*, 
           m.material_name, m.unit_type, m.current_cost,
           sr.sub_recipe_name
    FROM product_recipes pr
    LEFT JOIN raw_materials m ON pr.material_id = m.material_id
    LEFT JOIN sub_recipes sr ON pr.sub_recipe_id = sr.sub_recipe_id
    WHERE pr.item_id = ?
    ORDER BY pr.recipe_id
");
$stmt->execute([$item_id]);
$recipe_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pricing info
$stmt = $conn->prepare("SELECT * FROM product_pricing WHERE item_id = ?");
$stmt->execute([$item_id]);
$pricing = $stmt->fetch(PDO::FETCH_ASSOC);

$message = '';
$message_type = '';

// Handle delete
if (isset($_GET['delete'])) {
    $recipe_id = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM product_recipes WHERE recipe_id = ? AND item_id = ?");
        if ($stmt->execute([$recipe_id, $item_id])) {
            // Recalculate pricing
            recalculateProductPricing($conn, $cafe_id, null);
            $message = 'Recipe item deleted. Pricing recalculated.';
            $message_type = 'success';
            header('Location: product_recipes.php?item_id=' . $item_id);
            exit();
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

$page_title = 'Product Recipe';
include 'includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">
    Recipe for: <?php echo htmlspecialchars($product['item_name']); ?>
    <a href="products.php" class="btn btn-secondary btn-sm" style="margin-left: 15px;">← Back to Products</a>
</h2>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type == 'error' ? 'error' : 'success'; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
        <div style="color: var(--text-gray); font-size: 14px; margin-bottom: 5px;">Total Ingredient Cost</div>
        <div style="color: var(--primary-white); font-size: 28px; font-weight: bold;"><?php echo formatCurrency($ingredient_cost); ?></div>
    </div>
    <?php if ($pricing): ?>
        <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
            <div style="color: var(--text-gray); font-size: 14px; margin-bottom: 5px;">Suggested Price</div>
            <div style="color: var(--primary-white); font-size: 28px; font-weight: bold;"><?php echo formatCurrency($pricing['suggested_price']); ?></div>
        </div>
        <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
            <div style="color: var(--text-gray); font-size: 14px; margin-bottom: 5px;">Current Selling Price</div>
            <div style="color: var(--primary-white); font-size: 28px; font-weight: bold;"><?php echo formatCurrency($product['price']); ?></div>
        </div>
    <?php endif; ?>
</div>

<div class="table-container">
    <div class="table-header">
        <div class="table-title">Recipe Items (Bill of Materials)</div>
        <a href="forms/recipe_item_form.php?item_id=<?php echo $item_id; ?>" class="btn btn-primary btn-sm">Add Ingredient</a>
    </div>
    
    <?php if (empty($recipe_items)): ?>
        <div style="padding: 20px; text-align: center; color: var(--text-gray);">
            No recipe items found. <a href="forms/recipe_item_form.php?item_id=<?php echo $item_id; ?>" style="color: var(--primary-white);">Add ingredients to create the recipe</a>
        </div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Ingredient</th>
                    <th>Type</th>
                    <th>Quantity</th>
                    <th>Unit Cost</th>
                    <th>Total Cost</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recipe_items as $item): ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--primary-white);">
                            <?php 
                            if ($item['material_id']) {
                                echo htmlspecialchars($item['material_name']);
                            } elseif ($item['sub_recipe_id']) {
                                echo htmlspecialchars($item['sub_recipe_name']) . ' <span style="color: var(--text-gray); font-size: 12px;">(Sub-recipe)</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($item['material_id']): ?>
                                <span class="badge badge-info">Material</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Sub-recipe</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($item['quantity'], 2); ?> <?php echo $item['unit_type'] ?? 'unit'; ?></td>
                        <td>
                            <?php 
                            if ($item['material_id']) {
                                echo formatCurrency($item['current_cost']);
                            } else {
                                $sub_cost = calculateSubRecipeCost($conn, $item['sub_recipe_id']);
                                echo formatCurrency($sub_cost);
                            }
                            ?>
                        </td>
                        <td style="font-weight: 600; color: var(--primary-white);">
                            <?php 
                            if ($item['material_id']) {
                                echo formatCurrency($item['quantity'] * $item['current_cost']);
                            } else {
                                $sub_cost = calculateSubRecipeCost($conn, $item['sub_recipe_id']);
                                echo formatCurrency($item['quantity'] * $sub_cost);
                            }
                            ?>
                        </td>
                        <td>
                            <a href="forms/recipe_item_form.php?id=<?php echo $item['recipe_id']; ?>&item_id=<?php echo $item_id; ?>" class="btn btn-primary btn-sm">Edit</a>
                            <a href="?delete=<?php echo $item['recipe_id']; ?>&item_id=<?php echo $item_id; ?>" 
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Are you sure you want to delete this recipe item?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if ($pricing): ?>
<div style="margin-top: 30px; padding: 20px; background: var(--accent-gray); border-radius: 8px;">
    <h3 style="color: var(--primary-white); margin-bottom: 15px;">Pricing Information</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <div>
            <div style="color: var(--text-gray); font-size: 12px;">Desired Margin</div>
            <div style="color: var(--primary-white); font-size: 18px; font-weight: 600;"><?php echo number_format($pricing['desired_margin_percent'], 1); ?>%</div>
        </div>
        <div>
            <div style="color: var(--text-gray); font-size: 12px;">Minimum Price</div>
            <div style="color: var(--primary-white); font-size: 18px; font-weight: 600;"><?php echo formatCurrency($pricing['min_price']); ?></div>
        </div>
        <div>
            <div style="color: var(--text-gray); font-size: 12px;">Maximum Price</div>
            <div style="color: var(--primary-white); font-size: 18px; font-weight: 600;"><?php echo formatCurrency($pricing['max_price']); ?></div>
        </div>
    </div>
    <div style="margin-top: 15px;">
        <a href="smart_pricing.php?item_id=<?php echo $item_id; ?>" class="btn btn-primary btn-sm">View Smart Pricing</a>
        <a href="profit_simulator.php?item_id=<?php echo $item_id; ?>" class="btn btn-secondary btn-sm" style="margin-left: 10px;">Profit Simulator</a>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

