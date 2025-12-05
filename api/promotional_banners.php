<?php
/**
 * API Endpoint: Get Promotional Banners
 * GET /api/promotional_banners.php
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if promotional_banners table exists, if not create it
    try {
        $conn->query("SELECT 1 FROM promotional_banners LIMIT 1");
    } catch (Exception $e) {
        // Table doesn't exist, create it
        $conn->exec("
            CREATE TABLE IF NOT EXISTS `promotional_banners` (
              `banner_id` int(11) NOT NULL AUTO_INCREMENT,
              `banner_image` varchar(255) NOT NULL,
              `banner_title` varchar(255) DEFAULT NULL,
              `banner_link` varchar(255) DEFAULT NULL,
              `display_order` int(11) DEFAULT 0,
              `is_active` tinyint(1) DEFAULT 1,
              `is_archived` tinyint(1) DEFAULT 0,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`banner_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        
        // Insert default placeholder banners
        $defaultBanners = [
            ['assets/bg.jpg', 'Welcome to SYMCAFE', '#', 1],
            ['assets/bg.jpg', 'Special Offers', '#', 2],
            ['assets/bg.jpg', 'New Products', '#', 3],
            ['assets/bg.jpg', 'Best Coffee Experience', '#', 4],
        ];
        
        $stmt = $conn->prepare("
            INSERT INTO promotional_banners (banner_image, banner_title, banner_link, display_order, is_active) 
            VALUES (?, ?, ?, ?, 1)
        ");
        
        foreach ($defaultBanners as $banner) {
            $stmt->execute($banner);
        }
    }
    
    // Get active, non-archived banners
    $stmt = $conn->prepare("
        SELECT banner_id, banner_image, banner_title, banner_link, display_order 
        FROM promotional_banners 
        WHERE is_active = 1 AND is_archived = 0 
        ORDER BY display_order ASC, banner_id ASC
        LIMIT 10
    ");
    $stmt->execute();
    $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return relative image path - let client construct full URL
    // This allows mobile app to use its own BASE_URL (with IP address instead of localhost)
    foreach ($banners as &$banner) {
        $banner_path = __DIR__ . '/../' . $banner['banner_image'];
        if ($banner['banner_image'] && file_exists($banner_path)) {
            $banner['banner_image_url'] = $banner['banner_image']; // Return relative path
        } else {
            $banner['banner_image_url'] = 'assets/bg.jpg'; // Fallback - relative path
        }
    }
    
    echo json_encode([
        'success' => true,
        'banners' => $banners
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

