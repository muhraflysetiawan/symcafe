<?php
require_once 'config/config.php';

$db = new Database();
$conn = $db->getConnection();

// Get cafe_id from URL
$cafe_id = isset($_GET['cafe_id']) ? (int)$_GET['cafe_id'] : 0;

if (!$cafe_id) {
    header('Location: index.php');
    exit();
}

// Get café information
$stmt = $conn->prepare("SELECT cafe_id, cafe_name, address, description, phone, logo FROM cafes WHERE cafe_id = ?");
$stmt->execute([$cafe_id]);
$cafe = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cafe) {
    header('Location: index.php');
    exit();
}

// Get categories for this café
$stmt = $conn->prepare("SELECT category_id, category_name FROM menu_categories WHERE cafe_id = ? ORDER BY category_name");
$stmt->execute([$cafe_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all products for this café
$stmt = $conn->prepare("
    SELECT mi.*, mc.category_name 
    FROM menu_items mi 
    LEFT JOIN menu_categories mc ON mi.category_id = mc.category_id 
    WHERE mi.cafe_id = ? AND mi.status = 'available' AND mi.stock > 0
    ORDER BY mc.category_name, mi.item_name
");
$stmt->execute([$cafe_id]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group products by category
$products_by_category = [];
foreach ($products as $product) {
    $category_name = $product['category_name'] ?? 'General';
    if (!isset($products_by_category[$category_name])) {
        $products_by_category[$category_name] = [];
    }
    $products_by_category[$category_name][] = $product;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($cafe['cafe_name']); ?> - Menu | <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .menu-nav {
            background: var(--primary-black);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-gray);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .menu-nav h1 {
            color: var(--primary-white);
            margin: 0;
            font-size: 24px;
        }
        .menu-nav a {
            color: var(--primary-white);
            text-decoration: none;
            padding: 8px 20px;
            border: 1px solid var(--primary-white);
            border-radius: 5px;
            transition: all 0.3s;
        }
        .menu-nav a:hover {
            background: var(--primary-white);
            color: var(--primary-black);
        }
        .cafe-header {
            background: linear-gradient(135deg, var(--primary-black) 0%, var(--accent-gray) 100%);
            padding: 60px 30px;
            text-align: center;
            color: var(--primary-white);
        }
        .cafe-header img {
            width: 120px;
            height: 120px;
            object-fit: contain;
            margin-bottom: 20px;
            border-radius: 10px;
            background: var(--primary-white);
            padding: 10px;
        }
        .cafe-header h2 {
            font-size: 42px;
            margin-bottom: 15px;
        }
        .cafe-info {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .cafe-info p {
            color: var(--text-gray);
            font-size: 16px;
            margin: 10px 0;
        }
        .menu-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 30px;
        }
        .category-section {
            margin-bottom: 50px;
        }
        .category-title {
            color: var(--primary-white);
            font-size: 28px;
            margin-bottom: 30px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-white);
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
        }
        .product-card {
            background: var(--accent-gray);
            border: 1px solid var(--border-gray);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(255,255,255,0.1);
        }
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 5px;
            margin-bottom: 15px;
            background: var(--primary-black);
        }
        .product-name {
            color: var(--primary-white);
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .product-price {
            color: var(--primary-white);
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .product-stock {
            color: var(--text-gray);
            font-size: 14px;
        }
        .no-products {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-gray);
        }
        .no-products h3 {
            color: var(--primary-white);
            font-size: 24px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body style="background: var(--primary-black);">
    <!-- Navigation Bar -->
    <nav class="menu-nav">
        <h1><?php echo APP_NAME; ?></h1>
        <div>
            <a href="index.php">← Back to Home</a>
            <?php if (!isLoggedIn()): ?>
                <a href="login.php" style="margin-left: 10px;">Login</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Café Header -->
    <section class="cafe-header">
        <?php if (!empty($cafe['logo']) && file_exists($cafe['logo'])): ?>
            <img src="<?php echo htmlspecialchars($cafe['logo']); ?>" alt="<?php echo htmlspecialchars($cafe['cafe_name']); ?> Logo">
        <?php else: ?>
            <div style="width: 120px; height: 120px; background: var(--primary-white); border-radius: 10px; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 60px;">☕</div>
        <?php endif; ?>
        <h2><?php echo htmlspecialchars($cafe['cafe_name']); ?></h2>
        <div class="cafe-info">
            <?php if (!empty($cafe['address'])): ?>
                <p><strong>📍 Address:</strong> <?php echo htmlspecialchars($cafe['address']); ?></p>
            <?php endif; ?>
            <?php if (!empty($cafe['phone'])): ?>
                <p><strong>📞 Phone:</strong> <?php echo htmlspecialchars($cafe['phone']); ?></p>
            <?php endif; ?>
            <?php if (!empty($cafe['description'])): ?>
                <p style="margin-top: 20px;"><?php echo nl2br(htmlspecialchars($cafe['description'])); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Menu Section -->
    <div class="menu-container">
        <?php if (empty($products)): ?>
            <div class="no-products">
                <h3>No Products Available</h3>
                <p>This café hasn't added any products to their menu yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($products_by_category as $category_name => $category_products): ?>
                <div class="category-section">
                    <h2 class="category-title"><?php echo htmlspecialchars($category_name); ?></h2>
                    <div class="products-grid">
                        <?php foreach ($category_products as $product): ?>
                            <div class="product-card">
                                <?php if (!empty($product['image']) && file_exists($product['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['item_name']); ?>" class="product-image">
                                <?php else: ?>
                                    <div class="product-image" style="display: flex; align-items: center; justify-content: center; font-size: 60px; color: var(--text-gray);">☕</div>
                                <?php endif; ?>
                                <div class="product-name"><?php echo htmlspecialchars($product['item_name']); ?></div>
                                <div class="product-price"><?php echo formatCurrency($product['price']); ?></div>
                                <div class="product-stock">Stock: <?php echo $product['stock']; ?> available</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer-landing" style="margin-top: 60px;">
        <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
    </footer>
</body>
</html>

