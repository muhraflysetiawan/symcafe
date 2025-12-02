<?php
require_once '../config/config.php';
requireLogin();
requireCafeSetup();
requireRole(['owner']);

require_once '../config/functions_inventory.php';

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$recipe_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $recipe_id > 0;

if (!$item_id && !$is_edit) {
    header('Location: ../products.php');
    exit();
}

// Get product info
if ($item_id) {
    $stmt = $conn->prepare("SELECT * FROM menu_items WHERE item_id = ? AND cafe_id = ?");
    $stmt->execute([$item_id, $cafe_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        header('Location: ../products.php');
        exit();
    }
}

// Get recipe data if editing
$recipe = null;
if ($is_edit) {
    $stmt = $conn->prepare("SELECT pr.*, mi.item_id, mi.cafe_id FROM product_recipes pr JOIN menu_items mi ON pr.item_id = mi.item_id WHERE pr.recipe_id = ? AND mi.cafe_id = ?");
    $stmt->execute([$recipe_id, $cafe_id]);
    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($recipe) {
        $item_id = $recipe['item_id'];
        $stmt = $conn->prepare("SELECT * FROM menu_items WHERE item_id = ?");
        $stmt->execute([$item_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        header('Location: ../products.php');
        exit();
    }
}

// Get available materials
$stmt = $conn->prepare("SELECT * FROM raw_materials WHERE cafe_id = ? AND is_active = 1 ORDER BY material_name");
$stmt->execute([$cafe_id]);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available sub-recipes
$stmt = $conn->prepare("SELECT * FROM sub_recipes WHERE cafe_id = ? ORDER BY sub_recipe_name");
$stmt->execute([$cafe_id]);
$sub_recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $material_id = !empty($_POST['material_id']) ? (int)$_POST['material_id'] : null;
    $sub_recipe_id = !empty($_POST['sub_recipe_id']) ? (int)$_POST['sub_recipe_id'] : null;
    $quantity = (float)($_POST['quantity'] ?? 0);
    $notes = trim(sanitizeInput($_POST['notes'] ?? ''));
    $item_id = (int)($_POST['item_id'] ?? $item_id);
    
    if (($material_id && $sub_recipe_id) || (!$material_id && !$sub_recipe_id)) {
        $error = 'Please select either a material or a sub-recipe (not both)';
    } elseif ($quantity <= 0) {
        $error = 'Quantity must be greater than 0';
    } else {
        try {
            if ($is_edit) {
                $stmt = $conn->prepare("UPDATE product_recipes SET material_id = ?, sub_recipe_id = ?, quantity = ?, notes = ? WHERE recipe_id = ?");
                if ($stmt->execute([$material_id, $sub_recipe_id, $quantity, $notes, $recipe_id])) {
                    // Recalculate pricing
                    recalculateProductPricing($conn, $cafe_id, null);
                    $success = 'Recipe item updated. Pricing recalculated.';
                    header('Location: ../product_recipes.php?item_id=' . $item_id);
                    exit();
                }
            } else {
                $stmt = $conn->prepare("INSERT INTO product_recipes (item_id, material_id, sub_recipe_id, quantity, notes) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$item_id, $material_id, $sub_recipe_id, $quantity, $notes])) {
                    // Recalculate pricing
                    recalculateProductPricing($conn, $cafe_id, null);
                    $success = 'Recipe item added. Pricing recalculated.';
                    header('Location: ../product_recipes.php?item_id=' . $item_id);
                    exit();
                }
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

$page_title = $is_edit ? 'Edit Recipe Item' : 'Add Recipe Item';
include '../includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">
    <?php echo $is_edit ? 'Edit Recipe Item' : 'Add Recipe Item'; ?>
    <span style="color: var(--text-gray); font-size: 16px; font-weight: normal;">
        for <?php echo htmlspecialchars($product['item_name']); ?>
    </span>
</h2>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<div class="form-container">
    <form method="POST">
        <input type="hidden" name="item_id" value="<?php echo $item_id; ?>">
        
        <div class="form-group">
            <label>Select Ingredient Type *</label>
            <div style="display: flex; gap: 20px; margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px; background: var(--accent-gray); border-radius: 5px; flex: 1;">
                    <input type="radio" name="ingredient_type" value="material" 
                           onchange="toggleIngredientType('material')"
                           <?php echo (!isset($recipe) || $recipe['material_id']) ? 'checked' : ''; ?>>
                    <span style="color: var(--primary-white);">Raw Material</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px; background: var(--accent-gray); border-radius: 5px; flex: 1;">
                    <input type="radio" name="ingredient_type" value="sub_recipe" 
                           onchange="toggleIngredientType('sub_recipe')"
                           <?php echo (isset($recipe) && $recipe['sub_recipe_id']) ? 'checked' : ''; ?>>
                    <span style="color: var(--primary-white);">Sub-Recipe</span>
                </label>
            </div>
        </div>
        
        <div id="material_section" class="form-group" style="display: <?php echo (!isset($recipe) || $recipe['material_id']) ? 'block' : 'none'; ?>;">
            <label for="material_id">Select Raw Material *</label>
            <select id="material_id" name="material_id">
                <option value="">-- Select Material --</option>
                <?php foreach ($materials as $material): ?>
                    <option value="<?php echo $material['material_id']; ?>" 
                            data-unit="<?php echo $material['unit_type']; ?>"
                            data-cost="<?php echo $material['current_cost']; ?>"
                            <?php echo (isset($recipe) && $recipe['material_id'] == $material['material_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($material['material_name']); ?> 
                        (<?php echo $material['unit_type']; ?>, <?php echo formatCurrency($material['current_cost']); ?>/unit)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div id="sub_recipe_section" class="form-group" style="display: <?php echo (isset($recipe) && $recipe['sub_recipe_id']) ? 'block' : 'none'; ?>;">
            <label for="sub_recipe_id">Select Sub-Recipe *</label>
            <select id="sub_recipe_id" name="sub_recipe_id">
                <option value="">-- Select Sub-Recipe --</option>
                <?php foreach ($sub_recipes as $sub_recipe): ?>
                    <option value="<?php echo $sub_recipe['sub_recipe_id']; ?>"
                            <?php echo (isset($recipe) && $recipe['sub_recipe_id'] == $sub_recipe['sub_recipe_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($sub_recipe['sub_recipe_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($sub_recipes)): ?>
                <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">
                    No sub-recipes available. <a href="../sub_recipes.php" style="color: var(--primary-white);">Create one first</a>
                </p>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label for="quantity">Quantity *</label>
            <input type="number" id="quantity" name="quantity" step="0.01" min="0.01" required 
                   value="<?php echo $recipe['quantity'] ?? ''; ?>">
            <p id="quantity_hint" style="color: var(--text-gray); font-size: 12px; margin-top: 5px;"></p>
        </div>
        
        <div class="form-group">
            <label for="notes">Notes (Optional)</label>
            <textarea id="notes" name="notes" rows="3" placeholder="Additional notes about this ingredient..."><?php echo htmlspecialchars($recipe['notes'] ?? ''); ?></textarea>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button type="submit" class="btn btn-primary"><?php echo $is_edit ? 'Update Recipe Item' : 'Add Recipe Item'; ?></button>
            <a href="../product_recipes.php?item_id=<?php echo $item_id; ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
function toggleIngredientType(type) {
    const materialSection = document.getElementById('material_section');
    const subRecipeSection = document.getElementById('sub_recipe_section');
    const materialSelect = document.getElementById('material_id');
    const subRecipeSelect = document.getElementById('sub_recipe_id');
    
    if (type === 'material') {
        materialSection.style.display = 'block';
        subRecipeSection.style.display = 'none';
        materialSelect.required = true;
        subRecipeSelect.required = false;
        subRecipeSelect.value = '';
    } else {
        materialSection.style.display = 'none';
        subRecipeSection.style.display = 'block';
        materialSelect.required = false;
        subRecipeSelect.required = true;
        materialSelect.value = '';
    }
    updateQuantityHint();
}

function updateQuantityHint() {
    const materialSelect = document.getElementById('material_id');
    const quantityHint = document.getElementById('quantity_hint');
    const selectedOption = materialSelect.options[materialSelect.selectedIndex];
    
    if (selectedOption && selectedOption.value) {
        const unit = selectedOption.getAttribute('data-unit');
        quantityHint.textContent = 'Enter quantity in ' + unit;
    } else {
        quantityHint.textContent = '';
    }
}

document.getElementById('material_id').addEventListener('change', updateQuantityHint);
</script>

<?php include '../includes/footer.php'; ?>

