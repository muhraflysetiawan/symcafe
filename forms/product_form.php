<?php
require_once '../config/config.php';
requireLogin();
requireCafeSetup();
requireRole(['owner']);

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

$error = '';
$success = '';
$product = null;
$is_edit = false;

// Get categories
$stmt = $conn->prepare("SELECT * FROM menu_categories WHERE cafe_id = ?");
$stmt->execute([$cafe_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If no categories, create default
if (empty($categories)) {
    $stmt = $conn->prepare("INSERT INTO menu_categories (cafe_id, category_name) VALUES (?, 'General')");
    $stmt->execute([$cafe_id]);
    $categories = [['category_id' => $conn->lastInsertId(), 'category_name' => 'General']];
}

// Get all variations with options
try {
    $stmt = $conn->prepare("
        SELECT v.*, 
               GROUP_CONCAT(o.option_id ORDER BY o.display_order, o.option_name) as option_ids,
               GROUP_CONCAT(o.option_name ORDER BY o.display_order, o.option_name SEPARATOR '|') as option_names
        FROM product_variations v
        LEFT JOIN variation_options o ON v.variation_id = o.variation_id
        WHERE v.cafe_id = ?
        GROUP BY v.variation_id
        ORDER BY v.display_order, v.variation_name
    ");
    $stmt->execute([$cafe_id]);
    $all_variations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_variations = [];
}

// Get all add-ons
try {
    $stmt = $conn->prepare("SELECT * FROM product_addons WHERE cafe_id = ? AND is_active = 1 ORDER BY display_order, addon_name");
    $stmt->execute([$cafe_id]);
    $all_addons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_addons = [];
}

// Get assigned variations and add-ons for this product (if editing)
$assigned_variations = [];
$assigned_addons = [];
if ($is_edit && isset($product['item_id'])) {
    try {
        $stmt = $conn->prepare("SELECT variation_id FROM product_variation_assignments WHERE item_id = ?");
        $stmt->execute([$product['item_id']]);
        $assigned_variations = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $conn->prepare("SELECT addon_id FROM product_addon_assignments WHERE item_id = ?");
        $stmt->execute([$product['item_id']]);
        $assigned_addons = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        // Tables might not exist yet
    }
}

// Handle edit - get product data
if (isset($_GET['id'])) {
    $item_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM menu_items WHERE item_id = ? AND cafe_id = ?");
    $stmt->execute([$item_id, $cafe_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        $is_edit = true;
    } else {
        header('Location: products.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_name = sanitizeInput($_POST['item_name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $status = sanitizeInput($_POST['status'] ?? 'available');
    
    // Auto-update status based on stock
    if ($stock <= 0) {
        $status = 'unavailable';
    } elseif ($stock > 0 && $status == 'unavailable') {
        $status = 'available';
    }
    
    if (empty($item_name) || $price <= 0) {
        $error = 'Product name and price are required';
    } else {
        // Handle image upload
        $image_path = null;
        
        // Check if image column exists
        $columns = $conn->query("SHOW COLUMNS FROM menu_items")->fetchAll(PDO::FETCH_COLUMN);
        $has_image_column = in_array('image', $columns);
        
        if ($has_image_column) {
            // Keep existing image if no new upload
            if ($is_edit && isset($_GET['id'])) {
                $item_id = (int)$_GET['id'];
                $stmt = $conn->prepare("SELECT image FROM menu_items WHERE item_id = ? AND cafe_id = ?");
                $stmt->execute([$item_id, $cafe_id]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                $image_path = $existing['image'] ?? null;
            }
            
            // Handle new image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/products/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $new_filename = 'product_' . ($is_edit ? $_GET['id'] : 'new') . '_' . time() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                        // Delete old image if exists
                        if ($image_path && file_exists('../' . $image_path)) {
                            unlink('../' . $image_path);
                        }
                        // Store path relative to root (remove ../ prefix)
                        $image_path = 'uploads/products/' . $new_filename;
                    } else {
                        $error = 'Failed to upload image';
                    }
                } else {
                    $error = 'Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.';
                }
            }
        }
        
        if (empty($error)) {
            if ($is_edit && isset($_GET['id'])) {
                $item_id = (int)$_GET['id'];
                if ($has_image_column && $image_path !== null) {
                    $stmt = $conn->prepare("UPDATE menu_items SET item_name = ?, category_id = ?, price = ?, stock = ?, status = ?, image = ? WHERE item_id = ? AND cafe_id = ?");
                    $result = $stmt->execute([$item_name, $category_id, $price, $stock, $status, $image_path, $item_id, $cafe_id]);
                } else {
                    $stmt = $conn->prepare("UPDATE menu_items SET item_name = ?, category_id = ?, price = ?, stock = ?, status = ? WHERE item_id = ? AND cafe_id = ?");
                    $result = $stmt->execute([$item_name, $category_id, $price, $stock, $status, $item_id, $cafe_id]);
                }
                if ($result) {
                    // Handle variation assignments
                    if (isset($_POST['variations']) && is_array($_POST['variations'])) {
                        // Delete existing assignments
                        $stmt = $conn->prepare("DELETE FROM product_variation_assignments WHERE item_id = ?");
                        $stmt->execute([$item_id]);
                        
                        // Insert new assignments
                        $stmt = $conn->prepare("INSERT INTO product_variation_assignments (item_id, variation_id) VALUES (?, ?)");
                        foreach ($_POST['variations'] as $variation_id) {
                            $variation_id = (int)$variation_id;
                            if ($variation_id > 0) {
                                $stmt->execute([$item_id, $variation_id]);
                            }
                        }
                    } else {
                        // Remove all assignments if none selected
                        $stmt = $conn->prepare("DELETE FROM product_variation_assignments WHERE item_id = ?");
                        $stmt->execute([$item_id]);
                    }
                    
                    // Handle add-on assignments
                    if (isset($_POST['addons']) && is_array($_POST['addons'])) {
                        // Delete existing assignments
                        $stmt = $conn->prepare("DELETE FROM product_addon_assignments WHERE item_id = ?");
                        $stmt->execute([$item_id]);
                        
                        // Insert new assignments
                        $stmt = $conn->prepare("INSERT INTO product_addon_assignments (item_id, addon_id) VALUES (?, ?)");
                        foreach ($_POST['addons'] as $addon_id) {
                            $addon_id = (int)$addon_id;
                            if ($addon_id > 0) {
                                $stmt->execute([$item_id, $addon_id]);
                            }
                        }
                    } else {
                        // Remove all assignments if none selected
                        $stmt = $conn->prepare("DELETE FROM product_addon_assignments WHERE item_id = ?");
                        $stmt->execute([$item_id]);
                    }
                    
                    $success = 'Product updated successfully';
                    header('Location: ../products.php');
                    exit();
                } else {
                    $error = 'Failed to update product';
                }
            } else {
                if ($has_image_column) {
                    $stmt = $conn->prepare("INSERT INTO menu_items (cafe_id, category_id, item_name, price, stock, status, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $result = $stmt->execute([$cafe_id, $category_id, $item_name, $price, $stock, $status, $image_path]);
                } else {
                    $stmt = $conn->prepare("INSERT INTO menu_items (cafe_id, category_id, item_name, price, stock, status) VALUES (?, ?, ?, ?, ?, ?)");
                    $result = $stmt->execute([$cafe_id, $category_id, $item_name, $price, $stock, $status]);
                }
                if ($result) {
                    $item_id = $conn->lastInsertId();
                    
                    // Handle variation assignments
                    if (isset($_POST['variations']) && is_array($_POST['variations'])) {
                        $stmt = $conn->prepare("INSERT INTO product_variation_assignments (item_id, variation_id) VALUES (?, ?)");
                        foreach ($_POST['variations'] as $variation_id) {
                            $variation_id = (int)$variation_id;
                            if ($variation_id > 0) {
                                $stmt->execute([$item_id, $variation_id]);
                            }
                        }
                    }
                    
                    // Handle add-on assignments
                    if (isset($_POST['addons']) && is_array($_POST['addons'])) {
                        $stmt = $conn->prepare("INSERT INTO product_addon_assignments (item_id, addon_id) VALUES (?, ?)");
                        foreach ($_POST['addons'] as $addon_id) {
                            $addon_id = (int)$addon_id;
                            if ($addon_id > 0) {
                                $stmt->execute([$item_id, $addon_id]);
                            }
                        }
                    }
                    
                    $success = 'Product added successfully';
                    header('Location: ../products.php');
                    exit();
                } else {
                    $error = 'Failed to add product';
                }
            }
        }
    }
}

$page_title = $is_edit ? 'Edit Product' : 'Add Product';
include '../includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;"><?php echo $is_edit ? 'Edit Product' : 'Add New Product'; ?></h2>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="form-container">
    <form method="POST" enctype="multipart/form-data">
        <div class="form-row">
            <div class="form-group">
                <label for="item_name">Product Name *</label>
                <input type="text" id="item_name" name="item_name" required value="<?php echo htmlspecialchars($product['item_name'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['category_id']; ?>" <?php echo (isset($product['category_id']) && $product['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="price">Price *</label>
                <input type="number" id="price" name="price" step="0.01" min="0" required value="<?php echo $product['price'] ?? ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="stock">Stock</label>
                <input type="number" id="stock" name="stock" min="0" value="<?php echo $product['stock'] ?? 0; ?>">
            </div>
        </div>
        
        <?php
        // Check if image column exists
        $columns = $conn->query("SHOW COLUMNS FROM menu_items")->fetchAll(PDO::FETCH_COLUMN);
        $has_image_column = in_array('image', $columns);
        ?>
        
        <?php if ($has_image_column): ?>
        <div class="form-group">
            <label for="image">Product Image (Optional)</label>
            <?php if ($is_edit && !empty($product['image']) && file_exists('../' . $product['image'])): ?>
                <div style="margin-bottom: 10px;">
                    <img src="../<?php echo htmlspecialchars($product['image']); ?>" alt="Current Image" style="max-width: 200px; max-height: 150px; border: 1px solid var(--border-gray); border-radius: 5px; padding: 5px; object-fit: cover;">
                    <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">Current image</p>
                </div>
            <?php endif; ?>
            <input type="file" id="image" name="image" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
            <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">Recommended: Square image, max 2MB. Formats: JPG, PNG, GIF, WEBP</p>
        </div>
        <?php else: ?>
        <div class="alert alert-warning">
            <strong>Note:</strong> Product image feature is not available yet. Please run the database migration script first.
            <br><a href="../database/migrate_product_images.php" style="color: var(--primary-white); text-decoration: underline;">Run Migration Now</a>
        </div>
        <?php endif; ?>
        
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="available" <?php echo (isset($product['status']) && $product['status'] == 'available') ? 'selected' : ''; ?>>Available</option>
                <option value="unavailable" <?php echo (isset($product['status']) && $product['status'] == 'unavailable') ? 'selected' : ''; ?>>Unavailable</option>
            </select>
        </div>
        
        <!-- Variations Assignment -->
        <?php if (!empty($all_variations)): ?>
        <div class="form-group" style="margin-top: 30px; padding-top: 30px; border-top: 1px solid var(--border-gray);">
            <label style="font-size: 18px; font-weight: 600; color: var(--primary-white); margin-bottom: 15px; display: block;">Product Variations</label>
            <p style="color: var(--text-gray); font-size: 13px; margin-bottom: 20px;">Select which variations customers can choose for this product</p>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px;">
                <?php foreach ($all_variations as $variation): ?>
                    <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer; padding: 15px; background: var(--accent-gray); border: 2px solid var(--border-gray); border-radius: 8px; transition: all 0.3s; position: relative;" 
                           onmouseover="this.style.borderColor='var(--primary-white)'; this.style.background='rgba(255,255,255,0.05)'" 
                           onmouseout="this.style.borderColor='var(--border-gray)'; this.style.background='var(--accent-gray)'">
                        <input type="checkbox" name="variations[]" value="<?php echo $variation['variation_id']; ?>" 
                               style="width: 20px; height: 20px; margin-top: 2px; cursor: pointer;"
                               <?php echo in_array($variation['variation_id'], $assigned_variations) ? 'checked' : ''; ?>>
                        <div style="flex: 1;">
                            <div style="color: var(--primary-white); font-weight: 600; font-size: 16px; margin-bottom: 6px;"><?php echo htmlspecialchars($variation['variation_name']); ?></div>
                            <div style="color: var(--text-gray); font-size: 13px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <?php if ($variation['is_required']): ?>
                                    <span style="background: rgba(255, 193, 7, 0.2); color: #ffc107; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">Required</span>
                                <?php else: ?>
                                    <span style="background: rgba(108, 117, 125, 0.2); color: var(--text-gray); padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">Optional</span>
                                <?php endif; ?>
                                <span style="color: var(--text-gray);">
                                    <?php 
                                    $option_count = !empty($variation['option_names']) ? count(explode('|', $variation['option_names'])) : 0;
                                    echo $option_count . ' option' . ($option_count != 1 ? 's' : '');
                                    ?>
                                </span>
                            </div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Add-ons Assignment -->
        <?php if (!empty($all_addons)): ?>
        <div class="form-group" style="margin-top: 30px; padding-top: 30px; border-top: 1px solid var(--border-gray);">
            <label style="font-size: 18px; font-weight: 600; color: var(--primary-white); margin-bottom: 15px; display: block;">Available Add-ons</label>
            <p style="color: var(--text-gray); font-size: 13px; margin-bottom: 20px;">Select which add-ons customers can add to this product</p>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px;">
                <?php foreach ($all_addons as $addon): ?>
                    <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer; padding: 15px; background: var(--accent-gray); border: 2px solid var(--border-gray); border-radius: 8px; transition: all 0.3s;" 
                           onmouseover="this.style.borderColor='var(--primary-white)'; this.style.background='rgba(255,255,255,0.05)'" 
                           onmouseout="this.style.borderColor='var(--border-gray)'; this.style.background='var(--accent-gray)'">
                        <input type="checkbox" name="addons[]" value="<?php echo $addon['addon_id']; ?>" 
                               style="width: 20px; height: 20px; margin-top: 2px; cursor: pointer;"
                               <?php echo in_array($addon['addon_id'], $assigned_addons) ? 'checked' : ''; ?>>
                        <div style="flex: 1;">
                            <div style="color: var(--primary-white); font-weight: 600; font-size: 16px; margin-bottom: 6px;"><?php echo htmlspecialchars($addon['addon_name']); ?></div>
                            <div style="color: var(--text-gray); font-size: 13px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <?php if (!empty($addon['addon_category'])): ?>
                                    <span style="background: rgba(108, 117, 125, 0.2); color: var(--text-gray); padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;"><?php echo htmlspecialchars($addon['addon_category']); ?></span>
                                <?php endif; ?>
                                <span style="color: var(--primary-white); font-weight: 600; font-size: 14px;"><?php echo formatCurrency($addon['price']); ?></span>
                            </div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div style="display: flex; gap: 10px; margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border-gray);">
            <button type="submit" class="btn btn-primary"><?php echo $is_edit ? 'Update Product' : 'Add Product'; ?></button>
            <a href="../products.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>

