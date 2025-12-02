<?php
// Optimized: cache cafe name in session to avoid repeated queries
$cafe_name = 'My Café';
$cafe_id = getCafeId();

if ($cafe_id) {
    if (!isset($_SESSION['cafe_name'])) {
        try {
            $db = new Database();
            $conn = $db->getConnection();
            $stmt = $conn->prepare("SELECT cafe_name FROM cafes WHERE cafe_id = ?");
            $stmt->execute([$cafe_id]);
            $cafe = $stmt->fetch(PDO::FETCH_ASSOC);
            $_SESSION['cafe_name'] = $cafe ? $cafe['cafe_name'] : 'My Café';
        } catch (Exception $e) {
            $_SESSION['cafe_name'] = 'My Café';
        }
    }
    $cafe_name = $_SESSION['cafe_name'];
}
?>
<nav class="top-nav">
    <div class="top-nav-left">
        <button class="mobile-menu-btn">
            <i class="fas fa-bars"></i>
        </button>
        <h1><?php echo htmlspecialchars($cafe_name); ?></h1>
    </div>
    <div class="user-info">
        <div class="clock-container">
            <div class="clock-time" id="clockTime"></div>
            <div class="clock-date" id="clockDate"></div>
        </div>
        <div class="user-details">
            <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></div>
            <div class="user-role"><?php echo htmlspecialchars($_SESSION['user_role'] ?? 'user'); ?></div>
        </div>
        <div class="user-avatar">
            <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
        </div>
    </div>
</nav>

