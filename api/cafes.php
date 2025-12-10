<?php
/**
 * API Endpoint: Get List of Cafes
 * GET /api/cafes.php
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
    
    // Handle search query
    $search_query = isset($_GET['search']) ? trim(sanitizeInput($_GET['search'])) : '';
    
    if (!empty($search_query)) {
        $stmt = $conn->prepare("
            SELECT cafe_id, cafe_name, address, description, phone, logo 
            FROM cafes 
            WHERE cafe_name LIKE ? OR address LIKE ? OR description LIKE ?
            ORDER BY cafe_name
        ");
        $search_term = '%' . $search_query . '%';
        $stmt->execute([$search_term, $search_term, $search_term]);
    } else {
        $stmt = $conn->prepare("SELECT cafe_id, cafe_name, address, description, phone, logo FROM cafes ORDER BY cafe_name");
        $stmt->execute();
    }
    
    $cafes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return logo path (relative to root) - let client construct full URL
    // This allows mobile app to use its own BASE_URL (with IP address instead of localhost)
    foreach ($cafes as &$cafe) {
        if (!empty($cafe['logo'])) {
            // Logo path is stored relative to root (e.g., "uploads/logos/cafe_1_123.jpg")
            // Check if file exists using correct path (from api/ directory, need to go up one level)
            $logo_path = __DIR__ . '/../' . $cafe['logo'];
            if (file_exists($logo_path)) {
                // Return relative path - client will prepend its BASE_URL
                $cafe['logo_url'] = $cafe['logo'];
            } else {
                $cafe['logo_url'] = null;
            }
        } else {
            $cafe['logo_url'] = null;
        }
    }
    
    echo json_encode([
        'success' => true,
        'cafes' => $cafes
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

