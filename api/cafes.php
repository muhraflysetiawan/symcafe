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
    
    $stmt = $conn->prepare("SELECT cafe_id, cafe_name, address, description, phone, logo FROM cafes ORDER BY cafe_name");
    $stmt->execute();
    $cafes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert logo path to full URL if exists
    foreach ($cafes as &$cafe) {
        if ($cafe['logo'] && file_exists($cafe['logo'])) {
            $cafe['logo_url'] = BASE_URL . $cafe['logo'];
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

