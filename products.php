<?php
require_once 'config/config.php';
requireLogin();
requireCafeSetup();
requireRole(['owner']);

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

$message = '';
$message_type = '';

// Handle delete
if (isset($_GET['delete']) && $_GET['delete']) {
    $item_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM menu_items WHERE item_id = ? AND cafe_id = ?");
    if ($stmt->execute([$item_id, $cafe_id])) {
        $message = 'Product deleted successfully';
        $message_type = 'success';
    } else {
        $message = 'Failed to delete product';
        $message_type = 'error';
    }
}

// Auto-update product status based on stock
$stmt = $conn->prepare("UPDATE menu_items SET status = 'unavailable' WHERE cafe_id = ? AND stock <= 0 AND status = 'available'");
$stmt->execute([$cafe_id]);

$stmt = $conn->prepare("UPDATE menu_items SET status = 'available' WHERE cafe_id = ? AND stock > 0 AND status = 'unavailable'");
$stmt->execute([$cafe_id]);

// Get all products with categories
$stmt = $conn->prepare("
    SELECT mi.*, mc.category_name 
    FROM menu_items mi 
    LEFT JOIN menu_categories mc ON mi.category_id = mc.category_id 
    WHERE mi.cafe_id = ? 
    ORDER BY mi.created_at DESC
");
$stmt->execute([$cafe_id]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Products';
include 'includes/header.php';

// Get active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'list';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">Products</h2>

<!-- Products Tabs -->
<div class="subnav-container">
    <div class="subnav-tabs">
        <a href="?tab=list" class="subnav-tab <?php echo $active_tab == 'list' ? 'active' : ''; ?>">
            <i class="fas fa-box"></i>
            <span>Product List</span>
        </a>
        <a href="?tab=variations" class="subnav-tab <?php echo $active_tab == 'variations' ? 'active' : ''; ?>">
            <i class="fas fa-layer-group"></i>
            <span>Variations</span>
        </a>
        <a href="?tab=addons" class="subnav-tab <?php echo $active_tab == 'addons' ? 'active' : ''; ?>">
            <i class="fas fa-plus-circle"></i>
            <span>Add-ons</span>
        </a>
        <a href="?tab=inventory" class="subnav-tab <?php echo $active_tab == 'inventory' ? 'active' : ''; ?>">
            <i class="fas fa-warehouse"></i>
            <span>Inventory</span>
        </a>
    </div>
</div>

<?php if ($active_tab == 'list'): ?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<div class="table-container">
    <div class="table-header">
        <div class="table-title">Product List</div>
        <a href="forms/product_form.php" class="btn btn-primary btn-sm">+ Add Product</a>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>Image</th>
                <th>Name</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-gray);">No products found. <a href="forms/product_form.php" style="color: var(--primary-white);">Add your first product</a></td>
                </tr>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td>
                            <?php 
                            $image_path = null;
                            if (!empty($product['image'])) {
                                // Check if file exists relative to root
                                $check_path = $product['image'];
                                if (file_exists($check_path)) {
                                    $image_path = $product['image'];
                                }
                            }
                            ?>
                            <?php if ($image_path): ?>
                                <img src="<?php echo htmlspecialchars($image_path); ?>" alt="<?php echo htmlspecialchars($product['item_name']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px; border: 1px solid var(--border-gray);">
                            <?php else: ?>
                                <div style="width: 50px; height: 50px; background: var(--accent-gray); border-radius: 5px; display: flex; align-items: center; justify-content: center; color: var(--text-gray); font-size: 20px;">☕</div>
                            <?php endif; ?>
                        </td>
                        <td style="color: var(--primary-white); font-weight: 500;"><?php echo htmlspecialchars($product['item_name']); ?></td>
                        <td><?php echo htmlspecialchars($product['category_name'] ?? 'General'); ?></td>
                        <td><?php echo formatCurrency($product['price']); ?></td>
                        <td><?php echo $product['stock']; ?></td>
                        <td>
                            <span class="badge badge-<?php echo $product['status'] == 'available' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($product['status']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="forms/product_form.php?id=<?php echo $product['item_id']; ?>" class="btn btn-secondary btn-sm">Edit</a>
                            <a href="product_recipes.php?item_id=<?php echo $product['item_id']; ?>" class="btn btn-primary btn-sm">Recipe</a>
                            <a href="?delete=<?php echo $product['item_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this product?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php elseif ($active_tab == 'variations'): ?>
    <!-- Variations Tab Content -->
    <?php
    // Get all variations with their options
    try {
        $stmt = $conn->prepare("
            SELECT v.*, 
                   COUNT(DISTINCT o.option_id) as option_count,
                   COUNT(DISTINCT pva.item_id) as product_count
            FROM product_variations v
            LEFT JOIN variation_options o ON v.variation_id = o.variation_id
            LEFT JOIN product_variation_assignments pva ON v.variation_id = pva.variation_id
            WHERE v.cafe_id = ?
            GROUP BY v.variation_id
            ORDER BY v.display_order, v.variation_name
        ");
        $stmt->execute([$cafe_id]);
        $variations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $variations = [];
    }
    ?>
    
    <div class="table-container">
        <div class="table-header">
            <div class="table-title">Product Variations</div>
            <a href="forms/variation_form.php" class="btn btn-primary btn-sm">Add New Variation</a>
        </div>
        
        <?php if (empty($variations)): ?>
            <div style="padding: 20px; text-align: center; color: var(--text-gray);">
                No variations found. <a href="forms/variation_form.php" style="color: var(--primary-white);">Create your first variation</a>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Variation Name</th>
                        <th>Type</th>
                        <th>Required</th>
                        <th>Options</th>
                        <th>Used in Products</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($variations as $variation): ?>
                        <tr>
                            <td style="font-weight: 600; color: var(--primary-white);"><?php echo htmlspecialchars($variation['variation_name']); ?></td>
                            <td><span class="badge badge-info"><?php echo ucfirst($variation['variation_type']); ?></span></td>
                            <td>
                                <?php if ($variation['is_required']): ?>
                                    <span class="badge badge-warning">Required</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Optional</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $variation['option_count']; ?> options</td>
                            <td><?php echo $variation['product_count']; ?> products</td>
                            <td>
                                <a href="forms/variation_form.php?id=<?php echo $variation['variation_id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                <a href="variation_options.php?variation_id=<?php echo $variation['variation_id']; ?>" class="btn btn-secondary btn-sm">Manage Options</a>
                                <a href="variations.php?delete=<?php echo $variation['variation_id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Are you sure you want to delete this variation? This will also delete all its options.')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

<?php elseif ($active_tab == 'addons'): ?>
    <!-- Add-ons Tab Content -->
    <?php
    // Get all add-ons
    try {
        $stmt = $conn->prepare("
            SELECT a.*, COUNT(DISTINCT paa.item_id) as product_count
            FROM product_addons a
            LEFT JOIN product_addon_assignments paa ON a.addon_id = paa.addon_id
            WHERE a.cafe_id = ?
            GROUP BY a.addon_id
            ORDER BY a.display_order, a.addon_name
        ");
        $stmt->execute([$cafe_id]);
        $addons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $addons = [];
    }
    ?>
    
    <div class="table-container">
        <div class="table-header">
            <div class="table-title">Product Add-ons</div>
            <a href="forms/addon_form.php" class="btn btn-primary btn-sm">Add New Add-on</a>
        </div>
        
        <?php if (empty($addons)): ?>
            <div style="padding: 20px; text-align: center; color: var(--text-gray);">
                No add-ons found. <a href="forms/addon_form.php" style="color: var(--primary-white);">Create your first add-on</a>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Add-on Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Used in Products</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($addons as $addon): ?>
                        <tr>
                            <td style="font-weight: 600; color: var(--primary-white);"><?php echo htmlspecialchars($addon['addon_name']); ?></td>
                            <td><?php echo htmlspecialchars($addon['addon_category'] ?? 'General'); ?></td>
                            <td><?php echo formatCurrency($addon['price']); ?></td>
                            <td><?php echo $addon['product_count']; ?> products</td>
                            <td>
                                <?php if ($addon['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="forms/addon_form.php?id=<?php echo $addon['addon_id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                <a href="addons.php?toggle=<?php echo $addon['addon_id']; ?>" 
                                   class="btn btn-<?php echo $addon['is_active'] ? 'warning' : 'success'; ?> btn-sm"
                                   onclick="return confirm('<?php echo $addon['is_active'] ? 'Deactivate' : 'Activate'; ?> this add-on?')">
                                    <?php echo $addon['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                </a>
                                <a href="addons.php?delete=<?php echo $addon['addon_id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Are you sure you want to delete this add-on?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

<?php elseif ($active_tab == 'inventory'): ?>
    <!-- Inventory Tab Content -->
    <?php
    // Get all raw materials with current stock
    try {
        $stmt = $conn->prepare("
            SELECT m.*, 
                   COALESCE(SUM(CASE WHEN b.is_used = 0 THEN b.quantity ELSE 0 END), 0) as current_stock,
                   MIN(CASE WHEN b.is_used = 0 AND b.expiration_date IS NOT NULL THEN b.expiration_date ELSE NULL END) as nearest_expiration
            FROM raw_materials m
            LEFT JOIN material_batches b ON m.material_id = b.material_id
            WHERE m.cafe_id = ?
            GROUP BY m.material_id
            ORDER BY m.material_name
        ");
        $stmt->execute([$cafe_id]);
        $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $materials = [];
    }
    ?>
    
    <div class="table-container">
        <div class="table-header">
            <div class="table-title">Raw Materials Inventory</div>
            <a href="forms/raw_material_form.php" class="btn btn-primary btn-sm">Add New Material</a>
        </div>
        
        <?php if (empty($materials)): ?>
            <div style="padding: 20px; text-align: center; color: var(--text-gray);">
                No raw materials found. <a href="forms/raw_material_form.php" style="color: var(--primary-white);">Add your first raw material</a>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Material Name</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th>Current Cost</th>
                        <th>Current Stock</th>
                        <th>Min Level</th>
                        <th>Expiration Alert</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materials as $material): ?>
                        <?php
                        $stock_status = 'ok';
                        $expiration_alert = '';
                        if ($material['current_stock'] <= $material['min_stock_level']) {
                            $stock_status = 'low';
                        }
                        if ($material['nearest_expiration']) {
                            $days_until_expiry = (strtotime($material['nearest_expiration']) - time()) / (60 * 60 * 24);
                            if ($days_until_expiry < 0) {
                                $expiration_alert = '<span class="badge badge-danger">Expired</span>';
                            } elseif ($days_until_expiry <= 7) {
                                $expiration_alert = '<span class="badge badge-warning">Expires in ' . ceil($days_until_expiry) . ' days</span>';
                            }
                        }
                        ?>
                        <tr>
                            <td style="font-weight: 600; color: var(--primary-white);"><?php echo htmlspecialchars($material['material_name']); ?></td>
                            <td><?php echo htmlspecialchars($material['material_category'] ?? 'General'); ?></td>
                            <td><?php echo ucfirst($material['unit_type']); ?></td>
                            <td><?php echo formatCurrency($material['current_cost']); ?></td>
                            <td>
                                <span style="color: <?php echo $stock_status == 'low' ? '#dc3545' : 'var(--primary-white)'; ?>; font-weight: 600;">
                                    <?php echo number_format($material['current_stock'], 2); ?>
                                </span>
                            </td>
                            <td><?php echo number_format($material['min_stock_level'], 2); ?></td>
                            <td><?php echo $expiration_alert; ?></td>
                            <td>
                                <?php if ($material['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="forms/raw_material_form.php?id=<?php echo $material['material_id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                <a href="material_batches.php?material_id=<?php echo $material['material_id']; ?>" class="btn btn-secondary btn-sm">Batches</a>
                                <a href="raw_materials.php?toggle=<?php echo $material['material_id']; ?>" 
                                   class="btn btn-<?php echo $material['is_active'] ? 'warning' : 'success'; ?> btn-sm"
                                   onclick="return confirm('<?php echo $material['is_active'] ? 'Deactivate' : 'Activate'; ?> this material?')">
                                    <?php echo $material['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                </a>
                                <a href="raw_materials.php?delete=<?php echo $material['material_id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Are you sure you want to delete this material? This will also delete all batches and recipe associations.')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>

