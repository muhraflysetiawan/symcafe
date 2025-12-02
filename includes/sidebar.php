<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Get café logo - optimized: cache in session to avoid repeated queries
$cafe_logo = null;
$cafe_id = getCafeId();

if ($cafe_id) {
    // Use session cache to avoid repeated database queries
    if (!isset($_SESSION['cafe_logo'])) {
        try {
            $db = new Database();
            $conn = $db->getConnection();
            // Direct query without expensive SHOW COLUMNS check
            $stmt = $conn->prepare("SELECT logo FROM cafes WHERE cafe_id = ?");
            $stmt->execute([$cafe_id]);
            $cafe = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cafe && !empty($cafe['logo'])) {
                $logo_path = dirname(__DIR__) . '/' . $cafe['logo'];
                $_SESSION['cafe_logo'] = file_exists($logo_path) ? $cafe['logo'] : null;
            } else {
                $_SESSION['cafe_logo'] = null;
            }
        } catch (PDOException $e) {
            $_SESSION['cafe_logo'] = null;
        }
    }
    $cafe_logo = $_SESSION['cafe_logo'];
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-header-content">
            <?php if ($cafe_logo): ?>
                <?php 
                // Determine correct path for logo based on current page location
                $logo_url = $cafe_logo;
                $current_dir = dirname($_SERVER['PHP_SELF']);
                if (strpos($current_dir, '/forms') !== false || strpos($current_dir, '\\forms') !== false) {
                    $logo_url = '../' . $cafe_logo;
                }
                ?>
                <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="Café Logo" class="sidebar-logo">
            <?php else: ?>
                <h2 class="sidebar-title"><?php echo APP_NAME; ?></h2>
            <?php endif; ?>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-item">
            <a href="<?php echo url('dashboard.php'); ?>" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" title="Dashboard">
                <i class="fas fa-chart-line"></i> <span class="nav-link-text">Dashboard</span>
            </a>
        </div>
        <?php if ($_SESSION['user_role'] == 'owner'): ?>
        <div class="nav-item">
            <a href="<?php echo url('products.php'); ?>" class="nav-link <?php echo $current_page == 'products.php' ? 'active' : ''; ?>" title="Products">
                <i class="fas fa-coffee"></i> <span class="nav-link-text">Products</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo url('categories.php'); ?>" class="nav-link <?php echo $current_page == 'categories.php' ? 'active' : ''; ?>" title="Categories">
                <i class="fas fa-folder"></i> <span class="nav-link-text">Categories</span>
            </a>
        </div>
        <?php endif; ?>
        <div class="nav-item">
            <a href="<?php echo url('pos.php'); ?>" class="nav-link <?php echo $current_page == 'pos.php' ? 'active' : ''; ?>" title="POS / Transactions">
                <i class="fas fa-cash-register"></i> <span class="nav-link-text">POS / Transactions</span>
            </a>
        </div>
        <?php if (in_array($_SESSION['user_role'], ['cashier', 'owner'])): ?>
        <div class="nav-item">
            <a href="<?php echo url('cashier_orders.php'); ?>" class="nav-link <?php echo $current_page == 'cashier_orders.php' ? 'active' : ''; ?>" title="Customer Orders">
                <i class="fas fa-clipboard-list"></i> <span class="nav-link-text">Customer Orders</span>
            </a>
        </div>
        <?php endif; ?>
        <div class="nav-item">
            <a href="<?php echo url('transactions.php'); ?>" class="nav-link <?php echo $current_page == 'transactions.php' ? 'active' : ''; ?>" title="Transaction History">
                <i class="fas fa-history"></i> <span class="nav-link-text">Transaction History</span>
            </a>
        </div>
        <?php if ($_SESSION['user_role'] == 'owner'): ?>
        <div class="nav-item">
            <a href="<?php echo url('cashiers.php'); ?>" class="nav-link <?php echo $current_page == 'cashiers.php' ? 'active' : ''; ?>" title="Cashiers">
                <i class="fas fa-users"></i> <span class="nav-link-text">Cashiers</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo url('payment_categories.php'); ?>" class="nav-link <?php echo $current_page == 'payment_categories.php' || $current_page == 'payment_category_form.php' || (isset($_GET['page']) && $_GET['page'] == 'payment_category_form') ? 'active' : ''; ?>" title="Payment Categories">
                <i class="fas fa-credit-card"></i> <span class="nav-link-text">Payment Categories</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo url('voucher_analytics.php'); ?>" class="nav-link <?php echo $current_page == 'voucher_analytics.php' || $current_page == 'vouchers.php' || $current_page == 'voucher_form.php' ? 'active' : ''; ?>" title="Vouchers & Analytics">
                <i class="fas fa-ticket-alt"></i> <span class="nav-link-text">Vouchers & Analytics</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo url('product_analytics.php'); ?>" class="nav-link <?php echo $current_page == 'product_analytics.php' ? 'active' : ''; ?>" title="Product Performance Analytics">
                <i class="fas fa-chart-bar"></i> <span class="nav-link-text">Product Analytics</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo url('theme_settings.php'); ?>" class="nav-link <?php echo $current_page == 'theme_settings.php' ? 'active' : ''; ?>" title="Theme Settings">
                <i class="fas fa-palette"></i> <span class="nav-link-text">Theme Settings</span>
            </a>
        </div>
        <?php endif; ?>
        <div class="nav-item">
            <a href="<?php echo url('profile.php'); ?>" class="nav-link <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>" title="Profile">
                <i class="fas fa-user"></i> <span class="nav-link-text">Profile</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo url('logout.php'); ?>" class="nav-link" title="Logout">
                <i class="fas fa-sign-out-alt"></i> <span class="nav-link-text">Logout</span>
            </a>
        </div>
    </nav>
</aside>

