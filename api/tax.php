<?php
/**
 * API Endpoint: Get Tax Percentage
 * GET /api/tax.php?cafe_id=1
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

$cafe_id = isset($_GET['cafe_id']) ? (int)$_GET['cafe_id'] : 0;

if ($cafe_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cafe ID is required']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $tax_percentage = 10.00; // Default
    try {
        $columns = $conn->query("SHOW COLUMNS FROM cafe_settings")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('tax_percentage', $columns)) {
            $stmt = $conn->prepare("SELECT tax_percentage FROM cafe_settings WHERE cafe_id = ?");
            $stmt->execute([$cafe_id]);
            $tax_setting = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($tax_setting && isset($tax_setting['tax_percentage']) && $tax_setting['tax_percentage'] !== null) {
                $tax_percentage = (float)$tax_setting['tax_percentage'];
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching tax percentage: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'tax_percentage' => $tax_percentage
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

