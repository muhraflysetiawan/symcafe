<?php
require_once 'config/config.php';
requireLogin();
requireCafeSetup();

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

// Get all available products
$stmt = $conn->prepare("SELECT * FROM menu_items WHERE cafe_id = ? AND status = 'available' ORDER BY item_name");
$stmt->execute([$cafe_id]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get variations and add-ons for all products
$product_variations = [];
$product_addons = [];
try {
    // Get variations for each product
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

// Get active payment categories
$payment_categories = [];
try {
    $stmt = $conn->prepare("SELECT category_name FROM payment_categories WHERE cafe_id = ? AND is_active = 1 ORDER BY category_name");
    $stmt->execute([$cafe_id]);
    $payment_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // Table might not exist, use defaults
    $payment_categories = [];
}

// Get active vouchers for display
$active_vouchers = [];
try {
    $stmt = $conn->prepare("
        SELECT voucher_id, voucher_code, discount_amount, min_order_amount, max_order_amount, applicable_products, usage_limit, used_count, valid_from, valid_until 
        FROM vouchers 
        WHERE cafe_id = ? AND is_active = 1 
        AND (valid_from IS NULL OR valid_from <= CURDATE())
        AND (valid_until IS NULL OR valid_until >= CURDATE())
        AND (usage_limit IS NULL OR used_count < usage_limit)
        ORDER BY discount_amount DESC
    ");
    $stmt->execute([$cafe_id]);
    $active_vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist
    $active_vouchers = [];
}

// Get tax percentage from cafe settings
$tax_percentage = 10.00; // Default
try {
    $columns = $conn->query("SHOW COLUMNS FROM cafe_settings")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('tax_percentage', $columns)) {
        $stmt = $conn->prepare("SELECT tax_percentage FROM cafe_settings WHERE cafe_id = ?");
        $stmt->execute([$cafe_id]);
        $tax_setting = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($tax_setting && isset($tax_setting['tax_percentage']) && $tax_setting['tax_percentage'] !== null) {
            $tax_percentage = (float)$tax_setting['tax_percentage'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching tax percentage: " . $e->getMessage());
}

$page_title = 'POS / Transactions';
include 'includes/header.php';

// Display error message if any
$error = '';
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">Point of Sale</h2>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="pos-container">
    <div class="pos-products">
        <h3 style="color: var(--primary-white); margin-bottom: 15px;">Products</h3>
        <div class="product-grid" id="productGrid">
            <?php foreach ($products as $product): ?>
                <div class="product-card <?php echo $product['stock'] <= 0 ? 'unavailable' : ''; ?>" 
                     data-id="<?php echo $product['item_id']; ?>"
                     data-name="<?php echo htmlspecialchars($product['item_name']); ?>"
                     data-price="<?php echo $product['price']; ?>"
                     data-stock="<?php echo $product['stock']; ?>">
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
                        <div class="product-image">
                            <img src="<?php echo htmlspecialchars($image_path); ?>" alt="<?php echo htmlspecialchars($product['item_name']); ?>">
                        </div>
                    <?php endif; ?>
                    <div class="product-name"><?php echo htmlspecialchars($product['item_name']); ?></div>
                    <div class="product-price"><?php echo formatCurrency($product['price']); ?></div>
                    <div class="product-stock">Stock: <?php echo $product['stock']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="pos-cart">
        <h3 style="color: var(--primary-white); margin-bottom: 15px;">Cart</h3>
        <form id="posForm" method="POST" action="process_transaction.php">
            <div id="cartItems"></div>
            
            <div class="cart-summary">
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span id="subtotal">Rp 0</span>
                </div>
                <div class="form-group" style="margin-top: 15px;">
                    <label for="voucher_code">Voucher Code (Optional)</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="voucher_code" name="voucher_code" placeholder="Enter voucher code" 
                               style="flex: 1; text-transform: uppercase;" onkeyup="this.value = this.value.toUpperCase(); validateVoucher()">
                        <button type="button" class="btn btn-secondary" onclick="validateVoucher()">Apply</button>
                    </div>
                    <div id="voucherMessage" style="margin-top: 5px; font-size: 12px;"></div>
                    <input type="hidden" id="voucher_discount" name="voucher_discount" value="0">
                    <input type="hidden" id="voucher_id" name="voucher_id" value="">
                </div>
                <div class="form-group">
                    <label for="tax">Tax (%) <span style="color: var(--text-gray); font-size: 12px; font-weight: normal;">(Set by owner in settings)</span></label>
                    <input type="number" id="tax" name="tax" step="0.01" min="0" value="<?php echo htmlspecialchars($tax_percentage); ?>" readonly style="background-color: var(--accent-gray); color: var(--text-gray); cursor: not-allowed;" onchange="calculateTotal()">
                    <?php if ($_SESSION['user_role'] == 'owner'): ?>
                        <p style="color: var(--text-gray); font-size: 11px; margin-top: 5px; margin-bottom: 0;">
                            <a href="dashboard.php?tab=settings" style="color: var(--primary-white); text-decoration: underline;">Change tax percentage in Dashboard Settings</a>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="summary-row total">
                    <span>Total:</span>
                    <span id="total">Rp 0</span>
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <label for="customer_name">Customer Name (Optional)</label>
                    <input type="text" id="customer_name" name="customer_name" placeholder="Enter customer name">
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <label for="order_type">Order Type</label>
                    <select id="order_type" name="order_type" required>
                        <option value="dine-in">Dine In</option>
                        <option value="take-away">Take Away</option>
                        <option value="online">Online</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <label for="payment_method">Payment Method</label>
                    <select id="payment_method" name="payment_method" required onchange="handlePaymentMethodChange()">
                        <?php if (empty($payment_categories)): ?>
                            <option value="cash">Cash</option>
                            <option value="qris">QRIS</option>
                            <option value="debit">Debit Card</option>
                            <option value="credit">Credit Card</option>
                        <?php else: ?>
                            <?php foreach ($payment_categories as $method): ?>
                                <option value="<?php echo htmlspecialchars($method); ?>"><?php echo htmlspecialchars($method); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <!-- Cash Payment Fields (hidden by default) -->
                <div id="cashPaymentFields" style="display: none; margin-top: 15px;">
                    <div class="form-group">
                        <label for="cash_amount_given">Amount Received *</label>
                        <input type="number" id="cash_amount_given" name="cash_amount_given" step="0.01" min="0" placeholder="Enter amount received" oninput="calculatePOSChange()">
                    </div>
                    <div style="padding: 10px; background: var(--accent-gray); border-radius: 5px; margin-top: 10px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span style="color: var(--text-gray);">Total:</span>
                            <span id="posOrderTotal" style="color: var(--primary-white); font-weight: bold;">Rp 0</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-gray);">Change:</span>
                            <span id="posChangeAmount" style="color: var(--primary-white); font-weight: bold;">Rp 0</span>
                        </div>
                    </div>
                </div>
                
                <input type="hidden" id="cash_change_amount" name="cash_change_amount" value="0">
                <input type="hidden" id="cartData" name="cart_data">
                <input type="hidden" id="subtotalValue" name="subtotal_value">
                <input type="hidden" id="discountValue" name="discount_value">
                <input type="hidden" id="taxValue" name="tax_value">
                <input type="hidden" id="totalValue" name="total_value">
                
                <button type="submit" class="btn btn-primary btn-full" style="margin-top: 20px;" id="checkoutBtn" disabled>Process Payment</button>
                <button type="button" class="btn btn-secondary btn-full" style="margin-top: 10px;" onclick="clearCart()">Clear Cart</button>
            </div>
        </form>
    </div>
</div>

<!-- Product Options Modal -->
<div id="productModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000; overflow-y: auto;">
    <div style="max-width: 600px; margin: 50px auto; background: var(--primary-black); border: 1px solid var(--border-gray); border-radius: 10px; padding: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 id="modalProductName" style="color: var(--primary-white); margin: 0; font-size: 24px;"></h3>
            <button onclick="closeProductModal()" style="background: none; border: none; color: var(--primary-white); font-size: 28px; cursor: pointer; padding: 0; width: 30px; height: 30px;">&times;</button>
        </div>
        
        <div id="modalVariations" style="margin-bottom: 30px;"></div>
        <div id="modalAddons" style="margin-bottom: 30px;"></div>
        
        <div style="border-top: 1px solid var(--border-gray); padding-top: 20px; margin-top: 20px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                <span style="color: var(--primary-white); font-size: 18px; font-weight: 600;">Total Price:</span>
                <span id="modalTotalPrice" style="color: var(--primary-white); font-size: 20px; font-weight: bold;"></span>
            </div>
            <div style="display: flex; gap: 10px;">
                <button onclick="addToCartFromModal()" class="btn btn-primary" style="flex: 1;">Add to Cart</button>
                <button onclick="closeProductModal()" class="btn btn-secondary">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
let cart = [];
let currentProduct = null;
let productVariations = <?php echo json_encode($product_variations); ?>;
let productAddons = <?php echo json_encode($product_addons); ?>;

document.querySelectorAll('.product-card').forEach(card => {
    if (!card.classList.contains('unavailable')) {
        card.addEventListener('click', function() {
            const id = parseInt(this.dataset.id);
            const name = this.dataset.name;
            const price = parseFloat(this.dataset.price);
            const stock = parseInt(this.dataset.stock);
            
            // Check if product has variations or add-ons
            const hasVariations = productVariations[id] && productVariations[id].length > 0;
            const hasAddons = productAddons[id] && productAddons[id].length > 0;
            
            if (hasVariations || hasAddons) {
                showProductModal(id, name, price, stock);
            } else {
                // Simple product, add directly
                addToCart(id, name, price, stock);
            }
        });
    }
});

function showProductModal(id, name, price, stock) {
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
                                   data-name="${option.option_name}"
                                   ${option.is_default ? 'checked' : ''}
                                   onchange="updateModalVariation(${variation.variation_id}, ${option.option_id}, '${option.option_name}', ${option.price_adjustment})" ${variation.is_required ? 'required' : ''}>
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
                           data-name="${addon.addon_name}" 
                           data-price="${addon.price}"
                           onchange="updateModalAddon(${addon.addon_id}, '${addon.addon_name}', ${addon.price}, this.checked)">
                    <div style="flex: 1;">
                        <span style="color: var(--primary-white); font-weight: 600;">${addon.addon_name}</span>
                        ${addon.addon_category ? `<div style="color: var(--text-gray); font-size: 12px;">${addon.addon_category}</div>` : ''}
                    </div>
                    <span style="color: var(--primary-white); font-weight: 600;">${formatCurrency(addon.price)}</span>
                </label>
            `;
            addonsDiv.appendChild(addonDiv);
        });
    } else {
        addonsDiv.innerHTML = '';
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
        cartKey: generateCartKey(currentProduct.id, currentProduct.variations, currentProduct.addons)
    };
    
    cart.push(cartItem);
    updateCart();
    closeProductModal();
}

function generateCartKey(productId, variations, addons) {
    const varStr = JSON.stringify(variations);
    const addonStr = JSON.stringify(addons.sort((a, b) => a.addon_id - b.addon_id));
    return `${productId}_${varStr}_${addonStr}`;
}

function addToCart(id, name, price, stock) {
    // Simple add to cart for products without variations/addons
    const existingItem = cart.find(item => item.id === id && !item.variations && !item.addons);
    
    if (existingItem) {
        if (existingItem.quantity < stock) {
            existingItem.quantity++;
        } else {
            alert('Stock limit reached');
            return;
        }
    } else {
        cart.push({ id, name, price, stock, quantity: 1 });
    }
    
    updateCart();
}


function updateCart() {
    const cartItems = document.getElementById('cartItems');
    cartItems.innerHTML = '';
    
    if (cart.length === 0) {
        cartItems.innerHTML = '<p style="color: var(--text-gray); text-align: center;">Cart is empty</p>';
        document.getElementById('checkoutBtn').disabled = true;
        // Clear voucher when cart is empty
        document.getElementById('voucher_code').value = '';
        document.getElementById('voucher_discount').value = 0;
        document.getElementById('voucher_id').value = '';
        document.getElementById('voucherMessage').innerHTML = '';
        calculateTotal();
        return;
    }
    
    document.getElementById('checkoutBtn').disabled = false;
    
    cart.forEach((item, index) => {
        const cartItem = document.createElement('div');
        cartItem.className = 'cart-item';
        
        // Build variations display
        let variationsHtml = '';
        if (item.variations && Object.keys(item.variations).length > 0) {
            const variationTexts = Object.values(item.variations).map(v => v.option_name);
            variationsHtml = `<div style="font-size: 12px; color: var(--text-gray); margin-top: 5px;">${variationTexts.join(', ')}</div>`;
        }
        
        // Build add-ons display
        let addonsHtml = '';
        if (item.addons && item.addons.length > 0) {
            const addonTexts = item.addons.map(a => `${a.addon_name} (+${formatCurrency(a.price)})`);
            addonsHtml = `<div style="font-size: 12px; color: var(--text-gray); margin-top: 5px;">+ ${addonTexts.join(', ')}</div>`;
        }
        
        cartItem.innerHTML = `
            <div class="cart-item-info">
                <div class="cart-item-name">${item.name}</div>
                ${variationsHtml}
                ${addonsHtml}
                <div class="cart-item-price">${formatCurrency(item.price)} x ${item.quantity}</div>
            </div>
            <div class="cart-item-controls">
                <div class="quantity-control">
                    <button type="button" class="quantity-btn" onclick="updateQuantity(${index}, -1)">-</button>
                    <input type="number" class="quantity-input" value="${item.quantity}" min="1" max="${item.stock}" onchange="setQuantity(${index}, this.value)">
                    <button type="button" class="quantity-btn" onclick="updateQuantity(${index}, 1)">+</button>
                </div>
                <button type="button" class="btn btn-danger btn-sm" onclick="removeFromCart(${index})">Remove</button>
            </div>
        `;
        cartItems.appendChild(cartItem);
    });
    
    // Re-validate voucher when cart changes
    if (document.getElementById('voucher_code').value) {
        validateVoucher();
    } else {
        calculateTotal();
    }
}

function setQuantity(index, quantity) {
    if (cart[index]) {
        quantity = parseInt(quantity);
        if (quantity < 1) quantity = 1;
        if (quantity > cart[index].stock) quantity = cart[index].stock;
        cart[index].quantity = quantity;
        updateCart();
    }
}

function updateQuantity(index, change) {
    if (cart[index]) {
        const newQuantity = cart[index].quantity + change;
        if (newQuantity >= 1 && newQuantity <= cart[index].stock) {
            cart[index].quantity = newQuantity;
            updateCart();
        }
    }
}

function removeFromCart(index) {
    if (cart[index]) {
        cart.splice(index, 1);
        updateCart();
    }
}

// Available vouchers data
const availableVouchers = <?php echo json_encode($active_vouchers); ?>;

function validateVoucher() {
    const voucherCode = document.getElementById('voucher_code').value.trim().toUpperCase();
    const voucherMessage = document.getElementById('voucherMessage');
    const voucherDiscount = document.getElementById('voucher_discount');
    const voucherId = document.getElementById('voucher_id');
    
    if (!voucherCode) {
        voucherMessage.innerHTML = '';
        voucherDiscount.value = 0;
        voucherId.value = '';
        calculateTotal();
        return;
    }
    
    // Find matching voucher
    const voucher = availableVouchers.find(v => v.voucher_code === voucherCode);
    
    if (!voucher) {
        voucherMessage.innerHTML = '<span style="color: #dc3545;">Invalid voucher code</span>';
        voucherDiscount.value = 0;
        voucherId.value = '';
        calculateTotal();
        return;
    }
    
    // Calculate current subtotal
    let subtotal = 0;
    cart.forEach(item => {
        subtotal += item.price * item.quantity;
    });
    
    // Check minimum order amount
    if (voucher.min_order_amount > 0 && subtotal < parseFloat(voucher.min_order_amount)) {
        voucherMessage.innerHTML = '<span style="color: #ffc107;">Minimum order: ' + formatCurrency(voucher.min_order_amount) + '</span>';
        voucherDiscount.value = 0;
        voucherId.value = '';
        calculateTotal();
        return;
    }
    
    // Check maximum order amount
    if (voucher.max_order_amount && subtotal > parseFloat(voucher.max_order_amount)) {
        voucherMessage.innerHTML = '<span style="color: #ffc107;">Maximum order: ' + formatCurrency(voucher.max_order_amount) + '</span>';
        voucherDiscount.value = 0;
        voucherId.value = '';
        calculateTotal();
        return;
    }
    
    // Check applicable products if specified
    if (voucher.applicable_products && voucher.applicable_products !== null && voucher.applicable_products !== '') {
        try {
            const applicableProducts = JSON.parse(voucher.applicable_products);
            if (Array.isArray(applicableProducts) && applicableProducts.length > 0) {
                const cartProductIds = cart.map(item => item.id);
                const hasApplicableProduct = cartProductIds.some(id => applicableProducts.includes(parseInt(id)));
                
                if (!hasApplicableProduct) {
                    voucherMessage.innerHTML = '<span style="color: #ffc107;">Voucher not applicable to selected products</span>';
                    voucherDiscount.value = 0;
                    voucherId.value = '';
                    calculateTotal();
                    return;
                }
            }
        } catch (e) {
            // Invalid JSON, skip product validation
        }
    }
    
    // Voucher is valid
    voucherMessage.innerHTML = '<span style="color: #28a745;">✓ Discount: ' + formatCurrency(parseFloat(voucher.discount_amount)) + '</span>';
    voucherDiscount.value = voucher.discount_amount;
    voucherId.value = voucher.voucher_id || '';
    calculateTotal();
}

function calculateTotal() {
    let subtotal = 0;
    cart.forEach(item => {
        subtotal += item.price * item.quantity;
    });
    
    const voucherDiscount = parseFloat(document.getElementById('voucher_discount').value) || 0;
    const taxPercent = parseFloat(document.getElementById('tax').value) || 0;
    
    const afterDiscount = subtotal - voucherDiscount;
    const tax = (afterDiscount * taxPercent) / 100;
    const total = afterDiscount + tax;
    
    document.getElementById('subtotal').textContent = formatCurrency(subtotal);
    document.getElementById('total').textContent = formatCurrency(total);
    
    // Show discount if voucher applied
    const discountRow = document.getElementById('discountRow');
    if (voucherDiscount > 0) {
        if (!discountRow) {
            const summary = document.querySelector('.cart-summary');
            const subtotalRow = summary.querySelector('.summary-row');
            const discountDiv = document.createElement('div');
            discountDiv.id = 'discountRow';
            discountDiv.className = 'summary-row';
            discountDiv.innerHTML = '<span>Voucher Discount:</span><span style="color: #28a745;">-' + formatCurrency(voucherDiscount) + '</span>';
            subtotalRow.after(discountDiv);
        } else {
            discountRow.innerHTML = '<span>Voucher Discount:</span><span style="color: #28a745;">-' + formatCurrency(voucherDiscount) + '</span>';
        }
    } else {
        if (discountRow) {
            discountRow.remove();
        }
    }
    
    // Prepare cart data for submission (include variations and addons)
    const cartDataForSubmit = cart.map(item => ({
        id: item.id,
        name: item.name,
        price: item.price,
        quantity: item.quantity,
        variations: item.variations || {},
        addons: item.addons || []
    }));
    
    document.getElementById('cartData').value = JSON.stringify(cartDataForSubmit);
    document.getElementById('subtotalValue').value = subtotal;
    document.getElementById('discountValue').value = voucherDiscount;
    document.getElementById('taxValue').value = tax;
    document.getElementById('totalValue').value = total;
}

function clearCart() {
    if (confirm('Are you sure you want to clear the cart?')) {
        cart = [];
        updateCart();
        document.getElementById('voucher_code').value = '';
        document.getElementById('voucher_discount').value = 0;
        document.getElementById('voucher_id').value = '';
        document.getElementById('voucherMessage').innerHTML = '';
        // Tax is read-only and set by owner in settings, so don't reset it
    }
}

function formatCurrency(amount) {
    return 'Rp ' + amount.toLocaleString('id-ID');
}

function handlePaymentMethodChange() {
    const paymentMethod = document.getElementById('payment_method').value.toLowerCase();
    const cashFields = document.getElementById('cashPaymentFields');
    const cashAmountInput = document.getElementById('cash_amount_given');
    
    // Check if payment method is cash (case insensitive)
    const isCash = paymentMethod.includes('cash') || paymentMethod === 'cash';
    
    if (isCash) {
        cashFields.style.display = 'block';
        if (cashAmountInput) {
            cashAmountInput.required = true;
        }
        calculatePOSChange();
    } else {
        cashFields.style.display = 'none';
        if (cashAmountInput) {
            cashAmountInput.required = false;
            cashAmountInput.value = '';
        }
        const changeField = document.getElementById('cash_change_amount');
        if (changeField) {
            changeField.value = '0';
        }
        const changeDisplay = document.getElementById('posChangeAmount');
        if (changeDisplay) {
            changeDisplay.textContent = 'Rp 0';
        }
    }
}

function calculatePOSChange() {
    const cashAmountInput = document.getElementById('cash_amount_given');
    const totalValueInput = document.getElementById('totalValue');
    const changeField = document.getElementById('cash_change_amount');
    const changeDisplay = document.getElementById('posChangeAmount');
    const totalDisplay = document.getElementById('posOrderTotal');
    
    if (!cashAmountInput || !totalValueInput || !changeField || !changeDisplay || !totalDisplay) {
        return;
    }
    
    const amountReceived = parseFloat(cashAmountInput.value) || 0;
    const totalValue = parseFloat(totalValueInput.value) || 0;
    const change = Math.max(0, amountReceived - totalValue);
    
    totalDisplay.textContent = formatCurrency(totalValue);
    changeDisplay.textContent = formatCurrency(change);
    changeField.value = change;
    
    // Highlight change if insufficient amount
    if (amountReceived > 0 && amountReceived < totalValue) {
        changeDisplay.style.color = '#dc3545';
    } else {
        changeDisplay.style.color = 'var(--primary-white)';
    }
}

// Update change calculation when total changes
const originalCalculateTotal = calculateTotal;
calculateTotal = function() {
    originalCalculateTotal();
    const cashFields = document.getElementById('cashPaymentFields');
    if (cashFields && cashFields.style.display !== 'none') {
        calculatePOSChange();
    }
};

// Validate cash payment before form submission
document.getElementById('posForm').addEventListener('submit', function(e) {
    const paymentMethod = document.getElementById('payment_method').value.toLowerCase();
    const isCash = paymentMethod.includes('cash') || paymentMethod === 'cash';
    
    if (isCash) {
        const cashAmountInput = document.getElementById('cash_amount_given');
        const totalValueInput = document.getElementById('totalValue');
        
        if (cashAmountInput && totalValueInput) {
            const amountReceived = parseFloat(cashAmountInput.value) || 0;
            const total = parseFloat(totalValueInput.value) || 0;
            
            if (!amountReceived || amountReceived <= 0) {
                e.preventDefault();
                alert('Please enter the amount received for cash payment');
                cashAmountInput.focus();
                return false;
            }
            
            if (amountReceived < total) {
                e.preventDefault();
                alert('Amount received is less than order total. Please enter correct amount.');
                cashAmountInput.focus();
                return false;
            }
        }
    }
});

// Initialize
updateCart();
</script>

<?php include 'includes/footer.php'; ?>

