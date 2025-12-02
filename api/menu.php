<?php
/**
 * API Endpoint: Get Menu Items by Cafe
 * GET /api/menu.php?cafe_id=1
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
    
    // Get cafe info
    $stmt = $conn->prepare("SELECT cafe_id, cafe_name, address, phone, logo FROM cafes WHERE cafe_id = ?");
    $stmt->execute([$cafe_id]);
    $cafe = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cafe) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Cafe not found']);
        exit();
    }
    
    // Get categories
    $stmt = $conn->prepare("SELECT category_id, category_name FROM menu_categories WHERE cafe_id = ? ORDER BY category_name");
    $stmt->execute([$cafe_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get products
    $stmt = $conn->prepare("
        SELECT mi.*, mc.category_name 
        FROM menu_items mi 
        LEFT JOIN menu_categories mc ON mi.category_id = mc.category_id 
        WHERE mi.cafe_id = ? AND mi.status = 'available' AND mi.stock > 0
        ORDER BY mc.category_name, mi.item_name
    ");
    $stmt->execute([$cafe_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get variations and add-ons for all products
    $product_variations = [];
    $product_addons = [];
    
    try {
        foreach ($products as $product) {
            $item_id = $product['item_id'];
            
            // Get variations
            $stmt = $conn->prepare("
                SELECT v.variation_id, v.variation_name, v.is_required, v.variation_type,
                       o.option_id, o.option_name, o.price_adjustment, o.is_default
                FROM product_variation_assignments pva
                JOIN product_variations v ON pva.variation_id = v.variation_id
                LEFT JOIN variation_options o ON v.variation_id = o.variation_id
                WHERE pva.item_id = ?
                ORDER BY v.display_order, o.display_order, o.option_name
            ");
            $stmt->execute([$item_id]);
            $variations_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group by variation
            $variations = [];
            foreach ($variations_data as $row) {
                $var_id = $row['variation_id'];
                if (!isset($variations[$var_id])) {
                    $variations[$var_id] = [
                        'variation_id' => $var_id,
                        'variation_name' => $row['variation_name'],
                        'is_required' => $row['is_required'],
                        'variation_type' => $row['variation_type'],
                        'options' => []
                    ];
                }
                if ($row['option_id']) {
                    $variations[$var_id]['options'][] = [
                        'option_id' => $row['option_id'],
                        'option_name' => $row['option_name'],
                        'price_adjustment' => floatval($row['price_adjustment']),
                        'is_default' => $row['is_default']
                    ];
                }
            }
            $product_variations[$item_id] = array_values($variations);
            
            // Get add-ons
            $stmt = $conn->prepare("
                SELECT a.addon_id, a.addon_name, a.addon_category, a.price
                FROM product_addon_assignments paa
                JOIN product_addons a ON paa.addon_id = a.addon_id
                WHERE paa.item_id = ? AND a.is_active = 1
                ORDER BY a.display_order, a.addon_name
            ");
            $stmt->execute([$item_id]);
            $product_addons[$item_id] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convert image path to full URL
            if ($product['image'] && file_exists($product['image'])) {
                $product['image_url'] = BASE_URL . $product['image'];
            } else {
                $product['image_url'] = null;
            }
        }
    } catch (Exception $e) {
        // Tables might not exist yet
        error_log("Error fetching variations/addons: " . $e->getMessage());
    }
    
    // Convert cafe logo to full URL
    if ($cafe['logo'] && file_exists($cafe['logo'])) {
        $cafe['logo_url'] = BASE_URL . $cafe['logo'];
    } else {
        $cafe['logo_url'] = null;
    }
    
    echo json_encode([
        'success' => true,
        'cafe' => $cafe,
        'categories' => $categories,
        'products' => $products,
        'variations' => $product_variations,
        'addons' => $product_addons
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

