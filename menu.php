<?php
require_once 'config/config.php';

// Function to adjust color brightness
function adjustBrightness($hex, $percent) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $r = max(0, min(255, $r + ($r * $percent / 100)));
    $g = max(0, min(255, $g + ($g * $percent / 100)));
    $b = max(0, min(255, $b + ($b * $percent / 100)));
    return '#' . str_pad(dechex(round($r)), 2, '0', STR_PAD_LEFT) . 
                 str_pad(dechex(round($g)), 2, '0', STR_PAD_LEFT) . 
                 str_pad(dechex(round($b)), 2, '0', STR_PAD_LEFT);
}

// Function to convert hex to rgba
function hexToRgba($hex, $alpha = 1) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return "rgba($r, $g, $b, $alpha)";
}

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

// Get theme colors - try to get from cafe's theme
$theme_colors = [
    'primary' => '#FFFFFF',
    'secondary' => '#252525',
    'accent' => '#3A3A3A'
];

try {
    $stmt = $conn->prepare("SELECT primary_color, secondary_color, accent_color FROM cafe_settings WHERE cafe_id = ?");
    $stmt->execute([$cafe_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($settings) {
        $theme_colors = [
            'primary' => $settings['primary_color'],
            'secondary' => $settings['secondary_color'],
            'accent' => $settings['accent_color']
        ];
    }
} catch (Exception $e) {
    // Use defaults
}

// Create gradient colors from theme with opacity
$gradient_start = hexToRgba($theme_colors['secondary'], 0.85);
$gradient_mid1 = hexToRgba(adjustBrightness($theme_colors['accent'], 10), 0.8);
$gradient_mid2 = hexToRgba(adjustBrightness($theme_colors['accent'], 20), 0.75);
$gradient_mid3 = hexToRgba(adjustBrightness($theme_colors['accent'], 30), 0.7);
$gradient_end = hexToRgba(adjustBrightness($theme_colors['primary'], -20), 0.65);

// Get categories for this café
$stmt = $conn->prepare("SELECT category_id, category_name FROM menu_categories WHERE cafe_id = ? ORDER BY category_name");
$stmt->execute([$cafe_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all products for this café with ratings if available
$reviews_table_exists = false;
try {
    $conn->query("SELECT 1 FROM product_reviews LIMIT 1");
    $reviews_table_exists = true;
} catch (Exception $e) {
    $reviews_table_exists = false;
}

if ($reviews_table_exists) {
    $stmt = $conn->prepare("
        SELECT 
            mi.*, 
            mc.category_name,
            COALESCE(AVG(pr.rating), 4.5) as avg_rating,
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
        SELECT mi.*, mc.category_name, 4.5 as avg_rating, 0 as review_count
        FROM menu_items mi 
        LEFT JOIN menu_categories mc ON mi.category_id = mc.category_id 
        WHERE mi.cafe_id = ? AND mi.status = 'available' AND mi.stock > 0
        ORDER BY mc.category_name, mi.item_name
    ");
}
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            overflow-x: hidden;
        }

        /* Background with Image and Gradient Overlay */
        .landing-page {
            min-height: 100vh;
            position: relative;
            background: url('<?php echo BASE_URL; ?>assets/bg.jpg') center/cover no-repeat fixed;
        }

        .landing-page::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, 
                <?php echo $gradient_start; ?> 0%, 
                <?php echo $gradient_mid1; ?> 25%, 
                <?php echo $gradient_mid2; ?> 50%, 
                <?php echo $gradient_mid3; ?> 75%, 
                <?php echo $gradient_end; ?> 100%);
            z-index: 0;
            pointer-events: none;
        }

        /* Glass Header */
        .landing-nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .landing-nav h1 {
            color: white;
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .landing-nav-links {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .landing-nav-links a {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 20px;
            transition: all 0.3s ease;
            font-weight: 500;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .landing-nav-links a:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        /* Cafe Header */
        .cafe-header {
            position: relative;
            z-index: 1;
            padding: 150px 40px 60px;
            text-align: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .cafe-header img {
            width: 120px;
            height: 120px;
            object-fit: contain;
            margin-bottom: 20px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.2);
            padding: 10px;
            backdrop-filter: blur(10px);
        }

        .cafe-header h2 {
            font-size: 48px;
            font-weight: 700;
            color: white;
            margin-bottom: 20px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .cafe-info {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            line-height: 1.8;
            text-shadow: 0 1px 5px rgba(0, 0, 0, 0.2);
        }

        /* Menu Category Section */
        .menu-category-section {
            position: relative;
            z-index: 1;
            padding: 60px 40px;
            max-width: 1400px;
            margin: 0 auto;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .category-header h2 {
            font-size: 48px;
            font-weight: 800;
            color: white;
            text-shadow: 0 3px 15px rgba(0, 0, 0, 0.4);
            letter-spacing: 1px;
            background: linear-gradient(135deg, 
                <?php echo hexToRgba($theme_colors['primary'], 1); ?> 0%, 
                <?php echo hexToRgba($theme_colors['primary'], 0.8); ?> 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            filter: drop-shadow(0 2px 4px rgba(255, 255, 255, 0.3));
        }

        .category-description {
            font-size: 18px;
            color: rgba(255, 255, 255, 0.9);
            margin-top: 10px;
            text-shadow: 0 1px 5px rgba(0, 0, 0, 0.2);
        }

        .category-carousel {
            position: relative;
            overflow: visible;
        }

        .category-carousel-wrapper {
            display: flex;
            gap: 30px;
            padding: 15px 0;
            overflow-x: auto;
            overflow-y: hidden;
            scroll-behavior: smooth;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
            -ms-overflow-style: -ms-autohiding-scrollbar;
            cursor: grab;
        }

        .category-carousel-wrapper:active {
            cursor: grabbing;
        }

        .category-carousel-wrapper::-webkit-scrollbar {
            height: 8px;
        }

        .category-carousel-wrapper::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        .category-carousel-wrapper::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.4);
            border-radius: 10px;
            transition: background 0.3s ease;
        }

        .category-carousel-wrapper::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.6);
        }

        .product-card {
            flex: 0 0 280px;
            background: transparent;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }

        .product-card:hover {
            transform: scale(1.05);
        }

        .product-image-wrapper {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            overflow: hidden;
            margin-bottom: 20px;
            background: linear-gradient(135deg, 
                <?php echo hexToRgba($theme_colors['primary'], 0.2); ?> 0%, 
                <?php echo hexToRgba($theme_colors['accent'], 0.15); ?> 100%);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 4px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4),
                        0 0 0 1px rgba(255, 255, 255, 0.2) inset;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .product-card:hover .product-image-wrapper {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5),
                        0 0 0 1px rgba(255, 255, 255, 0.3) inset;
            border-color: rgba(255, 255, 255, 0.6);
        }

        .product-image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .product-card:hover .product-image-wrapper img {
            transform: scale(1.15);
        }

        .product-card-panel {
            background: linear-gradient(135deg, 
                <?php echo hexToRgba($theme_colors['secondary'], 0.95); ?> 0%, 
                <?php echo hexToRgba($theme_colors['accent'], 0.9); ?> 100%);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border-radius: 24px;
            padding: 24px;
            width: 100%;
            min-height: 200px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4), 
                        0 0 0 1px rgba(255, 255, 255, 0.1) inset;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .product-card:hover .product-card-panel {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5), 
                        0 0 0 1px rgba(255, 255, 255, 0.2) inset;
            background: linear-gradient(135deg, 
                <?php echo hexToRgba($theme_colors['secondary'], 1); ?> 0%, 
                <?php echo hexToRgba($theme_colors['accent'], 0.95); ?> 100%);
        }

        .product-name {
            color: white;
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 14px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .product-rating {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-bottom: 10px;
        }

        .product-rating .stars {
            color: #ffc107;
            font-size: 18px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            filter: drop-shadow(0 0 3px rgba(255, 193, 7, 0.5));
        }

        .product-rating .rating-value {
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
            font-weight: 600;
        }

        .product-volume {
            color: rgba(255, 255, 255, 0.85);
            font-size: 15px;
            text-align: center;
            margin-bottom: 15px;
            font-weight: 500;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        }

        .product-price-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .product-price {
            color: white;
            font-size: 24px;
            font-weight: 800;
            flex: 1;
            text-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
            letter-spacing: 0.5px;
        }

        .product-add-btn {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            background: linear-gradient(135deg, 
                <?php echo hexToRgba($theme_colors['primary'], 0.3); ?> 0%, 
                <?php echo hexToRgba($theme_colors['primary'], 0.2); ?> 100%);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 2px solid rgba(255, 255, 255, 0.5);
            color: white;
            font-size: 26px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            flex-shrink: 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .product-add-btn:hover {
            background: linear-gradient(135deg, 
                <?php echo hexToRgba($theme_colors['primary'], 0.5); ?> 0%, 
                <?php echo hexToRgba($theme_colors['primary'], 0.4); ?> 100%);
            border-color: rgba(255, 255, 255, 0.8);
            transform: scale(1.15) rotate(90deg);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }

        .product-add-btn:active {
            transform: scale(1.05) rotate(90deg);
        }


        /* Footer */
        .footer-landing {
            position: relative;
            z-index: 1;
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(20px);
            padding: 40px;
            text-align: center;
            color: rgba(255, 255, 255, 0.9);
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            margin-top: 60px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .category-header h2 {
                font-size: 32px;
            }

            .product-card {
                flex: 0 0 240px;
            }

            .product-image-wrapper {
                width: 160px;
                height: 160px;
            }

            .landing-nav {
                padding: 16px 20px;
                flex-direction: column;
                gap: 16px;
            }

            .landing-nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body class="landing-page">
    <!-- Glass Navigation Bar -->
    <nav class="landing-nav">
        <h1><?php echo APP_NAME; ?></h1>
        <div class="landing-nav-links">
            <a href="index.php">← Back to Home</a>
            <?php if (!isLoggedIn()): ?>
                <a href="login.php">Login</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Café Header -->
    <section class="cafe-header">
        <?php if (!empty($cafe['logo']) && file_exists($cafe['logo'])): ?>
            <img src="<?php echo htmlspecialchars($cafe['logo']); ?>" alt="<?php echo htmlspecialchars($cafe['cafe_name']); ?> Logo">
        <?php else: ?>
            <div style="width: 120px; height: 120px; background: rgba(255, 255, 255, 0.2); border-radius: 20px; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 60px; color: white; backdrop-filter: blur(10px);">☕</div>
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

    <!-- Menu Categories -->
    <?php if (empty($products)): ?>
        <div class="menu-category-section" style="text-align: center; color: white; padding: 100px 20px;">
            <h2 style="font-size: 36px; margin-bottom: 20px;">No Products Available</h2>
            <p style="font-size: 18px; opacity: 0.9;">This café hasn't added any products to their menu yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($products_by_category as $category_name => $category_products): ?>
            <section class="menu-category-section">
                <div class="category-header">
                    <div>
                        <h2>OUR <?php echo strtoupper(htmlspecialchars($category_name)); ?></h2>
                        <p class="category-description">There's always room for coffee, it's not just coffee, it's an experience, life is better with coffee.</p>
                    </div>
                </div>
                    <div class="category-carousel">
                        <div class="category-carousel-wrapper" id="carousel-<?php echo preg_replace('/[^a-zA-Z0-9]/', '', strtolower($category_name)); ?>">
                        <?php foreach ($category_products as $product): ?>
                            <div class="product-card">
                                <div class="product-image-wrapper">
                                    <?php if (!empty($product['image']) && file_exists($product['image'])): ?>
                                        <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['item_name']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-coffee" style="font-size: 80px; color: rgba(255, 255, 255, 0.7);"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="product-card-panel">
                                    <div class="product-name"><?php echo htmlspecialchars($product['item_name']); ?></div>
                                    <div class="product-rating">
                                        <span class="stars">
                                            <?php
                                            $rating = round($product['avg_rating'], 1);
                                            $full_stars = floor($rating);
                                            $has_half = ($rating - $full_stars) >= 0.5;
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
                                        <span class="rating-value"><?php echo number_format($rating, 1); ?></span>
                                    </div>
                                    <div class="product-volume">Volume 160 ml</div>
                                    <div class="product-price-row">
                                        <span class="product-price"><?php echo formatCurrency($product['price']); ?></span>
                                        <button class="product-add-btn" onclick="addToCart(<?php echo $product['item_id']; ?>, '<?php echo htmlspecialchars(addslashes($product['item_name'])); ?>', <?php echo $product['price']; ?>)">
                                            +
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer-landing">
        <p style="margin: 0; font-size: 16px;">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
        <p style="margin-top: 10px; font-size: 14px;">Brewed with ❤️ for café lovers</p>
    </footer>

    <script>
        function addToCart(itemId, itemName, price) {
            alert('Added to cart: ' + itemName + ' - ' + formatCurrency(price));
            // Add your cart functionality here
        }

        function formatCurrency(amount) {
            return 'Rp ' + amount.toLocaleString('id-ID');
        }

        // Enable smooth scrolling with mouse drag
        document.addEventListener('DOMContentLoaded', function() {
            const carousels = document.querySelectorAll('.category-carousel-wrapper');
            
            carousels.forEach(carousel => {
                let isDown = false;
                let startX;
                let scrollLeft;

                carousel.addEventListener('mousedown', (e) => {
                    isDown = true;
                    carousel.style.cursor = 'grabbing';
                    startX = e.pageX - carousel.offsetLeft;
                    scrollLeft = carousel.scrollLeft;
                });

                carousel.addEventListener('mouseleave', () => {
                    isDown = false;
                    carousel.style.cursor = 'grab';
                });

                carousel.addEventListener('mouseup', () => {
                    isDown = false;
                    carousel.style.cursor = 'grab';
                });

                carousel.addEventListener('mousemove', (e) => {
                    if (!isDown) return;
                    e.preventDefault();
                    const x = e.pageX - carousel.offsetLeft;
                    const walk = (x - startX) * 2; // Scroll speed
                    carousel.scrollLeft = scrollLeft - walk;
                });
            });
        });
    </script>
</body>
</html>
