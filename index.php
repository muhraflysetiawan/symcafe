<?php
require_once 'config/config.php';
require_once 'includes/tutorial.php';

// Don't redirect logged-in users - they can still view the landing page
$db = new Database();
$conn = $db->getConnection();

// Handle search - cafes or products
$search_query = isset($_GET['search']) ? trim(sanitizeInput($_GET['search'])) : '';
$search_type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : 'cafe';
$cafes = [];
$products = [];
$is_customer = isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'customer';
$user_role = isLoggedIn() && isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;

// Get tutorial steps for logged-in users
$tutorial_steps = [];
if ($user_role && in_array($user_role, ['owner', 'cashier'])) {
    $tutorial_steps = getTutorialSteps($user_role);
}

if (!empty($search_query)) {
    if ($search_type == 'product' && $is_customer) {
        $stmt = $conn->prepare("
            SELECT 
                mi.item_id, mi.item_name, mi.price, mi.stock, mi.status, mi.image,
                c.cafe_id, c.cafe_name, c.logo as cafe_logo, mc.category_name
            FROM menu_items mi
            JOIN cafes c ON mi.cafe_id = c.cafe_id
            LEFT JOIN menu_categories mc ON mi.category_id = mc.category_id
            WHERE mi.item_name LIKE ? AND mi.status = 'available' AND mi.stock > 0
            ORDER BY mi.item_name
            LIMIT 50
        ");
        $search_term = '%' . $search_query . '%';
        $stmt->execute([$search_term]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $conn->prepare("
            SELECT cafe_id, cafe_name, address, description, phone, logo 
            FROM cafes 
            WHERE cafe_name LIKE ? OR address LIKE ? OR description LIKE ?
            ORDER BY cafe_name
        ");
        $search_term = '%' . $search_query . '%';
        $stmt->execute([$search_term, $search_term, $search_term]);
        $cafes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$page_title = 'Home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Point of Sale System for Cafés</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <style>
        /* Landing Page Specific Styles - Matching Dashboard Design */
        .landing-page {
            min-height: 100vh;
            background: var(--primary-black);
        }

        .landing-nav {
            background: rgba(26, 26, 26, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 18px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid var(--border-gray);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .landing-nav h1 {
            color: var(--primary-white);
            margin: 0;
            font-size: 26px;
            font-weight: 700;
        }

        .landing-nav-links {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .landing-nav-links a, .learn-btn {
            color: var(--primary-white);
            text-decoration: none;
            padding: 10px 20px;
            border-radius: var(--radius-md);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 500;
            border: 1px solid var(--border-gray);
        }

        .landing-nav-links a:hover, .learn-btn:hover {
            background: var(--accent-gray);
            border-color: var(--primary-white);
            transform: translateY(-2px);
        }

        .learn-btn {
            background: linear-gradient(135deg, var(--accent-color) 0%, var(--accent-hover) 100%);
            border: none;
            cursor: pointer;
        }

        .learn-btn:hover {
            background: linear-gradient(135deg, var(--accent-hover) 0%, var(--accent-color) 100%);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }

        /* Hero Section with Animations */
        .hero-section {
            background: linear-gradient(135deg, var(--primary-black) 0%, var(--accent-gray) 100%);
            padding: 100px 32px;
            text-align: center;
            color: var(--primary-white);
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 50% 50%, rgba(99, 102, 241, 0.1) 0%, transparent 70%);
            animation: pulse 8s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.5; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.1); }
        }

        .hero-content {
            position: relative;
            z-index: 1;
            animation: fadeInUp 1s ease-out;
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

        .hero-section h2 {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 20px;
            letter-spacing: -1px;
        }

        .hero-section p {
            font-size: 20px;
            color: var(--text-gray);
            max-width: 800px;
            margin: 0 auto 40px;
            line-height: 1.6;
        }

        /* Search Section - Dashboard Style */
        .search-section {
            background: var(--primary-white);
            padding: 60px 32px;
            text-align: center;
        }

        .search-section h2 {
            color: var(--primary-black);
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .search-section > p {
            color: var(--text-gray);
            font-size: 18px;
            margin-bottom: 40px;
        }

        .search-box {
            max-width: 700px;
            margin: 0 auto;
            display: flex;
            gap: 12px;
            animation: fadeInUp 1s ease-out 0.3s both;
        }

        .search-box input, .search-box select {
            flex: 1;
            padding: 16px 20px;
            border: 2px solid var(--border-gray);
            border-radius: var(--radius-lg);
            font-size: 16px;
            background: var(--primary-white);
            color: var(--primary-black);
            transition: all 0.3s;
        }

        .search-box input:focus, .search-box select:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .search-box button {
            padding: 16px 32px;
            background: linear-gradient(135deg, var(--accent-color) 0%, var(--accent-hover) 100%);
            color: var(--primary-white);
            border: none;
            border-radius: var(--radius-lg);
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .search-box button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }

        /* Results Grid - Dashboard Card Style */
        .cafe-results {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
            padding: 40px 32px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .cafe-card {
            background: linear-gradient(135deg, var(--accent-gray) 0%, rgba(45, 45, 45, 0.95) 100%);
            border-radius: var(--radius-xl);
            padding: 24px;
            text-align: center;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border-gray);
            cursor: pointer;
            animation: cardAppear 0.6s ease-out forwards;
            opacity: 0;
            transform: translateY(30px);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .cafe-card:nth-child(1) { animation-delay: 0.1s; }
        .cafe-card:nth-child(2) { animation-delay: 0.2s; }
        .cafe-card:nth-child(3) { animation-delay: 0.3s; }
        .cafe-card:nth-child(4) { animation-delay: 0.4s; }
        .cafe-card:nth-child(5) { animation-delay: 0.5s; }
        .cafe-card:nth-child(6) { animation-delay: 0.6s; }

        @keyframes cardAppear {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .cafe-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4);
            border-color: rgba(99, 102, 241, 0.5);
        }

        .cafe-card img {
            width: 100px;
            height: 100px;
            object-fit: contain;
            margin-bottom: 16px;
            border-radius: var(--radius-md);
        }

        .cafe-card h3 {
            color: var(--primary-white);
            margin: 12px 0;
            font-size: 20px;
            font-weight: 600;
        }

        .cafe-card p {
            color: var(--text-gray);
            font-size: 14px;
            margin: 6px 0;
        }

        /* Section Styles - Dashboard Style */
        .section {
            padding: 80px 32px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .section h2 {
            color: var(--primary-white);
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 40px;
            text-align: center;
            letter-spacing: -0.5px;
        }

        .section p {
            color: var(--text-gray);
            font-size: 18px;
            line-height: 1.8;
            margin-bottom: 20px;
        }

        /* Features Grid - Dashboard Card Style */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-top: 40px;
        }

        .feature-card {
            background: linear-gradient(135deg, var(--accent-gray) 0%, rgba(45, 45, 45, 0.95) 100%);
            padding: 32px;
            border-radius: var(--radius-xl);
            text-align: center;
            border: 1px solid var(--border-gray);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            animation: cardAppear 0.6s ease-out forwards;
            opacity: 0;
        }

        .feature-card:nth-child(1) { animation-delay: 0.1s; }
        .feature-card:nth-child(2) { animation-delay: 0.2s; }
        .feature-card:nth-child(3) { animation-delay: 0.3s; }
        .feature-card:nth-child(4) { animation-delay: 0.4s; }
        .feature-card:nth-child(5) { animation-delay: 0.5s; }
        .feature-card:nth-child(6) { animation-delay: 0.6s; }

        .feature-card:hover {
            transform: translateY(-8px) scale(1.03);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4);
            border-color: rgba(99, 102, 241, 0.5);
        }

        .feature-card .feature-icon {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
        }

        .feature-card h3 {
            color: var(--primary-white);
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .feature-card p {
            color: var(--text-gray);
            font-size: 15px;
            line-height: 1.6;
            margin: 0;
        }

        /* Footer */
        .footer-landing {
            background: var(--primary-black);
            padding: 40px 32px;
            text-align: center;
            color: var(--text-gray);
            border-top: 1px solid var(--border-gray);
        }

        /* Tutorial Modal */
        .tutorial-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            z-index: 10000;
            overflow-y: auto;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .tutorial-modal.active {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 40px 20px;
        }

        .tutorial-content {
            background: linear-gradient(135deg, var(--accent-gray) 0%, var(--primary-black) 100%);
            border-radius: var(--radius-xl);
            padding: 40px;
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid var(--border-gray);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: slideUp 0.4s ease-out;
            margin-top: 40px;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .tutorial-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-gray);
        }

        .tutorial-header h2 {
            color: var(--primary-white);
            font-size: 32px;
            font-weight: 700;
            margin: 0;
        }

        .tutorial-close {
            background: none;
            border: none;
            color: var(--primary-white);
            font-size: 32px;
            cursor: pointer;
            padding: 0;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s;
        }

        .tutorial-close:hover {
            background: var(--hover-gray);
            transform: rotate(90deg);
        }

        .tutorial-category {
            margin-bottom: 40px;
            animation: fadeInUp 0.6s ease-out;
        }

        .tutorial-category-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .tutorial-category-title .icon {
            font-size: 32px;
        }

        .tutorial-category-title h3 {
            color: var(--primary-white);
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }

        .tutorial-category p {
            color: var(--text-gray);
            margin-bottom: 24px;
            font-size: 16px;
        }

        .tutorial-steps-list {
            display: grid;
            gap: 16px;
        }

        .tutorial-step-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: var(--radius-lg);
            border-left: 4px solid var(--accent-color);
            transition: all 0.3s;
        }

        .tutorial-step-item:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateX(8px);
        }

        .tutorial-step-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .tutorial-step-header .icon {
            font-size: 24px;
        }

        .tutorial-step-header h4 {
            color: var(--primary-white);
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }

        .tutorial-step-item p {
            color: var(--text-gray);
            font-size: 14px;
            line-height: 1.6;
            margin: 0 0 8px 0;
        }

        .tutorial-step-link {
            display: inline-block;
            color: var(--accent-color);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            margin-top: 8px;
            transition: all 0.3s;
        }

        .tutorial-step-link:hover {
            color: var(--accent-hover);
            transform: translateX(4px);
        }

        .tutorial-step-link i {
            margin-left: 6px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-section h2 {
                font-size: 32px;
            }
            
            .hero-section p {
                font-size: 16px;
            }
            
            .search-box {
                flex-direction: column;
            }
            
            .cafe-results {
                grid-template-columns: 1fr;
                padding: 20px 16px;
            }
            
            .tutorial-content {
                padding: 24px;
                margin-top: 20px;
            }
        }
    </style>
</head>
<body class="landing-page">
    <!-- Navigation Bar -->
    <nav class="landing-nav">
        <h1><?php echo APP_NAME; ?></h1>
        <div class="landing-nav-links">
            <?php if (isLoggedIn()): ?>
                <?php if ($user_role == 'customer'): ?>
                    <a href="index.php">Home</a>
                    <a href="customer_orders.php">My Orders</a>
                <?php else: ?>
                    <a href="dashboard.php">Dashboard</a>
                    <?php if (!empty($tutorial_steps)): ?>
                        <button class="learn-btn" onclick="openTutorial()">
                            <i class="fas fa-graduation-cap"></i> Learn
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register as Owner</a>
                <a href="register_customer.php">Register as Customer</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <h2>Welcome to <?php echo APP_NAME; ?></h2>
            <p>Modern Point of Sale System designed specifically for cafés. Manage your business efficiently with our comprehensive POS solution.</p>
            <?php if (!isLoggedIn()): ?>
                <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
                    <a href="register.php" class="btn btn-primary" style="padding: 16px 32px; font-size: 18px; text-decoration: none; display: inline-block;">
                        <i class="fas fa-rocket"></i> Get Started
                    </a>
                    <a href="login.php" class="btn btn-secondary" style="padding: 16px 32px; font-size: 18px; text-decoration: none; display: inline-block;">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Search Section -->
    <section class="search-section">
        <h2>
            <?php echo $is_customer ? 'Find Cafés & Order from Menu' : 'Find Your Favorite Café'; ?>
        </h2>
        <p>
            <?php echo $is_customer ? 'Search for cafés or browse products to order' : 'Search for cafés and browse their menu'; ?>
        </p>
        <form method="GET" class="search-box">
            <input type="text" name="search" placeholder="<?php echo $is_customer ? 'Search cafés or products...' : 'Search by café name, address, or description...'; ?>" value="<?php echo htmlspecialchars($search_query); ?>">
            <?php if ($is_customer): ?>
                <select name="type">
                    <option value="cafe" <?php echo $search_type == 'cafe' ? 'selected' : ''; ?>>Search Cafés</option>
                    <option value="product" <?php echo $search_type == 'product' ? 'selected' : ''; ?>>Search Products</option>
                </select>
            <?php endif; ?>
            <button type="submit">
                <i class="fas fa-search"></i> Search
            </button>
        </form>
    </section>

    <!-- Search Results -->
    <?php if (!empty($search_query)): ?>
        <div class="cafe-results">
            <?php if ($search_type == 'product' && $is_customer): ?>
                <?php if (empty($products)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--text-gray);">
                        <p style="font-size: 18px;">No products found matching "<?php echo htmlspecialchars($search_query); ?>"</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <div class="cafe-card" onclick="window.location.href='customer_menu.php?cafe_id=<?php echo $product['cafe_id']; ?>'">
                            <?php if (!empty($product['image']) && file_exists($product['image'])): ?>
                                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['item_name']); ?>" style="height: 120px; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 100%; height: 120px; background: var(--accent-gray); border-radius: var(--radius-md); margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; font-size: 40px;">☕</div>
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($product['item_name']); ?></h3>
                            <p style="color: #10b981; font-size: 20px; font-weight: bold; margin: 8px 0;">
                                <?php echo formatCurrency($product['price']); ?>
                            </p>
                            <p><strong>📍</strong> <?php echo htmlspecialchars($product['cafe_name']); ?></p>
                            <?php if ($product['category_name']): ?>
                                <p>Category: <?php echo htmlspecialchars($product['category_name']); ?></p>
                            <?php endif; ?>
                            <p>Stock: <?php echo $product['stock']; ?> available</p>
                            <p style="color: var(--accent-color); font-weight: 600; margin-top: 12px;">Order Now <i class="fas fa-arrow-right"></i></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php else: ?>
                <?php if (empty($cafes)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--text-gray);">
                        <p style="font-size: 18px;">No cafés found matching "<?php echo htmlspecialchars($search_query); ?>"</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($cafes as $cafe): ?>
                        <div class="cafe-card" onclick="window.location.href='<?php echo $is_customer ? 'customer_menu.php' : 'menu.php'; ?>?cafe_id=<?php echo $cafe['cafe_id']; ?>'">
                            <?php if (!empty($cafe['logo']) && file_exists($cafe['logo'])): ?>
                                <img src="<?php echo htmlspecialchars($cafe['logo']); ?>" alt="<?php echo htmlspecialchars($cafe['cafe_name']); ?> Logo">
                            <?php else: ?>
                                <div style="width: 100px; height: 100px; background: var(--accent-gray); border-radius: var(--radius-md); margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; font-size: 40px;">☕</div>
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($cafe['cafe_name']); ?></h3>
                            <?php if (!empty($cafe['address'])): ?>
                                <p><strong>📍</strong> <?php echo htmlspecialchars($cafe['address']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($cafe['phone'])): ?>
                                <p><strong>📞</strong> <?php echo htmlspecialchars($cafe['phone']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($cafe['description'])): ?>
                                <p style="font-size: 13px; margin-top: 10px;"><?php echo htmlspecialchars(substr($cafe['description'], 0, 100)); ?><?php echo strlen($cafe['description']) > 100 ? '...' : ''; ?></p>
                            <?php endif; ?>
                            <p style="color: var(--accent-color); font-weight: 600; margin-top: 12px;">
                                <?php echo $is_customer ? 'Order from Menu <i class="fas fa-arrow-right"></i>' : 'View Menu <i class="fas fa-arrow-right"></i>'; ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- About Section -->
    <section class="section">
        <h2>About <?php echo APP_NAME; ?></h2>
        <p><?php echo APP_NAME; ?> is a comprehensive Point of Sale (POS) system designed specifically for café businesses. Our platform helps café owners manage their operations efficiently, from product inventory to sales transactions, all in one integrated system.</p>
        <p>Whether you're a small local café or a growing chain, <?php echo APP_NAME; ?> provides the tools you need to streamline your business operations, track sales, manage inventory, and provide excellent service to your customers.</p>
    </section>

    <!-- Advantages Section -->
    <section class="section">
        <h2>Why Choose <?php echo APP_NAME; ?>?</h2>
        <div class="features-grid">
            <div class="feature-card">
                <span class="feature-icon">💰</span>
                <h3>Easy Sales Management</h3>
                <p>Process transactions quickly with our intuitive POS interface. Support for multiple payment methods and voucher systems.</p>
            </div>
            <div class="feature-card">
                <span class="feature-icon">📊</span>
                <h3>Real-time Reports</h3>
                <p>Track your sales performance with detailed monthly reports, including graphs and analytics to help you make informed decisions.</p>
            </div>
            <div class="feature-card">
                <span class="feature-icon">📦</span>
                <h3>Inventory Control</h3>
                <p>Manage your product stock levels automatically. Get alerts when items are running low or out of stock.</p>
            </div>
            <div class="feature-card">
                <span class="feature-icon">👥</span>
                <h3>Multi-user Support</h3>
                <p>Add cashiers and manage staff access. Each user has their own account with appropriate permissions.</p>
            </div>
            <div class="feature-card">
                <span class="feature-icon">🎫</span>
                <h3>Voucher System</h3>
                <p>Create custom discount vouchers with flexible conditions to attract and retain customers.</p>
            </div>
            <div class="feature-card">
                <span class="feature-icon">🎨</span>
                <h3>Customizable</h3>
                <p>Personalize your café's branding with custom logos and theme colors to match your business identity.</p>
            </div>
        </div>
    </section>

    <!-- Tutorial Modal -->
    <?php if (!empty($tutorial_steps)): ?>
        <div class="tutorial-modal" id="tutorialModal">
            <div class="tutorial-content">
                <div class="tutorial-header">
                    <h2><i class="fas fa-graduation-cap"></i> Feature Tutorial</h2>
                    <button class="tutorial-close" onclick="closeTutorial()">&times;</button>
                </div>
                
                <?php foreach ($tutorial_steps as $category): ?>
                    <div class="tutorial-category">
                        <div class="tutorial-category-title">
                            <span class="icon"><?php echo $category['icon']; ?></span>
                            <h3><?php echo htmlspecialchars($category['title']); ?></h3>
                        </div>
                        <p><?php echo htmlspecialchars($category['description']); ?></p>
                        <div class="tutorial-steps-list">
                            <?php foreach ($category['steps'] as $step): ?>
                                <div class="tutorial-step-item">
                                    <div class="tutorial-step-header">
                                        <span class="icon"><?php echo htmlspecialchars($step['icon']); ?></span>
                                        <h4><?php echo htmlspecialchars($step['title']); ?></h4>
                                    </div>
                                    <p><?php echo htmlspecialchars($step['description']); ?></p>
                                    <?php if (isset($step['link'])): ?>
                                        <a href="<?php echo htmlspecialchars($step['link']); ?>" class="tutorial-step-link" target="_blank">
                                            Open Feature <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer-landing">
        <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
        <p style="margin-top: 10px; font-size: 14px;">Designed for modern café businesses</p>
    </footer>

    <script>
        function openTutorial() {
            document.getElementById('tutorialModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeTutorial() {
            document.getElementById('tutorialModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('tutorialModal');
            if (modal && e.target === modal) {
                closeTutorial();
            }
        });

        // Close modal with ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeTutorial();
            }
        });
    </script>
</body>
</html>
