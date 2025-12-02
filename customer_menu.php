<?php
require_once 'config/config.php';
requireLogin();

// Only customers can access this page
if ($_SESSION['user_role'] != 'customer') {
    header('Location: dashboard.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Get selected cafe_id from URL (required)
$cafe_id = isset($_GET['cafe_id']) ? (int)$_GET['cafe_id'] : (isset($_SESSION['selected_cafe_id']) ? $_SESSION['selected_cafe_id'] : 0);

if ($cafe_id <= 0) {
    $_SESSION['error'] = 'Please select a store from the home page';
    header('Location: index.php');
    exit();
}

if ($cafe_id > 0) {
    $_SESSION['selected_cafe_id'] = $cafe_id;
}

// Get selected cafe info
$selected_cafe = null;
$categories = [];
$products_by_category = [];

if ($cafe_id > 0) {
    $stmt = $conn->prepare("SELECT cafe_id, cafe_name, address, description, phone, logo FROM cafes WHERE cafe_id = ?");
    $stmt->execute([$cafe_id]);
    $selected_cafe = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selected_cafe) {
        // Get categories
        $stmt = $conn->prepare("SELECT category_id, category_name FROM menu_categories WHERE cafe_id = ? ORDER BY category_name");
        $stmt->execute([$cafe_id]);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check if reviews table exists
        $reviews_table_exists = false;
        try {
            $conn->query("SELECT 1 FROM product_reviews LIMIT 1");
            $reviews_table_exists = true;
        } catch (Exception $e) {
            $reviews_table_exists = false;
        }
        
        // Get products with reviews data if available
        if ($reviews_table_exists) {
            $stmt = $conn->prepare("
                SELECT 
                    mi.*, 
                    mc.category_name,
                    COALESCE(AVG(pr.rating), 0) as avg_rating,
                    COUNT(pr.review_id) as review_count
                FROM menu_items mi 
                LEFT JOIN menu_categories mc ON mi.category_id = mc.category_id
                LEFT JOIN product_reviews pr ON mi.item_id = pr.item_id
                WHERE mi.cafe_id = ? AND mi.status = 'available' AND mi.stock > 0
                GROUP BY mi.item_id, mc.category_name
                ORDER BY mc.category_name, mi.item_name
            ");
        } else {
            $stmt = $conn->prepare("
                SELECT mi.*, mc.category_name 
                FROM menu_items mi 
                LEFT JOIN menu_categories mc ON mi.category_id = mc.category_id 
                WHERE mi.cafe_id = ? AND mi.status = 'available' AND mi.stock > 0
                ORDER BY mc.category_name, mi.item_name
            ");
        }
        $stmt->execute([$cafe_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get variations and add-ons for all products
        $product_variations = [];
        $product_addons = [];
        try {
            foreach ($products as $product) {
                $item_id = $product['item_id'];
                
                // Get assigned variations with their options
                $stmt = $conn->prepare("
                    SELECT v.variation_id, v.variation_name, v.is_required, v.variation_type,
                           o.option_id, o.option_name, o.price_adjustment, o.is_default
                    FROM product_variation_assignments pva
                    JOIN product_variations v ON pva.variation_id = v.variation_id
                    LEFT JOIN variation_options o ON v.variation_id = o.variation_id
                    WHERE pva.item_id = ?
                    ORDER BY v.display_order, o.display_order, o.option_name
                ");
                $stmt->execute([$item_id]);
                $variations_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Group by variation
                $variations = [];
                foreach ($variations_data as $row) {
                    $var_id = $row['variation_id'];
                    if (!isset($variations[$var_id])) {
                        $variations[$var_id] = [
                            'variation_id' => $var_id,
                            'variation_name' => $row['variation_name'],
                            'is_required' => $row['is_required'],
                            'variation_type' => $row['variation_type'],
                            'options' => []
                        ];
                    }
                    if ($row['option_id']) {
                        $variations[$var_id]['options'][] = [
                            'option_id' => $row['option_id'],
                            'option_name' => $row['option_name'],
                            'price_adjustment' => $row['price_adjustment'],
                            'is_default' => $row['is_default']
                        ];
                    }
                }
                $product_variations[$item_id] = array_values($variations);
                
                // Get assigned add-ons
                $stmt = $conn->prepare("
                    SELECT a.addon_id, a.addon_name, a.addon_category, a.price
                    FROM product_addon_assignments paa
                    JOIN product_addons a ON paa.addon_id = a.addon_id
                    WHERE paa.item_id = ? AND a.is_active = 1
                    ORDER BY a.display_order, a.addon_name
                ");
                $stmt->execute([$item_id]);
                $product_addons[$item_id] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            // Tables might not exist yet
            error_log("Error fetching variations/addons: " . $e->getMessage());
        }
        
        // Group by category
        foreach ($products as $product) {
            $category_name = $product['category_name'] ?? 'General';
            if (!isset($products_by_category[$category_name])) {
                $products_by_category[$category_name] = [];
            }
            $products_by_category[$category_name][] = $product;
        }
    }
}

$page_title = 'Browse Menu';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Customer Navigation -->
    <nav style="background: var(--primary-black); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-gray);">
        <h1 style="color: var(--primary-white); margin: 0; font-size: 24px;"><?php echo APP_NAME; ?></h1>
        <div style="display: flex; gap: 15px; align-items: center;">
            <a href="index.php" style="color: var(--primary-white); text-decoration: none;">Home</a>
            <a href="customer_orders.php" style="color: var(--primary-white); text-decoration: none;">My Orders</a>
            <span style="color: var(--text-gray);"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            <a href="logout.php" style="color: var(--text-gray); text-decoration: none;">Logout</a>
        </div>
    </nav>

<div style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <?php if ($selected_cafe): ?>
        <!-- Store Info -->
        <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px; margin-bottom: 30px; display: flex; gap: 20px; align-items: center;">
            <?php if ($selected_cafe['logo'] && file_exists($selected_cafe['logo'])): ?>
                <img src="<?php echo htmlspecialchars($selected_cafe['logo']); ?>" alt="<?php echo htmlspecialchars($selected_cafe['cafe_name']); ?>" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;">
            <?php endif; ?>
            <div style="flex: 1;">
                <h3 style="color: var(--primary-white); margin: 0 0 10px 0;"><?php echo htmlspecialchars($selected_cafe['cafe_name']); ?></h3>
                <?php if ($selected_cafe['address']): ?>
                    <p style="color: var(--text-gray); margin: 5px 0;">📍 <?php echo htmlspecialchars($selected_cafe['address']); ?></p>
                <?php endif; ?>
                <?php if ($selected_cafe['phone']): ?>
                    <p style="color: var(--text-gray); margin: 5px 0;">📞 <?php echo htmlspecialchars($selected_cafe['phone']); ?></p>
                <?php endif; ?>
                <?php if ($selected_cafe['description']): ?>
                    <p style="color: var(--text-gray); margin: 5px 0;"><?php echo nl2br(htmlspecialchars($selected_cafe['description'])); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Menu Items -->
        <?php if (!empty($products_by_category)): ?>
            <?php foreach ($products_by_category as $category_name => $category_products): ?>
                <div style="margin-bottom: 40px;">
                    <h3 style="color: var(--primary-white); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--border-gray);">
                        <?php echo htmlspecialchars($category_name); ?>
                    </h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">
                        <?php foreach ($category_products as $product): ?>
                            <div style="background: var(--accent-gray); padding: 15px; border-radius: 8px; cursor: pointer; transition: transform 0.2s;" 
                                 onclick="showProductModal(<?php echo $product['item_id']; ?>, '<?php echo htmlspecialchars(addslashes($product['item_name'])); ?>', <?php echo $product['price']; ?>, <?php echo $product['stock']; ?>)">
                                <?php if ($product['image'] && file_exists($product['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['item_name']); ?>" 
                                         style="width: 100%; height: 150px; object-fit: cover; border-radius: 4px; margin-bottom: 10px;">
                                <?php else: ?>
                                    <div style="width: 100%; height: 150px; background: var(--primary-black); border-radius: 4px; margin-bottom: 10px; display: flex; align-items: center; justify-content: center; color: var(--text-gray);">
                                        No Image
                                    </div>
                                <?php endif; ?>
                                <h4 style="color: var(--primary-white); margin: 0 0 5px 0;"><?php echo htmlspecialchars($product['item_name']); ?></h4>
                                <?php if (isset($reviews_table_exists) && $reviews_table_exists && isset($product['avg_rating']) && $product['review_count'] > 0): ?>
                                    <div style="display: flex; align-items: center; gap: 5px; margin: 5px 0;">
                                        <?php
                                        $avg_rating = round($product['avg_rating'], 1);
                                        $full_stars = floor($avg_rating);
                                        $has_half = ($avg_rating - $full_stars) >= 0.5;
                                        ?>
                                        <span style="color: #ffc107; font-size: 14px;">
                                            <?php
                                            for ($i = 0; $i < $full_stars; $i++) {
                                                echo '★';
                                            }
                                            if ($has_half) {
                                                echo '½';
                                            }
                                            for ($i = $full_stars + ($has_half ? 1 : 0); $i < 5; $i++) {
                                                echo '☆';
                                            }
                                            ?>
                                        </span>
                                        <span style="color: var(--text-gray); font-size: 12px;">
                                            <?php echo number_format($avg_rating, 1); ?> (<?php echo $product['review_count']; ?> reviews)
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <p style="color: var(--text-gray); font-size: 14px; margin: 5px 0;">Stock: <?php echo $product['stock']; ?></p>
                                <p style="color: #28a745; font-size: 18px; font-weight: bold; margin: 10px 0 0 0;">
                                    <?php echo formatCurrency($product['price']); ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="padding: 40px; text-align: center; color: var(--text-gray);">
                <p>No products available at this store.</p>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div style="padding: 40px; text-align: center; color: var(--text-gray);">
            <p>Store not found. <a href="index.php" style="color: var(--primary-white);">Go back to home</a></p>
        </div>
    <?php endif; ?>
</div>

<!-- Product Modal -->
<div id="productModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 2000; overflow-y: auto;">
    <div style="max-width: 600px; margin: 50px auto; background: var(--primary-black); border-radius: 10px; padding: 30px; position: relative;">
        <button onclick="closeProductModal()" style="position: absolute; top: 15px; right: 15px; background: none; border: none; color: var(--primary-white); font-size: 30px; cursor: pointer;">&times;</button>
        <h2 id="modalProductName" style="color: var(--primary-white); margin: 0 0 20px 0;"></h2>
        
        <div id="modalVariations" style="margin-bottom: 20px;"></div>
        <div id="modalAddons" style="margin-bottom: 20px;"></div>
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border-gray);">
            <span style="color: var(--primary-white); font-size: 20px; font-weight: bold;">Total:</span>
            <span id="modalTotalPrice" style="color: #28a745; font-size: 24px; font-weight: bold;"></span>
        </div>
        
        <button onclick="addToCartFromModal()" class="btn btn-primary btn-block" style="margin-top: 20px;">Add to Cart</button>
    </div>
</div>

<!-- Shopping Cart Sidebar -->
<div id="cartSidebar" style="position: fixed; right: -400px; top: 0; width: 400px; height: 100vh; background: var(--primary-black); border-left: 1px solid var(--border-gray); z-index: 1000; transition: right 0.3s; overflow-y: auto; padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="color: var(--primary-white); margin: 0;">Shopping Cart</h3>
        <button onclick="closeCart()" style="background: none; border: none; color: var(--primary-white); font-size: 24px; cursor: pointer;">&times;</button>
    </div>
    
    <div id="cartItems" style="margin-bottom: 20px;">
        <p style="color: var(--text-gray); text-align: center;">Cart is empty</p>
    </div>
    
    <div style="border-top: 1px solid var(--border-gray); padding-top: 20px;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
            <span style="color: var(--text-gray);">Subtotal:</span>
            <span style="color: var(--primary-white);" id="cartSubtotal">Rp 0</span>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
            <span style="color: var(--text-gray);">Total:</span>
            <span style="color: #28a745; font-size: 20px; font-weight: bold;" id="cartTotal">Rp 0</span>
        </div>
        <button onclick="checkout()" class="btn btn-primary btn-block" id="checkoutBtn" disabled>Checkout</button>
    </div>
</div>

<!-- Cart Toggle Button -->
<button onclick="toggleCart()" style="position: fixed; bottom: 30px; right: 30px; width: 60px; height: 60px; border-radius: 50%; background: var(--primary-white); color: var(--primary-black); border: none; font-size: 24px; cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.3); z-index: 999;">
    🛒 <span id="cartCount" style="position: absolute; top: -5px; right: -5px; background: #dc3545; color: white; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 12px;">0</span>
</button>

<script>
let cart = JSON.parse(localStorage.getItem('customer_cart') || '[]');
let selectedCafeId = <?php echo $cafe_id; ?>;
let currentProduct = null;

// Product variations and addons data from PHP
const productVariations = <?php echo json_encode($product_variations ?? []); ?>;
const productAddons = <?php echo json_encode($product_addons ?? []); ?>;

function updateCart() {
    localStorage.setItem('customer_cart', JSON.stringify(cart));
    renderCart();
}

function showProductModal(id, name, price, stock) {
    if (selectedCafeId === 0) {
        alert('Please select a store first');
        return;
    }
    
    currentProduct = { id, name, price, stock, variations: {}, addons: [] };
    
    document.getElementById('modalProductName').textContent = name;
    document.getElementById('modalTotalPrice').textContent = formatCurrency(price);
    
    // Show variations
    const variationsDiv = document.getElementById('modalVariations');
    variationsDiv.innerHTML = '';
    
    const variations = productVariations[id] || [];
    if (variations.length > 0) {
        variations.forEach(variation => {
            const variationDiv = document.createElement('div');
            variationDiv.style.marginBottom = '20px';
            variationDiv.innerHTML = `
                <label style="color: var(--primary-white); font-weight: 600; display: block; margin-bottom: 10px;">
                    ${variation.variation_name} ${variation.is_required ? '<span style="color: #ffc107;">*</span>' : ''}
                </label>
                <div style="display: flex; flex-wrap: wrap; gap: 10px;" id="variation_${variation.variation_id}">
                    ${variation.options.map(option => `
                        <label style="display: flex; align-items: center; gap: 8px; padding: 10px 15px; background: var(--accent-gray); border-radius: 5px; cursor: pointer; border: 2px solid transparent; transition: border 0.3s;">
                            <input type="radio" name="variation_${variation.variation_id}" value="${option.option_id}" 
                                   data-price="${option.price_adjustment}" 
                                   data-name="${option.option_name.replace(/'/g, "\\'")}"
                                   ${option.is_default ? 'checked' : ''}
                                   onchange="updateModalVariation(${variation.variation_id}, ${option.option_id}, '${option.option_name.replace(/'/g, "\\'")}', ${option.price_adjustment})" ${variation.is_required ? 'required' : ''}>
                            <span style="color: var(--primary-white);">${option.option_name}</span>
                            ${option.price_adjustment != 0 ? `<span style="color: ${option.price_adjustment > 0 ? '#28a745' : '#dc3545'}; font-size: 12px;">${option.price_adjustment > 0 ? '+' : ''}${formatCurrency(option.price_adjustment)}</span>` : ''}
                        </label>
                    `).join('')}
                </div>
            `;
            variationsDiv.appendChild(variationDiv);
            
            // Set default selection
            const defaultOption = variation.options.find(o => o.is_default) || variation.options[0];
            if (defaultOption) {
                currentProduct.variations[variation.variation_id] = {
                    option_id: defaultOption.option_id,
                    option_name: defaultOption.option_name,
                    price_adjustment: defaultOption.price_adjustment
                };
            }
        });
    }
    
    // Show add-ons
    const addonsDiv = document.getElementById('modalAddons');
    addonsDiv.innerHTML = '';
    
    const addons = productAddons[id] || [];
    if (addons.length > 0) {
        addonsDiv.innerHTML = '<label style="color: var(--primary-white); font-weight: 600; display: block; margin-bottom: 10px;">Add-ons (Optional)</label>';
        addons.forEach(addon => {
            const addonDiv = document.createElement('div');
            addonDiv.style.marginBottom = '10px';
            addonDiv.innerHTML = `
                <label style="display: flex; align-items: center; gap: 10px; padding: 10px; background: var(--accent-gray); border-radius: 5px; cursor: pointer;">
                    <input type="checkbox" value="${addon.addon_id}" 
                           data-name="${addon.addon_name.replace(/'/g, "\\'")}" 
                           data-price="${addon.price}"
                           onchange="updateModalAddon(${addon.addon_id}, '${addon.addon_name.replace(/'/g, "\\'")}', ${addon.price}, this.checked)">
                    <div style="flex: 1;">
                        <span style="color: var(--primary-white); font-weight: 600;">${addon.addon_name}</span>
                        ${addon.addon_category ? `<div style="color: var(--text-gray); font-size: 12px;">${addon.addon_category}</div>` : ''}
                    </div>
                    <span style="color: var(--primary-white); font-weight: 600;">${formatCurrency(addon.price)}</span>
                </label>
            `;
            addonsDiv.appendChild(addonDiv);
        });
    }
    
    updateModalPrice();
    document.getElementById('productModal').style.display = 'block';
}

function closeProductModal() {
    document.getElementById('productModal').style.display = 'none';
    currentProduct = null;
}

function updateModalVariation(variationId, optionId, optionName, priceAdjustment) {
    if (!currentProduct) return;
    currentProduct.variations[variationId] = {
        option_id: optionId,
        option_name: optionName,
        price_adjustment: priceAdjustment
    };
    updateModalPrice();
}

function updateModalAddon(addonId, addonName, addonPrice, isChecked) {
    if (!currentProduct) return;
    if (isChecked) {
        currentProduct.addons.push({
            addon_id: addonId,
            addon_name: addonName,
            price: addonPrice
        });
    } else {
        currentProduct.addons = currentProduct.addons.filter(a => a.addon_id !== addonId);
    }
    updateModalPrice();
}

function updateModalPrice() {
    if (!currentProduct) return;
    
    let total = currentProduct.price;
    
    // Add variation price adjustments
    Object.values(currentProduct.variations).forEach(variation => {
        total += parseFloat(variation.price_adjustment || 0);
    });
    
    // Add add-on prices
    currentProduct.addons.forEach(addon => {
        total += parseFloat(addon.price || 0);
    });
    
    document.getElementById('modalTotalPrice').textContent = formatCurrency(total);
}

function generateCartKey(productId, variations, addons) {
    const varKeys = Object.keys(variations).sort().map(vid => `${vid}:${variations[vid].option_id}`).join(',');
    const addonKeys = addons.sort((a, b) => a.addon_id - b.addon_id).map(a => a.addon_id).join(',');
    return `${productId}_${varKeys}_${addonKeys}`;
}

function addToCartFromModal() {
    if (!currentProduct) return;
    
    // Validate required variations
    const variations = productVariations[currentProduct.id] || [];
    const requiredVariations = variations.filter(v => v.is_required);
    
    for (const variation of requiredVariations) {
        if (!currentProduct.variations[variation.variation_id]) {
            alert(`Please select ${variation.variation_name}`);
            return;
        }
    }
    
    // Calculate final price
    let finalPrice = currentProduct.price;
    Object.values(currentProduct.variations).forEach(v => {
        finalPrice += parseFloat(v.price_adjustment || 0);
    });
    currentProduct.addons.forEach(a => {
        finalPrice += parseFloat(a.price || 0);
    });
    
    // Convert variations object to array format for submission
    const variationsArray = [];
    Object.keys(currentProduct.variations).forEach(variationId => {
        const variation = currentProduct.variations[variationId];
        variationsArray.push({
            variation_id: parseInt(variationId),
            option_id: variation.option_id,
            option_name: variation.option_name,
            price_adjustment: variation.price_adjustment
        });
    });
    
    // Generate unique cart key
    const cartKey = generateCartKey(currentProduct.id, currentProduct.variations, currentProduct.addons);
    
    // Check if item with same variations/addons already in cart
    const existingItem = cart.find(item => item.cartKey === cartKey);
    if (existingItem) {
        existingItem.quantity++;
    } else {
        // Add to cart with unique identifier for items with different options
        const cartItem = {
            id: currentProduct.id,
            name: currentProduct.name,
            price: finalPrice,
            basePrice: currentProduct.price,
            stock: currentProduct.stock,
            quantity: 1,
            variations: variationsArray,
            addons: [...currentProduct.addons],
            cartKey: cartKey
        };
        cart.push(cartItem);
    }
    
    updateCart();
    closeProductModal();
    toggleCart();
}

// Legacy function for backwards compatibility
function addToCart(itemId, itemName, price) {
    if (selectedCafeId === 0) {
        alert('Please select a store first');
        return;
    }
    
    const existingItem = cart.find(item => item.id === itemId && !item.variations && !item.addons);
    if (existingItem) {
        existingItem.quantity++;
    } else {
        cart.push({
            id: itemId,
            name: itemName,
            price: price,
            quantity: 1,
            cartKey: itemId.toString()
        });
    }
    updateCart();
    toggleCart();
}

function removeFromCart(itemId) {
    cart = cart.filter(item => item.id !== itemId || item.cartKey);
    updateCart();
}

function removeFromCartByIndex(index) {
    cart.splice(index, 1);
    updateCart();
}

function updateQuantity(itemId, change) {
    const item = cart.find(item => item.id === itemId && (!item.cartKey || item.cartKey === itemId.toString()));
    if (item) {
        item.quantity += change;
        if (item.quantity <= 0) {
            cart = cart.filter(i => i !== item);
        }
        updateCart();
    }
}

function updateQuantityByIndex(index, change) {
    if (cart[index]) {
        cart[index].quantity += change;
        if (cart[index].quantity <= 0) {
            cart.splice(index, 1);
        }
        updateCart();
    }
}

function renderCart() {
    const cartItems = document.getElementById('cartItems');
    const cartCount = document.getElementById('cartCount');
    const checkoutBtn = document.getElementById('checkoutBtn');
    
    cartCount.textContent = cart.reduce((sum, item) => sum + item.quantity, 0);
    
    if (cart.length === 0) {
        cartItems.innerHTML = '<p style="color: var(--text-gray); text-align: center;">Cart is empty</p>';
        checkoutBtn.disabled = true;
        return;
    }
    
    checkoutBtn.disabled = false;
    
    let subtotal = 0;
    let html = '';
    
    cart.forEach((item, index) => {
        const itemTotal = item.price * item.quantity;
        subtotal += itemTotal;
        let variationsText = '';
        if (item.variations && item.variations.length > 0) {
            variationsText = '<div style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">' + 
                item.variations.map(v => v.option_name).join(', ') + '</div>';
        }
        let addonsText = '';
        if (item.addons && item.addons.length > 0) {
            addonsText = '<div style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">Add-ons: ' + 
                item.addons.map(a => a.addon_name).join(', ') + '</div>';
        }
        html += `
            <div style="background: var(--accent-gray); padding: 15px; border-radius: 8px; margin-bottom: 10px;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                    <div style="flex: 1;">
                        <h4 style="color: var(--primary-white); margin: 0 0 5px 0; font-size: 16px;">${item.name}</h4>
                        <p style="color: var(--text-gray); margin: 0; font-size: 14px;">${formatCurrency(item.price)} each</p>
                        ${variationsText}
                        ${addonsText}
                    </div>
                    <button onclick="removeFromCartByIndex(${index})" style="background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">×</button>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <button onclick="updateQuantityByIndex(${index}, -1)" style="background: var(--accent-gray); color: var(--primary-white); border: 1px solid var(--border-gray); width: 30px; height: 30px; border-radius: 4px; cursor: pointer;">-</button>
                        <span style="color: var(--primary-white); min-width: 30px; text-align: center;">${item.quantity}</span>
                        <button onclick="updateQuantityByIndex(${index}, 1)" style="background: var(--accent-gray); color: var(--primary-white); border: 1px solid var(--border-gray); width: 30px; height: 30px; border-radius: 4px; cursor: pointer;">+</button>
                    </div>
                    <span style="color: #28a745; font-weight: bold;">${formatCurrency(itemTotal)}</span>
                </div>
            </div>
        `;
    });
    
    cartItems.innerHTML = html;
    document.getElementById('cartSubtotal').textContent = formatCurrency(subtotal);
    document.getElementById('cartTotal').textContent = formatCurrency(subtotal);
}

function formatCurrency(amount) {
    return 'Rp ' + amount.toLocaleString('id-ID');
}

function toggleCart() {
    const sidebar = document.getElementById('cartSidebar');
    if (sidebar.style.right === '0px') {
        sidebar.style.right = '-400px';
    } else {
        sidebar.style.right = '0px';
    }
}

function closeCart() {
    document.getElementById('cartSidebar').style.right = '-400px';
}

function checkout() {
    if (cart.length === 0) {
        alert('Please add items to cart');
        return;
    }
    
    if (selectedCafeId === 0) {
        alert('Store not selected. Please go back to home and select a store.');
        window.location.href = 'index.php';
        return;
    }
    
    // Disable checkout button and show loading state
    const checkoutBtn = document.getElementById('checkoutBtn');
    if (checkoutBtn) {
        checkoutBtn.disabled = true;
        checkoutBtn.textContent = 'Loading...';
    }
    
    // Create a form to submit cart data to checkout page
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'customer_checkout.php';
    
    // Add cart data as hidden input
    const cartInput = document.createElement('input');
    cartInput.type = 'hidden';
    cartInput.name = 'cart_data';
    cartInput.value = JSON.stringify(cart);
    form.appendChild(cartInput);
    
    // Store cart in session via localStorage (for backup)
    localStorage.setItem('customer_cart', JSON.stringify(cart));
    
    // Submit form
    document.body.appendChild(form);
    form.submit();
}

// Initialize cart on page load
renderCart();
</script>

</body>
</html>

