<?php
require_once 'config/config.php';
requireLogin();
requireCafeSetup();
requireRole(['owner']);

require_once 'config/functions_voucher_analytics.php';

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

// Get date range
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$voucher_id = isset($_GET['voucher_id']) ? (int)$_GET['voucher_id'] : 0;
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'performance';

// Get all vouchers
$stmt = $conn->prepare("SELECT * FROM vouchers WHERE cafe_id = ? ORDER BY created_at DESC");
$stmt->execute([$cafe_id]);
$all_vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected voucher
$selected_voucher = null;
if ($voucher_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM vouchers WHERE voucher_id = ? AND cafe_id = ?");
    $stmt->execute([$voucher_id, $cafe_id]);
    $selected_voucher = $stmt->fetch(PDO::FETCH_ASSOC);
}

$page_title = 'Voucher Analytics';
include 'includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">Voucher & Promotion Analytics</h2>

<!-- Date Range and Voucher Selection -->
<div class="form-container" style="margin-bottom: 30px; padding: 20px;">
    <form method="GET" style="display: grid; grid-template-columns: 1fr 1fr 2fr auto; gap: 15px; align-items: end;">
        <div class="form-group" style="margin: 0;">
            <label for="start_date">Start Date</label>
            <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" required>
        </div>
        <div class="form-group" style="margin: 0;">
            <label for="end_date">End Date</label>
            <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" required>
        </div>
        <div class="form-group" style="margin: 0;">
            <label for="voucher_id">Select Voucher (Optional)</label>
            <select id="voucher_id" name="voucher_id">
                <option value="0">All Vouchers</option>
                <?php foreach ($all_vouchers as $voucher): ?>
                    <option value="<?php echo $voucher['voucher_id']; ?>" <?php echo $voucher_id == $voucher['voucher_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($voucher['voucher_code']); ?> - <?php echo formatCurrency($voucher['discount_amount']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin: 0;">
            <input type="hidden" name="tab" value="<?php echo $active_tab; ?>">
            <button type="submit" class="btn btn-primary">Analyze</button>
        </div>
    </form>
</div>

<!-- Analytics Tabs -->
<div style="border-bottom: 1px solid var(--border-gray); margin-bottom: 30px;">
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="?tab=vouchers&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&voucher_id=<?php echo $voucher_id; ?>" 
           style="padding: 12px 20px; color: <?php echo $active_tab == 'vouchers' ? 'var(--primary-white)' : 'var(--text-gray)'; ?>; text-decoration: none; border-bottom: 2px solid <?php echo $active_tab == 'vouchers' ? 'var(--primary-white)' : 'transparent'; ?>; font-weight: <?php echo $active_tab == 'vouchers' ? '600' : '400'; ?>; transition: all 0.3s;">
            🎫 Manage Vouchers
        </a>
        <a href="?tab=performance&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&voucher_id=<?php echo $voucher_id; ?>" 
           style="padding: 12px 20px; color: <?php echo $active_tab == 'performance' ? 'var(--primary-white)' : 'var(--text-gray)'; ?>; text-decoration: none; border-bottom: 2px solid <?php echo $active_tab == 'performance' ? 'var(--primary-white)' : 'transparent'; ?>; font-weight: <?php echo $active_tab == 'performance' ? '600' : '400'; ?>; transition: all 0.3s;">
            📊 Performance Analytics
        </a>
        <a href="?tab=profit&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&voucher_id=<?php echo $voucher_id; ?>" 
           style="padding: 12px 20px; color: <?php echo $active_tab == 'profit' ? 'var(--primary-white)' : 'var(--text-gray)'; ?>; text-decoration: none; border-bottom: 2px solid <?php echo $active_tab == 'profit' ? 'var(--primary-white)' : 'transparent'; ?>; font-weight: <?php echo $active_tab == 'profit' ? '600' : '400'; ?>; transition: all 0.3s;">
            💰 Profit Impact
        </a>
        <a href="?tab=product&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&voucher_id=<?php echo $voucher_id; ?>" 
           style="padding: 12px 20px; color: <?php echo $active_tab == 'product' ? 'var(--primary-white)' : 'var(--text-gray)'; ?>; text-decoration: none; border-bottom: 2px solid <?php echo $active_tab == 'product' ? 'var(--primary-white)' : 'transparent'; ?>; font-weight: <?php echo $active_tab == 'product' ? '600' : '400'; ?>; transition: all 0.3s;">
            📦 Product Impact
        </a>
    </div>
</div>

<?php if ($active_tab == 'vouchers'): ?>
    <!-- Voucher Management Tab -->
    <?php
    $message = '';
    $message_type = '';
    
    // Handle delete
    if (isset($_GET['delete'])) {
        $del_voucher_id = (int)$_GET['delete'];
        try {
            $stmt = $conn->prepare("DELETE FROM vouchers WHERE voucher_id = ? AND cafe_id = ?");
            if ($stmt->execute([$del_voucher_id, $cafe_id])) {
                $message = 'Voucher deleted successfully';
                $message_type = 'success';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    // Handle toggle active
    if (isset($_GET['toggle'])) {
        $toggle_voucher_id = (int)$_GET['toggle'];
        try {
            $stmt = $conn->prepare("UPDATE vouchers SET is_active = NOT is_active WHERE voucher_id = ? AND cafe_id = ?");
            if ($stmt->execute([$toggle_voucher_id, $cafe_id])) {
                $message = 'Voucher status updated';
                $message_type = 'success';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    // Get all vouchers with usage stats
    $stmt = $conn->prepare("
        SELECT v.*, 
               COUNT(DISTINCT vul.log_id) as total_uses,
               COUNT(DISTINCT vul.customer_id) as unique_customers
        FROM vouchers v
        LEFT JOIN voucher_usage_log vul ON v.voucher_id = vul.voucher_id
        WHERE v.cafe_id = ?
        GROUP BY v.voucher_id
        ORDER BY v.created_at DESC
    ");
    $stmt->execute([$cafe_id]);
    $vouchers_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get QR codes for each voucher
    foreach ($vouchers_list as &$voucher) {
        try {
            $stmt = $conn->prepare("SELECT code_id, unique_code, qr_code_data FROM voucher_codes WHERE voucher_id = ? LIMIT 1");
            $stmt->execute([$voucher['voucher_id']]);
            $qr_code = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If QR code entry exists but unique_code is empty, use voucher_code
            if ($qr_code && empty($qr_code['unique_code'])) {
                $qr_code['unique_code'] = $voucher['voucher_code'];
            }
            
            $voucher['qr_code'] = $qr_code ?: null;
        } catch (Exception $e) {
            // If table doesn't exist or error, set to null
            $voucher['qr_code'] = null;
        }
    }
    unset($voucher);
    ?>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type == 'error' ? 'error' : 'success'; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <div class="table-container">
        <div class="table-header">
            <div class="table-title">Voucher Management</div>
            <a href="forms/voucher_form.php" class="btn btn-primary btn-sm">Add New Voucher</a>
        </div>
        
        <?php if (empty($vouchers_list)): ?>
            <div style="padding: 20px; text-align: center; color: var(--text-gray);">
                No vouchers found. <a href="forms/voucher_form.php" style="color: var(--primary-white);">Create your first voucher</a>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Voucher Code</th>
                        <th>QR Code</th>
                        <th>Discount Amount</th>
                        <th>Conditions</th>
                        <th>Valid Period</th>
                        <th>Usage</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vouchers_list as $voucher): ?>
                        <tr>
                            <td style="font-weight: 600; color: var(--primary-white);">
                                <code style="background: var(--primary-black); padding: 4px 8px; border-radius: 4px; color: var(--primary-white); font-size: 14px;">
                                    <?php echo htmlspecialchars($voucher['voucher_code']); ?>
                                </code>
                            </td>
                            <td>
                                <?php 
                                // Determine QR code data - use unique_code if available, otherwise use voucher_code
                                $qr_data = null;
                                if (!empty($voucher['qr_code'])) {
                                    // Check unique_code first
                                    if (!empty($voucher['qr_code']['unique_code'])) {
                                        $qr_data = $voucher['qr_code']['unique_code'];
                                    } 
                                    // Then check qr_code_data
                                    elseif (!empty($voucher['qr_code']['qr_code_data'])) {
                                        $qr_data = $voucher['qr_code']['qr_code_data'];
                                    }
                                }
                                
                                // Final fallback: use voucher code directly
                                if (empty($qr_data) && !empty($voucher['voucher_code'])) {
                                    $qr_data = $voucher['voucher_code'];
                                }
                                ?>
                                
                                <?php if ($qr_data): ?>
                                    <div style="display: flex; flex-direction: column; align-items: center; gap: 5px;">
                                        <img src="api/generate_qr_code.php?data=<?php echo urlencode($qr_data); ?>&size=80&t=<?php echo time(); ?>" 
                                             alt="QR Code" 
                                             style="width: 80px; height: 80px; border: 1px solid var(--border-gray); border-radius: 4px; background: #fff; padding: 5px;"
                                             onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'80\' height=\'80\'%3E%3Crect width=\'80\' height=\'80\' fill=\'%23fff\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' fill=\'%23999\' font-size=\'10\'%3ELoading...%3C/text%3E%3C/svg%3E';">
                                        <a href="api/generate_qr_code.php?data=<?php echo urlencode($qr_data); ?>&size=400" 
                                           download="<?php echo htmlspecialchars($voucher['voucher_code']); ?>_QR.png"
                                           class="btn btn-primary btn-sm"
                                           style="font-size: 11px; padding: 4px 8px;">
                                            Download
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div style="display: flex; flex-direction: column; align-items: center; gap: 5px;">
                                        <span style="color: var(--text-gray); font-size: 11px;">No QR Code</span>
                                        <a href="forms/voucher_form.php?id=<?php echo $voucher['voucher_id']; ?>" 
                                           class="btn btn-secondary btn-sm"
                                           style="font-size: 10px; padding: 3px 6px;"
                                           title="Edit voucher to enable QR code">
                                            Enable QR
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="color: #28a745; font-weight: 600;"><?php echo formatCurrency($voucher['discount_amount']); ?></td>
                            <td style="font-size: 12px; color: var(--text-gray);">
                                <?php if ($voucher['min_order_amount'] > 0): ?>
                                    Min: <?php echo formatCurrency($voucher['min_order_amount']); ?><br>
                                <?php endif; ?>
                                <?php if ($voucher['max_order_amount']): ?>
                                    Max: <?php echo formatCurrency($voucher['max_order_amount']); ?><br>
                                <?php endif; ?>
                                <?php if ($voucher['usage_limit']): ?>
                                    Limit: <?php echo $voucher['used_count']; ?> / <?php echo $voucher['usage_limit']; ?><br>
                                <?php endif; ?>
                                <?php if (empty($voucher['min_order_amount']) && !$voucher['max_order_amount'] && !$voucher['usage_limit']): ?>
                                    No restrictions
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($voucher['valid_from']): ?>
                                    From: <?php echo date('d M Y', strtotime($voucher['valid_from'])); ?><br>
                                <?php endif; ?>
                                <?php if ($voucher['valid_until']): ?>
                                    Until: <?php echo date('d M Y', strtotime($voucher['valid_until'])); ?>
                                <?php else: ?>
                                    No expiry
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size: 12px;">
                                    <div>Total: <?php echo $voucher['total_uses']; ?> uses</div>
                                    <div style="color: var(--text-gray);">Unique: <?php echo $voucher['unique_customers']; ?> customers</div>
                                </div>
                            </td>
                            <td>
                                <?php if ($voucher['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="forms/voucher_form.php?id=<?php echo $voucher['voucher_id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                <a href="?tab=vouchers&toggle=<?php echo $voucher['voucher_id']; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                                   class="btn btn-<?php echo $voucher['is_active'] ? 'warning' : 'success'; ?> btn-sm"
                                   onclick="return confirm('<?php echo $voucher['is_active'] ? 'Deactivate' : 'Activate'; ?> this voucher?')">
                                    <?php echo $voucher['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                </a>
                                <a href="?tab=vouchers&delete=<?php echo $voucher['voucher_id']; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Are you sure you want to delete this voucher?')">Delete</a>
                                <a href="?tab=performance&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&voucher_id=<?php echo $voucher['voucher_id']; ?>" 
                                   class="btn btn-secondary btn-sm">Analytics</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

<?php elseif ($active_tab == 'performance'): ?>
    <!-- Voucher Performance Analytics -->
    <?php
    if ($voucher_id > 0 && $selected_voucher) {
        // Single voucher analysis
        $performance = calculateVoucherPerformance($conn, $voucher_id, $start_date, $end_date);
        
        if ($performance) {
            // Get usage over time
            $stmt = $conn->prepare("
                SELECT DATE(created_at) as usage_date, COUNT(*) as usage_count
                FROM orders
                WHERE voucher_id = ?
                AND DATE(created_at) BETWEEN ? AND ?
                GROUP BY DATE(created_at)
                ORDER BY usage_date
            ");
            $stmt->execute([$voucher_id, $start_date, $end_date]);
            $usage_over_time = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        // All vouchers comparison
        $all_performances = [];
        foreach ($all_vouchers as $voucher) {
            $perf = calculateVoucherPerformance($conn, $voucher['voucher_id'], $start_date, $end_date);
            if ($perf) {
                $perf['voucher_id'] = $voucher['voucher_id'];
                $perf['voucher_code'] = $voucher['voucher_code'];
                $perf['discount_amount'] = $voucher['discount_amount'];
                $all_performances[] = $perf;
            }
        }
        
        // Sort by performance (total uses)
        usort($all_performances, function($a, $b) {
            return $b['total_uses'] - $a['total_uses'];
        });
    }
    ?>
    
    <?php if ($voucher_id > 0 && $selected_voucher && isset($performance)): ?>
        <!-- Single Voucher Performance -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
                <div style="color: var(--text-gray); font-size: 14px; margin-bottom: 5px;">Total Uses</div>
                <div style="color: var(--primary-white); font-size: 28px; font-weight: bold;"><?php echo $performance['total_uses']; ?></div>
            </div>
            <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
                <div style="color: var(--text-gray); font-size: 14px; margin-bottom: 5px;">Unique Customers</div>
                <div style="color: var(--primary-white); font-size: 28px; font-weight: bold;"><?php echo $performance['unique_customers']; ?></div>
            </div>
            <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
                <div style="color: var(--text-gray); font-size: 14px; margin-bottom: 5px;">Redemption Rate</div>
                <div style="color: var(--primary-white); font-size: 28px; font-weight: bold;"><?php echo number_format($performance['redemption_rate'], 1); ?>%</div>
            </div>
            <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
                <div style="color: var(--text-gray); font-size: 14px; margin-bottom: 5px;">Total Discount Given</div>
                <div style="color: #dc3545; font-size: 28px; font-weight: bold;"><?php echo formatCurrency($performance['total_discount_given']); ?></div>
            </div>
            <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
                <div style="color: var(--text-gray); font-size: 14px; margin-bottom: 5px;">Avg Order (With)</div>
                <div style="color: var(--primary-white); font-size: 24px; font-weight: bold;"><?php echo formatCurrency($performance['avg_order_value_with_voucher']); ?></div>
            </div>
            <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
                <div style="color: var(--text-gray); font-size: 14px; margin-bottom: 5px;">Avg Order (Without)</div>
                <div style="color: var(--primary-white); font-size: 24px; font-weight: bold;"><?php echo formatCurrency($performance['avg_order_value_without_voucher']); ?></div>
            </div>
        </div>
        
        <!-- Charts -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
            <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
                <h3 style="color: var(--primary-white); margin-bottom: 15px;">Usage Over Time</h3>
                <canvas id="usageChart" style="max-height: 300px;"></canvas>
            </div>
            <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
                <h3 style="color: var(--primary-white); margin-bottom: 15px;">Peak Usage Hours</h3>
                <canvas id="hourChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        // Usage over time chart
        const usageData = <?php echo json_encode($usage_over_time); ?>;
        const usageCtx = document.getElementById('usageChart').getContext('2d');
        new Chart(usageCtx, {
            type: 'line',
            data: {
                labels: usageData.map(d => d.usage_date),
                datasets: [{
                    label: 'Voucher Uses',
                    data: usageData.map(d => d.usage_count),
                    borderColor: '#FFFFFF',
                    backgroundColor: 'rgba(255, 255, 255, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: '#FFFFFF' } }
                },
                scales: {
                    x: { ticks: { color: '#FFFFFF' }, grid: { color: 'rgba(255,255,255,0.1)' } },
                    y: { ticks: { color: '#FFFFFF' }, grid: { color: 'rgba(255,255,255,0.1)' } }
                }
            }
        });
        
        // Peak hours chart
        const hourData = <?php echo json_encode($performance['hour_distribution']); ?>;
        const hourCtx = document.getElementById('hourChart').getContext('2d');
        const hourLabels = Array.from({length: 24}, (_, i) => i + ':00');
        const hourValues = hourLabels.map((_, i) => hourData[i] || 0);
        
        new Chart(hourCtx, {
            type: 'bar',
            data: {
                labels: hourLabels,
                datasets: [{
                    label: 'Uses per Hour',
                    data: hourValues,
                    backgroundColor: 'rgba(255, 255, 255, 0.5)',
                    borderColor: '#FFFFFF',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: '#FFFFFF' } }
                },
                scales: {
                    x: { ticks: { color: '#FFFFFF', maxRotation: 45 }, grid: { color: 'rgba(255,255,255,0.1)' } },
                    y: { ticks: { color: '#FFFFFF' }, grid: { color: 'rgba(255,255,255,0.1)' } }
                }
            }
        });
        </script>
        
    <?php else: ?>
        <!-- All Vouchers Comparison -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">Voucher Performance Comparison</div>
                <div style="display: flex; gap: 10px;">
                    <a href="?tab=performance&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&export=csv" class="btn btn-secondary btn-sm">📥 Export CSV</a>
                    <button onclick="window.print()" class="btn btn-secondary btn-sm">🖨️ Print Report</button>
                </div>
            </div>
            
            <?php if (empty($all_performances)): ?>
                <div style="padding: 20px; text-align: center; color: var(--text-gray);">
                    No voucher usage data found for the selected period.
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Voucher Code</th>
                            <th>Discount</th>
                            <th>Total Uses</th>
                            <th>Unique Customers</th>
                            <th>Redemption Rate</th>
                            <th>Avg Order (With)</th>
                            <th>Avg Order (Without)</th>
                            <th>Total Discount</th>
                            <th>Performance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $top_performer = $all_performances[0];
                        $underperformer = end($all_performances);
                        foreach ($all_performances as $perf): 
                            $is_top = $perf['voucher_id'] == $top_performer['voucher_id'];
                            $is_under = $perf['voucher_id'] == $underperformer['voucher_id'];
                        ?>
                            <tr>
                                <td style="font-weight: 600; color: var(--primary-white);">
                                    <?php echo htmlspecialchars($perf['voucher_code']); ?>
                                    <?php if ($is_top): ?>
                                        <span class="badge badge-success" style="margin-left: 10px;">⭐ Top Performer</span>
                                    <?php elseif ($is_under && count($all_performances) > 1): ?>
                                        <span class="badge badge-danger" style="margin-left: 10px;">⚠️ Underperforming</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatCurrency($perf['discount_amount']); ?></td>
                                <td><?php echo $perf['total_uses']; ?></td>
                                <td><?php echo $perf['unique_customers']; ?></td>
                                <td><?php echo number_format($perf['redemption_rate'], 1); ?>%</td>
                                <td><?php echo formatCurrency($perf['avg_order_value_with_voucher']); ?></td>
                                <td><?php echo formatCurrency($perf['avg_order_value_without_voucher']); ?></td>
                                <td><?php echo formatCurrency($perf['total_discount_given']); ?></td>
                                <td>
                                    <?php
                                    $aov_diff = $perf['avg_order_value_with_voucher'] - $perf['avg_order_value_without_voucher'];
                                    if ($aov_diff > 0) {
                                        echo '<span style="color: #28a745;">↑ Increased AOV</span>';
                                    } elseif ($aov_diff < 0) {
                                        echo '<span style="color: #dc3545;">↓ Decreased AOV</span>';
                                    } else {
                                        echo '<span style="color: var(--text-gray);">→ No change</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="?tab=performance&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&voucher_id=<?php echo $perf['voucher_id']; ?>" class="btn btn-primary btn-sm">View Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php elseif ($active_tab == 'profit' && $voucher_id > 0 && $selected_voucher): ?>
    <!-- Profit Impact Analysis -->
    <?php
    $profit_impact = calculateVoucherProfitImpact($conn, $voucher_id, $start_date, $end_date);
    ?>
    
    <?php if ($profit_impact): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
                <div style="color: var(--text-gray); font-size: 14px; margin-bottom: 5px;">Revenue Before Discount</div>
                <div style="color: var(--primary-white); font-size: 28px; font-weight: bold;"><?php echo formatCurrency($profit_impact['total_revenue_before_discount']); ?></div>
            </div>
            <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
                <div style="color: var(--text-gray); font-size: 14px; margin-bottom: 5px;">Total Discount Given</div>
                <div style="color: #dc3545; font-size: 28px; font-weight: bold;"><?php echo formatCurrency($profit_impact['total_discount_given']); ?></div>
            </div>
            <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
                <div style="color: var(--text-gray); font-size: 14px; margin-bottom: 5px;">Net Revenue After Discount</div>
                <div style="color: var(--primary-white); font-size: 28px; font-weight: bold;"><?php echo formatCurrency($profit_impact['net_revenue_after_discount']); ?></div>
            </div>
            <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
                <div style="color: var(--text-gray); font-size: 14px; margin-bottom: 5px;">Gross Profit Before</div>
                <div style="color: var(--primary-white); font-size: 24px; font-weight: bold;"><?php echo formatCurrency($profit_impact['gross_profit_before_voucher']); ?></div>
            </div>
            <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
                <div style="color: var(--text-gray); font-size: 14px; margin-bottom: 5px;">Gross Profit After</div>
                <div style="color: <?php echo $profit_impact['profit_difference'] >= 0 ? '#28a745' : '#dc3545'; ?>; font-size: 24px; font-weight: bold;">
                    <?php echo formatCurrency($profit_impact['gross_profit_after_voucher']); ?>
                </div>
            </div>
            <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
                <div style="color: var(--text-gray); font-size: 14px; margin-bottom: 5px;">Profit Impact</div>
                <div style="color: <?php echo $profit_impact['profit_impact_percent'] >= 0 ? '#28a745' : '#dc3545'; ?>; font-size: 28px; font-weight: bold;">
                    <?php echo number_format($profit_impact['profit_impact_percent'], 1); ?>%
                </div>
            </div>
        </div>
        
        <!-- Recommendation Box -->
        <div style="background: <?php echo $profit_impact['recommendation'] == 'discontinue' ? 'rgba(220, 53, 69, 0.1)' : ($profit_impact['recommendation'] == 'adjust' ? 'rgba(255, 193, 7, 0.1)' : 'rgba(40, 167, 69, 0.1)'); ?>; border: 2px solid <?php echo $profit_impact['recommendation'] == 'discontinue' ? '#dc3545' : ($profit_impact['recommendation'] == 'adjust' ? '#ffc107' : '#28a745'); ?>; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
            <h3 style="color: var(--primary-white); margin-bottom: 10px;">
                <?php if ($profit_impact['profit_difference'] >= 0): ?>
                    ✅ This voucher <?php echo $profit_impact['profit_difference'] > 0 ? 'increased' : 'maintained'; ?> profit
                <?php else: ?>
                    ⚠️ This voucher decreased profit by <?php echo formatCurrency(abs($profit_impact['profit_difference'])); ?>
                <?php endif; ?>
            </h3>
            <p style="color: var(--text-gray); margin: 0;">
                <strong>Recommendation:</strong> 
                <?php
                $rec_text = [
                    'continue' => 'Continue using this voucher - it has a positive or neutral impact on profitability.',
                    'adjust' => 'Consider adjusting the discount amount or conditions - the voucher is reducing profit.',
                    'discontinue' => 'Consider discontinuing this voucher - it significantly reduces profitability.'
                ];
                echo $rec_text[$profit_impact['recommendation']];
                ?>
            </p>
        </div>
        
        <!-- Charts -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
                <h3 style="color: var(--primary-white); margin-bottom: 15px;">Revenue Comparison</h3>
                <canvas id="revenueChart" style="max-height: 300px;"></canvas>
            </div>
            <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px;">
                <h3 style="color: var(--primary-white); margin-bottom: 15px;">Profit Margin Comparison</h3>
                <canvas id="marginChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        // Revenue comparison chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: ['Before Discount', 'After Discount'],
                datasets: [{
                    label: 'Revenue (Rp)',
                    data: [<?php echo $profit_impact['total_revenue_before_discount']; ?>, <?php echo $profit_impact['net_revenue_after_discount']; ?>],
                    backgroundColor: ['rgba(255, 255, 255, 0.5)', 'rgba(40, 167, 69, 0.5)'],
                    borderColor: ['#FFFFFF', '#28a745'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: '#FFFFFF' } }
                },
                scales: {
                    x: { ticks: { color: '#FFFFFF' }, grid: { color: 'rgba(255,255,255,0.1)' } },
                    y: { ticks: { color: '#FFFFFF', callback: function(value) { return 'Rp ' + value.toLocaleString('id-ID'); } }, grid: { color: 'rgba(255,255,255,0.1)' } }
                }
            }
        });
        
        // Profit margin chart
        const marginCtx = document.getElementById('marginChart').getContext('2d');
        new Chart(marginCtx, {
            type: 'bar',
            data: {
                labels: ['Before Voucher', 'After Voucher'],
                datasets: [{
                    label: 'Profit Margin (%)',
                    data: [<?php echo $profit_impact['profit_margin_before']; ?>, <?php echo $profit_impact['profit_margin_after']; ?>],
                    backgroundColor: ['rgba(255, 193, 7, 0.5)', 'rgba(40, 167, 69, 0.5)'],
                    borderColor: ['#ffc107', '#28a745'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: '#FFFFFF' } }
                },
                scales: {
                    x: { ticks: { color: '#FFFFFF' }, grid: { color: 'rgba(255,255,255,0.1)' } },
                    y: { ticks: { color: '#FFFFFF', callback: function(value) { return value + '%'; } }, grid: { color: 'rgba(255,255,255,0.1)' } }
                }
            }
        });
        </script>
    <?php else: ?>
        <div style="padding: 40px; text-align: center; color: var(--text-gray);">
            <p>No profit impact data available for the selected voucher and period.</p>
            <p style="margin-top: 10px;">Please select a voucher and ensure there are transactions in the selected date range.</p>
        </div>
    <?php endif; ?>

<?php elseif ($active_tab == 'product' && $voucher_id > 0 && $selected_voucher): ?>
    <!-- Product Impact Analysis -->
    <?php
    $product_impact = calculateVoucherProductImpact($conn, $voucher_id, $start_date, $end_date);
    
    // Get product names
    if (!empty($product_impact)) {
        $item_ids = array_column($product_impact, 'item_id');
        $placeholders = str_repeat('?,', count($item_ids) - 1) . '?';
        $stmt = $conn->prepare("SELECT item_id, item_name, category_id FROM menu_items WHERE item_id IN ($placeholders)");
        $stmt->execute($item_ids);
        $products_info = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products_info[$row['item_id']] = $row;
        }
        
        // Get categories
        $stmt = $conn->prepare("SELECT category_id, category_name FROM menu_categories WHERE cafe_id = ?");
        $stmt->execute([$cafe_id]);
        $categories = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categories[$row['category_id']] = $row['category_name'];
        }
        
        // Add product info to impact data
        foreach ($product_impact as &$impact) {
            $impact['product_name'] = $products_info[$impact['item_id']]['item_name'] ?? 'Unknown';
            $impact['category_name'] = $categories[$products_info[$impact['item_id']]['category_id'] ?? 0] ?? 'General';
        }
        
        // Sort by units sold with voucher
        usort($product_impact, function($a, $b) {
            return $b['units_sold_with_voucher'] - $a['units_sold_with_voucher'];
        });
    }
    ?>
    
    <?php if (!empty($product_impact)): ?>
        <div class="table-container" style="margin-bottom: 30px;">
            <div class="table-header">
                <div class="table-title">Product Performance with Voucher</div>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Units (With Voucher)</th>
                        <th>Units (Without Voucher)</th>
                        <th>Revenue (With)</th>
                        <th>Revenue (Without)</th>
                        <th>Profit (With)</th>
                        <th>Profit (Without)</th>
                        <th>Price Sensitivity</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($product_impact as $impact): ?>
                        <?php
                        $profit_diff = $impact['profit_with_voucher'] - $impact['profit_without_voucher'];
                        $is_negative = $profit_diff < 0;
                        ?>
                        <tr>
                            <td style="font-weight: 600; color: var(--primary-white);"><?php echo htmlspecialchars($impact['product_name']); ?></td>
                            <td><?php echo htmlspecialchars($impact['category_name']); ?></td>
                            <td><?php echo $impact['units_sold_with_voucher']; ?></td>
                            <td><?php echo $impact['units_sold_without_voucher']; ?></td>
                            <td><?php echo formatCurrency($impact['revenue_with_voucher']); ?></td>
                            <td><?php echo formatCurrency($impact['revenue_without_voucher']); ?></td>
                            <td style="color: <?php echo $impact['profit_with_voucher'] >= 0 ? '#28a745' : '#dc3545'; ?>;">
                                <?php echo formatCurrency($impact['profit_with_voucher']); ?>
                            </td>
                            <td><?php echo formatCurrency($impact['profit_without_voucher']); ?></td>
                            <td>
                                <span style="color: <?php echo $impact['price_sensitivity_score'] > 50 ? '#ffc107' : 'var(--text-gray)'; ?>; font-weight: 600;">
                                    <?php echo number_format($impact['price_sensitivity_score'], 1); ?>%
                                </span>
                            </td>
                            <td>
                                <?php if ($is_negative): ?>
                                    <span class="badge badge-danger">⚠️ Negative Profit</span>
                                <?php elseif ($impact['units_sold_with_voucher'] > $impact['units_sold_without_voucher']): ?>
                                    <span class="badge badge-success">↑ Increased Sales</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">→ No Impact</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Top Products Chart -->
        <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px; margin-bottom: 30px;">
            <h3 style="color: var(--primary-white); margin-bottom: 15px;">Top Products Influenced by Voucher</h3>
            <canvas id="productChart" style="max-height: 400px;"></canvas>
        </div>
        
        <!-- Category Heatmap -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">Category Performance During Promotion</div>
            </div>
            
            <?php
            // Group by category
            $category_performance = [];
            foreach ($product_impact as $impact) {
                $cat = $impact['category_name'];
                if (!isset($category_performance[$cat])) {
                    $category_performance[$cat] = [
                        'units_with' => 0,
                        'units_without' => 0,
                        'revenue_with' => 0,
                        'revenue_without' => 0,
                        'profit_with' => 0,
                        'profit_without' => 0
                    ];
                }
                $category_performance[$cat]['units_with'] += $impact['units_sold_with_voucher'];
                $category_performance[$cat]['units_without'] += $impact['units_sold_without_voucher'];
                $category_performance[$cat]['revenue_with'] += $impact['revenue_with_voucher'];
                $category_performance[$cat]['revenue_without'] += $impact['revenue_without_voucher'];
                $category_performance[$cat]['profit_with'] += $impact['profit_with_voucher'];
                $category_performance[$cat]['profit_without'] += $impact['profit_without_voucher'];
            }
            
            // Calculate performance score
            foreach ($category_performance as &$perf) {
                $perf['growth'] = $perf['units_without'] > 0 ? (($perf['units_with'] - $perf['units_without']) / $perf['units_without']) * 100 : 0;
            }
            
            // Sort by growth
            uasort($category_performance, function($a, $b) {
                return $b['growth'] - $a['growth'];
            });
            ?>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Units (With)</th>
                        <th>Units (Without)</th>
                        <th>Growth</th>
                        <th>Revenue (With)</th>
                        <th>Revenue (Without)</th>
                        <th>Performance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($category_performance as $cat => $perf): ?>
                        <?php
                        $heat_color = $perf['growth'] > 20 ? '#28a745' : ($perf['growth'] > 0 ? '#ffc107' : ($perf['growth'] > -10 ? '#6c757d' : '#dc3545'));
                        ?>
                        <tr>
                            <td style="font-weight: 600; color: var(--primary-white);"><?php echo htmlspecialchars($cat); ?></td>
                            <td><?php echo $perf['units_with']; ?></td>
                            <td><?php echo $perf['units_without']; ?></td>
                            <td style="color: <?php echo $heat_color; ?>; font-weight: 600;">
                                <?php echo number_format($perf['growth'], 1); ?>%
                            </td>
                            <td><?php echo formatCurrency($perf['revenue_with']); ?></td>
                            <td><?php echo formatCurrency($perf['revenue_without']); ?></td>
                            <td>
                                <?php if ($perf['growth'] > 20): ?>
                                    <span class="badge badge-success">🔥 Excellent</span>
                                <?php elseif ($perf['growth'] > 0): ?>
                                    <span class="badge badge-warning">⭐ Good</span>
                                <?php elseif ($perf['growth'] > -10): ?>
                                    <span class="badge badge-secondary">→ Neutral</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">⚠️ Poor</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Suggestions -->
        <div style="background: var(--accent-gray); padding: 20px; border-radius: 8px; margin-top: 30px;">
            <h3 style="color: var(--primary-white); margin-bottom: 15px;">💡 Recommendations</h3>
            <ul style="color: var(--text-gray); padding-left: 20px;">
                <?php
                $top_products = array_slice($product_impact, 0, 3);
                $negative_products = array_filter($product_impact, function($p) { return ($p['profit_with_voucher'] - $p['profit_without_voucher']) < 0; });
                ?>
                <?php if (!empty($top_products)): ?>
                    <li style="margin-bottom: 10px;">
                        <strong style="color: var(--primary-white);">Products to Promote Next:</strong>
                        <?php echo implode(', ', array_map(function($p) { return htmlspecialchars($p['product_name']); }, $top_products)); ?>
                    </li>
                <?php endif; ?>
                <?php if (!empty($negative_products)): ?>
                    <li style="margin-bottom: 10px;">
                        <strong style="color: #dc3545;">⚠️ Voucher causes negative profit on:</strong>
                        <?php echo implode(', ', array_map(function($p) { return htmlspecialchars($p['product_name']); }, $negative_products)); ?>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        // Top products chart
        const productData = <?php echo json_encode(array_slice($product_impact, 0, 10)); ?>;
        const productCtx = document.getElementById('productChart').getContext('2d');
        new Chart(productCtx, {
            type: 'bar',
            data: {
                labels: productData.map(p => p.product_name),
                datasets: [
                    {
                        label: 'Units Sold (With Voucher)',
                        data: productData.map(p => p.units_sold_with_voucher),
                        backgroundColor: 'rgba(40, 167, 69, 0.5)',
                        borderColor: '#28a745',
                        borderWidth: 2
                    },
                    {
                        label: 'Units Sold (Without Voucher)',
                        data: productData.map(p => p.units_sold_without_voucher),
                        backgroundColor: 'rgba(255, 255, 255, 0.3)',
                        borderColor: '#FFFFFF',
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: '#FFFFFF' } }
                },
                scales: {
                    x: { ticks: { color: '#FFFFFF', maxRotation: 45 }, grid: { color: 'rgba(255,255,255,0.1)' } },
                    y: { ticks: { color: '#FFFFFF' }, grid: { color: 'rgba(255,255,255,0.1)' } }
                }
            }
        });
        </script>
    <?php else: ?>
        <div style="padding: 40px; text-align: center; color: var(--text-gray);">
            <p>No product impact data available for the selected voucher and period.</p>
            <p style="margin-top: 10px;">Please select a voucher and ensure there are transactions in the selected date range.</p>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div style="padding: 40px; text-align: center; color: var(--text-gray);">
        <p>Please select a voucher to view detailed analytics.</p>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

