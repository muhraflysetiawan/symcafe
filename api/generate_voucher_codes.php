<?php
/**
 * API Endpoint: Generate Unique Voucher Codes with QR Codes
 * POST /api/generate_voucher_codes.php
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

requireLogin();
requireCafeSetup();
requireRole(['owner']);

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

$data = json_decode(file_get_contents('php://input'), true);
$voucher_id = isset($data['voucher_id']) ? (int)$data['voucher_id'] : 0;
$quantity = isset($data['quantity']) ? (int)$data['quantity'] : 1;

if ($voucher_id <= 0 || $quantity <= 0 || $quantity > 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid voucher ID or quantity']);
    exit();
}

try {
    // Verify voucher exists and belongs to cafe
    $stmt = $conn->prepare("SELECT * FROM vouchers WHERE voucher_id = ? AND cafe_id = ?");
    $stmt->execute([$voucher_id, $cafe_id]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$voucher) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Voucher not found']);
        exit();
    }
    
    // Check if QR code already exists
    $stmt = $conn->prepare("SELECT code_id FROM voucher_codes WHERE voucher_id = ? LIMIT 1");
    $stmt->execute([$voucher_id]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'QR code already exists for this voucher']);
        exit();
    }
    
    // Generate single QR code
    $unique_code = strtoupper($voucher['voucher_code']);
    $stmt = $conn->prepare("INSERT INTO voucher_codes (voucher_id, unique_code, qr_code_data) VALUES (?, ?, ?)");
    $stmt->execute([$voucher_id, $unique_code, $unique_code]);
    
    $code_id = $conn->lastInsertId();
    
    $generated_codes = [[
        'code_id' => $code_id,
        'unique_code' => $unique_code,
        'qr_code_data' => $unique_code
    ]];
    
    echo json_encode([
        'success' => true,
        'message' => "Generated {$quantity} unique code(s) successfully",
        'codes' => $generated_codes
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

