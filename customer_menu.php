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

// Get theme colors - try to get from cafe's theme
$theme_colors = [
    'primary' => '#FFFFFF',
    'secondary' => '#252525',
    'accent' => '#3A3A3A'
];

try {
    if ($cafe_id > 0) {
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

$page_title = 'Browse Menu';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
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
            cursor: pointer;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
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
            <a href="index.php">Home</a>
            <a href="customer_orders.php">My Orders</a>
            <span style="color: rgba(255, 255, 255, 0.9); padding: 10px 20px;"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            <a href="logout.php">Logout</a>
        </div>
    </nav>

    <?php if ($selected_cafe): ?>
        <!-- Café Header -->
        <section class="cafe-header">
            <?php if (!empty($selected_cafe['logo']) && file_exists($selected_cafe['logo'])): ?>
                <img src="<?php echo htmlspecialchars($selected_cafe['logo']); ?>" alt="<?php echo htmlspecialchars($selected_cafe['cafe_name']); ?> Logo">
            <?php else: ?>
                <div style="width: 120px; height: 120px; background: rgba(255, 255, 255, 0.2); border-radius: 20px; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 60px; color: white; backdrop-filter: blur(10px);">☕</div>
            <?php endif; ?>
            <h2><?php echo htmlspecialchars($selected_cafe['cafe_name']); ?></h2>
            <div class="cafe-info">
                <?php if (!empty($selected_cafe['address'])): ?>
                    <p><strong>📍 Address:</strong> <?php echo htmlspecialchars($selected_cafe['address']); ?></p>
                <?php endif; ?>
                <?php if (!empty($selected_cafe['phone'])): ?>
                    <p><strong>📞 Phone:</strong> <?php echo htmlspecialchars($selected_cafe['phone']); ?></p>
                <?php endif; ?>
                <?php if (!empty($selected_cafe['description'])): ?>
                    <p style="margin-top: 20px;"><?php echo nl2br(htmlspecialchars($selected_cafe['description'])); ?></p>
                <?php endif; ?>
            </div>
        </section>

        <!-- Menu Categories -->
        <?php if (empty($products_by_category)): ?>
            <div class="menu-category-section" style="text-align: center; color: white; padding: 100px 20px;">
                <h2 style="font-size: 36px; margin-bottom: 20px;">No Products Available</h2>
                <p style="font-size: 18px; opacity: 0.9;">No products available at this store.</p>
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
                                <div class="product-card" onclick="showProductModal(<?php echo $product['item_id']; ?>, '<?php echo htmlspecialchars(addslashes($product['item_name'])); ?>', <?php echo $product['price']; ?>, <?php echo $product['stock']; ?>)">
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
                                                $rating = isset($product['avg_rating']) && $product['avg_rating'] > 0 ? round($product['avg_rating'], 1) : 4.5;
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
                                            <button class="product-add-btn" onclick="event.stopPropagation(); showProductModal(<?php echo $product['item_id']; ?>, '<?php echo htmlspecialchars(addslashes($product['item_name'])); ?>', <?php echo $product['price']; ?>, <?php echo $product['stock']; ?>)">
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
    <?php else: ?>
        <div class="menu-category-section" style="text-align: center; color: white; padding: 100px 20px;">
            <h2 style="font-size: 36px; margin-bottom: 20px;">Store Not Found</h2>
            <p style="font-size: 18px; opacity: 0.9;"><a href="index.php" style="color: white; text-decoration: underline;">Go back to home</a></p>
        </div>
    <?php endif; ?>

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

