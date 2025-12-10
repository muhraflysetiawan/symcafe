<?php
/**
 * API Endpoint: Validate Unique Voucher Code
 * POST /api/validate_voucher_code.php
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

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

$data = json_decode(file_get_contents('php://input'), true);
$unique_code = isset($data['unique_code']) ? strtoupper(trim($data['unique_code'])) : '';
$subtotal = isset($data['subtotal']) ? (float)$data['subtotal'] : 0;

if (empty($unique_code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Voucher code is required']);
    exit();
}

try {
    // Find the QR code for this voucher code
    $stmt = $conn->prepare("
        SELECT vc.*, v.* 
        FROM voucher_codes vc
        INNER JOIN vouchers v ON vc.voucher_id = v.voucher_id
        WHERE vc.unique_code = ? AND v.cafe_id = ?
        LIMIT 1
    ");
    $stmt->execute([$unique_code, $cafe_id]);
    $code_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$code_data) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid voucher code'
        ]);
        exit();
    }
    
    // Check if QR code is used (if it was marked as used, it means it was deleted)
    // But we'll check usage_limit instead
    if ($code_data['usage_limit'] && $code_data['used_count'] >= $code_data['usage_limit']) {
        echo json_encode([
            'success' => false,
            'message' => 'Voucher usage limit reached'
        ]);
        exit();
    }
    
    // Check if voucher is active
    if (!$code_data['is_active']) {
        echo json_encode([
            'success' => false,
            'message' => 'Voucher is not active'
        ]);
        exit();
    }
    
    // Check validity dates
    $today = date('Y-m-d');
    if ($code_data['valid_from'] && $code_data['valid_from'] > $today) {
        echo json_encode([
            'success' => false,
            'message' => 'Voucher is not yet valid'
        ]);
        exit();
    }
    
    if ($code_data['valid_until'] && $code_data['valid_until'] < $today) {
        echo json_encode([
            'success' => false,
            'message' => 'Voucher has expired'
        ]);
        exit();
    }
    
    // Validate minimum order amount
    if ($code_data['min_order_amount'] > 0 && $subtotal < $code_data['min_order_amount']) {
        echo json_encode([
            'success' => false,
            'message' => 'Minimum order: ' . formatCurrency($code_data['min_order_amount']),
            'min_order_amount' => $code_data['min_order_amount']
        ]);
        exit();
    }
    
    // Validate maximum order amount
    if ($code_data['max_order_amount'] && $subtotal > $code_data['max_order_amount']) {
        echo json_encode([
            'success' => false,
            'message' => 'Maximum order: ' . formatCurrency($code_data['max_order_amount']),
            'max_order_amount' => $code_data['max_order_amount']
        ]);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'voucher_id' => $code_data['voucher_id'],
        'voucher_code_id' => $code_data['code_id'],
        'discount_amount' => $code_data['discount_amount'],
        'voucher_code' => $code_data['voucher_code'],
        'unique_code' => $code_data['unique_code']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

