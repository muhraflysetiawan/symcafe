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
$is_customer = isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'customer';
$user_role = isLoggedIn() && isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;

// Get theme colors - try to get from logged-in user's cafe, or use first cafe, or defaults
$theme_colors = [
    'primary' => '#FFFFFF',
    'secondary' => '#252525',
    'accent' => '#3A3A3A'
];

try {
    $cafe_id = null;
    if (isLoggedIn() && function_exists('getCafeId')) {
        $cafe_id = getCafeId();
    }
    
    // If no cafe_id from logged in user, get first cafe's theme
    if (!$cafe_id) {
        $stmt = $conn->prepare("SELECT cafe_id FROM cafes ORDER BY cafe_id LIMIT 1");
        $stmt->execute();
        $first_cafe = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($first_cafe) {
            $cafe_id = $first_cafe['cafe_id'];
        }
    }
    
    if ($cafe_id) {
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
    // Use defaults if error
}

// Create gradient colors from theme with opacity
$gradient_start = hexToRgba($theme_colors['secondary'], 0.85);
$gradient_mid1 = hexToRgba(adjustBrightness($theme_colors['accent'], 10), 0.8);
$gradient_mid2 = hexToRgba(adjustBrightness($theme_colors['accent'], 20), 0.75);
$gradient_mid3 = hexToRgba(adjustBrightness($theme_colors['accent'], 30), 0.7);
$gradient_end = hexToRgba(adjustBrightness($theme_colors['primary'], -20), 0.65);

// Handle search
$search_query = isset($_GET['search']) ? trim(sanitizeInput($_GET['search'])) : '';

// Get all cafes
$all_cafes = [];
try {
    if (!empty($search_query)) {
        $stmt = $conn->prepare("
            SELECT cafe_id, cafe_name, address, description, phone, logo 
            FROM cafes 
            WHERE cafe_name LIKE ? OR address LIKE ? OR description LIKE ?
            ORDER BY cafe_name
        ");
        $search_term = '%' . $search_query . '%';
        $stmt->execute([$search_term, $search_term, $search_term]);
        $all_cafes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $conn->prepare("
            SELECT cafe_id, cafe_name, address, description, phone, logo 
            FROM cafes 
            ORDER BY cafe_name
        ");
        $stmt->execute();
        $all_cafes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $all_cafes = [];
}

// Show only first 6 cafes initially, rest with "More" button
$initial_display = 6;
$displayed_cafes = array_slice($all_cafes, 0, $initial_display);
$remaining_cafes = array_slice($all_cafes, $initial_display);

$page_title = 'Explore Cafés';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Explore Cafés</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
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
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 25%, #334155 50%, #475569 75%, #64748b 100%);
            background-attachment: fixed;
        }

        .landing-page::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 40% 20%, rgba(236, 72, 153, 0.1) 0%, transparent 50%);
            z-index: 0;
            pointer-events: none;
            animation: gradientShift 15s ease infinite;
        }

        @keyframes gradientShift {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.8;
            }
        }

        /* Dashboard-style Animated Header */
        .landing-nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            padding: 16px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            border-bottom: 2px solid rgba(99, 102, 241, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            animation: slideDown 0.6s ease-out;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .landing-nav.scrolled {
            padding: 12px 40px;
            background: rgba(15, 23, 42, 0.95);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
        }

        .landing-nav h1 {
            color: white;
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
            position: relative;
        }

        .landing-nav h1::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 0;
            height: 3px;
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
            transition: width 0.5s ease;
            border-radius: 2px;
        }

        .landing-nav h1:hover::after {
            width: 100%;
        }

        .landing-nav-links {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .landing-nav-links a {
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 600;
            font-size: 15px;
            border: 2px solid rgba(99, 102, 241, 0.3);
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(139, 92, 246, 0.2) 100%);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }

        .landing-nav-links a::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .landing-nav-links a:hover::before {
            left: 100%;
        }

        .landing-nav-links a:hover {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.4) 0%, rgba(139, 92, 246, 0.4) 100%);
            border-color: rgba(99, 102, 241, 0.6);
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
        }

        /* Page Header */
        .page-header {
            position: relative;
            z-index: 1;
            padding: 150px 40px 60px;
            text-align: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header h1 {
            font-size: 56px;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff 0%, #e0e7ff 50%, #c7d2fe 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .page-header p {
            font-size: 20px;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 40px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        /* Search Section */
        .search-section {
            position: relative;
            z-index: 1;
            padding: 40px;
            max-width: 800px;
            margin: 0 auto 60px;
        }

        .search-box {
            display: flex;
            gap: 12px;
            background: rgba(30, 41, 59, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 20px;
            border-radius: 24px;
            border: 2px solid rgba(99, 102, 241, 0.2);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .search-box input {
            flex: 1;
            padding: 14px 20px;
            border: 2px solid rgba(99, 102, 241, 0.2);
            border-radius: 16px;
            font-size: 16px;
            background: rgba(15, 23, 42, 0.5);
            color: white;
            transition: all 0.3s ease;
        }

        .search-box input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .search-box input:focus {
            outline: none;
            border-color: rgba(99, 102, 241, 0.5);
            background: rgba(15, 23, 42, 0.7);
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .search-box button {
            padding: 14px 32px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            white-space: nowrap;
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
        }

        .search-box button:hover {
            background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 12px 32px rgba(99, 102, 241, 0.6);
        }

        /* Cafes Grid */
        .cafes-section {
            position: relative;
            z-index: 1;
            padding: 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .cafes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .cafe-card {
            background: rgba(30, 41, 59, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 30px;
            border: 2px solid rgba(99, 102, 241, 0.2);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }

        .cafe-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%);
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .cafe-card:hover::before {
            opacity: 1;
        }

        .cafe-card:hover {
            transform: translateY(-10px) scale(1.05);
            box-shadow: 0 20px 50px rgba(99, 102, 241, 0.4);
            border-color: rgba(99, 102, 241, 0.5);
            background: rgba(30, 41, 59, 0.8);
        }

        .cafe-logo {
            width: 120px;
            height: 120px;
            border-radius: 20px;
            object-fit: cover;
            margin: 0 auto 20px;
            display: block;
            background: rgba(255, 255, 255, 0.2);
            padding: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .cafe-card h3 {
            color: white;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
            text-align: center;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .cafe-card p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 16px;
            text-align: center;
            text-shadow: 0 1px 5px rgba(0, 0, 0, 0.2);
        }

        .cafe-address {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            text-align: center;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .cafe-phone {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            text-align: center;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .cafe-btn {
            display: block;
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-top: 16px;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .cafe-btn:hover {
            background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.5);
        }

        /* More Button */
        .more-button-container {
            text-align: center;
            margin-top: 40px;
        }

        .more-btn {
            padding: 16px 40px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            backdrop-filter: blur(10px);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
        }

        .more-btn:hover {
            background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 12px 32px rgba(99, 102, 241, 0.6);
        }

        .hidden-cafes {
            display: none;
        }

        .hidden-cafes.show {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }

        /* No Results */
        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: white;
        }

        .no-results i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.7;
        }

        .no-results h3 {
            font-size: 28px;
            margin-bottom: 12px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .no-results p {
            font-size: 18px;
            opacity: 0.9;
            text-shadow: 0 1px 5px rgba(0, 0, 0, 0.2);
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
            .page-header h1 {
                font-size: 36px;
            }

            .cafes-grid {
                grid-template-columns: 1fr;
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

            .search-box {
                flex-direction: column;
            }
        }
    </style>
</head>
<body class="landing-page">
    <!-- Dashboard-style Navigation Bar -->
    <nav class="landing-nav">
        <h1>
            <img src="<?php echo BASE_URL; ?>assets/logo.png" alt="Symcafe Logo" class="logo-img" style="height: 50px; width: auto; object-fit: contain; filter: drop-shadow(0 2px 10px rgba(0, 0, 0, 0.2));">
            <?php echo APP_NAME; ?>
        </h1>
        <div class="landing-nav-links">
            <a href="index.php">Home</a>
            <?php if (isLoggedIn()): ?>
                <?php if ($user_role == 'customer'): ?>
                    <a href="customer_orders.php">My Orders</a>
                <?php else: ?>
                    <a href="dashboard.php">Dashboard</a>
                <?php endif; ?>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="page-header">
        <h1>Explore Cafés</h1>
        <p>Discover amazing cafés and their unique offerings</p>
    </section>

    <!-- Search Section -->
    <section class="search-section">
        <form method="GET" class="search-box">
            <input type="text" name="search" placeholder="Search cafés by name, address, or description..." value="<?php echo htmlspecialchars($search_query); ?>">
            <button type="submit">
                <i class="fas fa-search"></i> Search
            </button>
        </form>
    </section>

    <!-- Cafes Section -->
    <section class="cafes-section">
        <?php if (empty($all_cafes)): ?>
            <div class="no-results">
                <i class="fas fa-coffee"></i>
                <h3>No cafés found</h3>
                <p><?php echo !empty($search_query) ? 'Try a different search term' : 'No cafés are available at the moment'; ?></p>
            </div>
        <?php else: ?>
            <div class="cafes-grid" id="displayedCafes">
                <?php foreach ($displayed_cafes as $cafe): ?>
                    <div class="cafe-card" onclick="window.location.href='<?php echo $is_customer ? 'customer_menu.php' : 'menu.php'; ?>?cafe_id=<?php echo $cafe['cafe_id']; ?>'">
                        <?php if (!empty($cafe['logo']) && file_exists($cafe['logo'])): ?>
                            <img src="<?php echo htmlspecialchars($cafe['logo']); ?>" alt="<?php echo htmlspecialchars($cafe['cafe_name']); ?>" class="cafe-logo">
                        <?php else: ?>
                            <div class="cafe-logo" style="display: flex; align-items: center; justify-content: center; font-size: 48px; color: white;">
                                <i class="fas fa-coffee"></i>
                            </div>
                        <?php endif; ?>
                        <h3><?php echo htmlspecialchars($cafe['cafe_name']); ?></h3>
                        <?php if (!empty($cafe['description'])): ?>
                            <p><?php echo htmlspecialchars(substr($cafe['description'], 0, 120)); ?><?php echo strlen($cafe['description']) > 120 ? '...' : ''; ?></p>
                        <?php endif; ?>
                        <?php if (!empty($cafe['address'])): ?>
                            <div class="cafe-address">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($cafe['address']); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($cafe['phone'])): ?>
                            <div class="cafe-phone">
                                <i class="fas fa-phone"></i>
                                <span><?php echo htmlspecialchars($cafe['phone']); ?></span>
                            </div>
                        <?php endif; ?>
                        <a href="<?php echo $is_customer ? 'customer_menu.php' : 'menu.php'; ?>?cafe_id=<?php echo $cafe['cafe_id']; ?>" class="cafe-btn" onclick="event.stopPropagation();">
                            View Menu <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Hidden Cafes (shown with More button) -->
            <?php if (!empty($remaining_cafes)): ?>
                <div class="hidden-cafes" id="hiddenCafes">
                    <?php foreach ($remaining_cafes as $cafe): ?>
                        <div class="cafe-card" onclick="window.location.href='<?php echo $is_customer ? 'customer_menu.php' : 'menu.php'; ?>?cafe_id=<?php echo $cafe['cafe_id']; ?>'">
                            <?php if (!empty($cafe['logo']) && file_exists($cafe['logo'])): ?>
                                <img src="<?php echo htmlspecialchars($cafe['logo']); ?>" alt="<?php echo htmlspecialchars($cafe['cafe_name']); ?>" class="cafe-logo">
                            <?php else: ?>
                                <div class="cafe-logo" style="display: flex; align-items: center; justify-content: center; font-size: 48px; color: white;">
                                    <i class="fas fa-coffee"></i>
                                </div>
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($cafe['cafe_name']); ?></h3>
                            <?php if (!empty($cafe['description'])): ?>
                                <p><?php echo htmlspecialchars(substr($cafe['description'], 0, 120)); ?><?php echo strlen($cafe['description']) > 120 ? '...' : ''; ?></p>
                            <?php endif; ?>
                            <?php if (!empty($cafe['address'])): ?>
                                <div class="cafe-address">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($cafe['address']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($cafe['phone'])): ?>
                                <div class="cafe-phone">
                                    <i class="fas fa-phone"></i>
                                    <span><?php echo htmlspecialchars($cafe['phone']); ?></span>
                                </div>
                            <?php endif; ?>
                            <a href="<?php echo $is_customer ? 'customer_menu.php' : 'menu.php'; ?>?cafe_id=<?php echo $cafe['cafe_id']; ?>" class="cafe-btn" onclick="event.stopPropagation();">
                                View Menu <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="more-button-container">
                    <button class="more-btn" id="moreBtn" onclick="showMoreCafes()">
                        <i class="fas fa-chevron-down"></i> Show More Cafés
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <!-- Footer -->
    <footer class="footer-landing">
        <p style="margin: 0; font-size: 16px;">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
        <p style="margin-top: 10px; font-size: 14px;">Brewed with ❤️ for café lovers</p>
    </footer>

    <script>
        function showMoreCafes() {
            const hiddenCafes = document.getElementById('hiddenCafes');
            const moreBtn = document.getElementById('moreBtn');
            
            if (hiddenCafes && !hiddenCafes.classList.contains('show')) {
                hiddenCafes.classList.add('show');
                moreBtn.style.display = 'none';
                
                // Smooth scroll to the newly shown cafes
                hiddenCafes.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        // Navbar scroll animation
        document.addEventListener('DOMContentLoaded', function() {
            const navbar = document.querySelector('.landing-nav');
            if (navbar) {
                window.addEventListener('scroll', function() {
                    const currentScroll = window.pageYOffset;

                    if (currentScroll > 50) {
                        navbar.classList.add('scrolled');
                    } else {
                        navbar.classList.remove('scrolled');
                    }
                });
            }
        });
    </script>
</body>
</html>

