<?php
/**
 * Product Analytics API Endpoint
 * Provides real-time analytics data via AJAX
 */

require_once __DIR__ . '/../config/config.php';
requireLogin();
requireCafeSetup();
requireRole(['owner']);

require_once __DIR__ . '/../config/functions_product_analytics.php';

header('Content-Type: application/json');

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_product_sales':
            $item_id = (int)($_GET['item_id'] ?? 0);
            $period = $_GET['period'] ?? 'weekly';
            
            if ($item_id <= 0) {
                throw new Exception('Invalid product ID');
            }
            
            if ($period == 'weekly') {
                $data = getWeeklySales($conn, $cafe_id, $item_id, 8);
            } else {
                $data = getMonthlySales($conn, $cafe_id, $item_id, 6);
            }
            
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'get_peak_hours':
            $item_id = (int)($_GET['item_id'] ?? 0);
            $weeks = (int)($_GET['weeks'] ?? 4);
            
            if ($item_id <= 0) {
                throw new Exception('Invalid product ID');
            }
            
            $period_start = date('Y-m-d', strtotime("-$weeks weeks monday"));
            $period_end = date('Y-m-d');
            $data = getPeakHours($conn, $cafe_id, $item_id, $period_start, $period_end);
            
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'get_trend':
            $item_id = (int)($_GET['item_id'] ?? 0);
            $weeks = (int)($_GET['weeks'] ?? 4);
            
            if ($item_id <= 0) {
                throw new Exception('Invalid product ID');
            }
            
            $data = detectTrend($conn, $cafe_id, $item_id, $weeks);
            
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'simulate':
            $item_id = (int)($_POST['item_id'] ?? 0);
            $new_price = (float)($_POST['new_price'] ?? 0);
            $demand_change = (float)($_POST['demand_change'] ?? 0);
            
            if ($item_id <= 0 || $new_price <= 0) {
                throw new Exception('Invalid parameters');
            }
            
            $data = simulateProfitImpact($conn, $cafe_id, $item_id, $new_price, $demand_change);
            
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'get_low_demand':
            $settings = getAnalyticsSettings($conn, $cafe_id);
            $data = detectLowDemandProducts($conn, $cafe_id, $settings);
            
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'get_high_demand':
            $settings = getAnalyticsSettings($conn, $cafe_id);
            $data = detectHighDemandProducts($conn, $cafe_id, $settings);
            
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

