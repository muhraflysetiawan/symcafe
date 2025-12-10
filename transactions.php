<?php
require_once 'config/config.php';
requireLogin();
requireCafeSetup();

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$order_type_filter = $_GET['order_type'] ?? '';

// Check if order_source column exists
$columns = $conn->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
$has_order_source = in_array('order_source', $columns);

// Build query - use LEFT JOIN for cashier to include customer orders (which have NULL cashier_id)
$query = "
    SELECT o.*, u.name as cashier_name, c.name as customer_name
    FROM orders o
    LEFT JOIN users u ON o.cashier_id = u.user_id
    LEFT JOIN customers c ON o.customer_id = c.customer_id
    WHERE o.cafe_id = ? 
    AND DATE(o.created_at) BETWEEN ? AND ?
";

$params = [$cafe_id, $date_from, $date_to];

if (!empty($order_type_filter)) {
    $query .= " AND o.order_type = ?";
    $params[] = $order_type_filter;
}

$query .= " ORDER BY o.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_revenue = 0;
$total_orders = count($transactions);
foreach ($transactions as $trans) {
    if ($trans['payment_status'] == 'paid') {
        $total_revenue += $trans['total_amount'];
    }
}

$page_title = 'Transaction History';
include 'includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">Transaction History</h2>

<div class="form-container" style="margin-bottom: 20px;">
    <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: end;">
        <div class="form-group" style="flex: 1; min-width: 150px;">
            <label for="date_from">From Date</label>
            <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" required>
        </div>
        
        <div class="form-group" style="flex: 1; min-width: 150px;">
            <label for="date_to">To Date</label>
            <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" required>
        </div>
        
        <div class="form-group" style="flex: 1; min-width: 150px;">
            <label for="order_type">Order Type</label>
            <select id="order_type" name="order_type">
                <option value="">All Types</option>
                <option value="dine-in" <?php echo $order_type_filter == 'dine-in' ? 'selected' : ''; ?>>Dine In</option>
                <option value="take-away" <?php echo $order_type_filter == 'take-away' ? 'selected' : ''; ?>>Take Away</option>
                <option value="online" <?php echo $order_type_filter == 'online' ? 'selected' : ''; ?>>Online</option>
            </select>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="transactions.php" class="btn btn-secondary" style="margin-left: 10px;">Reset</a>
        </div>
    </form>
</div>

<div class="dashboard-grid" style="margin-bottom: 20px;">
    <div class="dashboard-card card-success">
        <i class="fas fa-list-alt card-bg-icon"></i>
        <div class="card-header">
            <div class="card-title">Total Orders</div>
        </div>
        <div class="card-value"><?php echo $total_orders; ?></div>
        <div class="card-subtitle">Transactions in selected period</div>
    </div>
    
    <div class="dashboard-card card-warning">
        <i class="fas fa-dollar-sign card-bg-icon"></i>
        <div class="card-header">
            <div class="card-title">Total Revenue</div>
        </div>
        <div class="card-value"><?php echo formatCurrency($total_revenue); ?></div>
        <div class="card-subtitle">Paid transactions only</div>
    </div>
</div>

<div class="table-container">
    <div class="table-header">
        <div class="table-title">Transactions</div>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Date & Time</th>
                <th>Customer</th>
                <th>Cashier</th>
                <th>Type</th>
                <th>Subtotal</th>
                <th>Discount</th>
                <th>Tax</th>
                <th>Total</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($transactions)): ?>
                <tr>
                    <td colspan="11" style="text-align: center; color: var(--text-gray);">No transactions found for the selected period.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($transactions as $trans): ?>
                    <tr>
                        <td style="color: var(--primary-white); font-weight: 500;">#<?php echo str_pad($trans['order_id'], 6, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($trans['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($trans['customer_name'] ?? 'Walk-in'); ?></td>
                        <td>
                            <?php if ($trans['cashier_name']): ?>
                                <?php echo htmlspecialchars($trans['cashier_name']); ?>
                            <?php else: ?>
                                <span style="color: var(--text-gray); font-style: italic;">Online Order</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-info">
                                <?php echo ucfirst(str_replace('-', ' ', $trans['order_type'])); ?>
                            </span>
                            <?php if ($has_order_source && isset($trans['order_source']) && $trans['order_source'] == 'customer_online'): ?>
                                <span class="badge badge-warning" style="margin-left: 5px; font-size: 10px;">Online</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo formatCurrency($trans['subtotal']); ?></td>
                        <td><?php echo formatCurrency($trans['discount']); ?></td>
                        <td><?php echo formatCurrency($trans['tax']); ?></td>
                        <td style="color: var(--primary-white); font-weight: 600;"><?php echo formatCurrency($trans['total_amount']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $trans['payment_status'] == 'paid' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($trans['payment_status']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="receipt.php?order_id=<?php echo $trans['order_id']; ?>" class="btn btn-secondary btn-sm" target="_blank">View Receipt</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>

