<?php
require_once 'config/config.php';
requireLogin();
requireCafeSetup();
requireRole(['owner']);

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

$voucher_id = isset($_GET['voucher_id']) ? (int)$_GET['voucher_id'] : 0;

if ($voucher_id <= 0) {
    header('Location: vouchers.php');
    exit();
}

// Get voucher details
try {
    $stmt = $conn->prepare("SELECT * FROM vouchers WHERE voucher_id = ? AND cafe_id = ?");
    $stmt->execute([$voucher_id, $cafe_id]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$voucher) {
        header('Location: vouchers.php');
        exit();
    }
} catch (Exception $e) {
    header('Location: vouchers.php');
    exit();
}

// Get all unique codes for this voucher
try {
    $stmt = $conn->prepare("SELECT * FROM voucher_codes WHERE voucher_id = ? ORDER BY created_at DESC, is_used ASC");
    $stmt->execute([$voucher_id]);
    $codes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $codes = [];
}

$page_title = 'Voucher Codes';
include 'includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">
    Voucher Codes: <?php echo htmlspecialchars($voucher['voucher_code']); ?>
    <a href="vouchers.php" class="btn btn-secondary btn-sm" style="margin-left: 10px;">Back to Vouchers</a>
</h2>

<div style="background: var(--accent-gray); padding: 15px; border-radius: 5px; margin-bottom: 20px;">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <div>
            <span style="color: var(--text-gray); font-size: 12px;">Discount:</span>
            <div style="color: var(--primary-white); font-weight: bold;"><?php echo formatCurrency($voucher['discount_amount']); ?></div>
        </div>
        <div>
            <span style="color: var(--text-gray); font-size: 12px;">Voucher Code:</span>
            <div style="color: var(--primary-white); font-weight: bold; font-size: 14px;"><?php echo htmlspecialchars($voucher['voucher_code']); ?></div>
        </div>
        <div>
            <span style="color: var(--text-gray); font-size: 12px;">Total QR Codes:</span>
            <div style="color: var(--primary-white); font-weight: bold;"><?php echo count($codes); ?></div>
        </div>
        <div>
            <span style="color: var(--text-gray); font-size: 12px;">Used:</span>
            <div style="color: var(--primary-white); font-weight: bold;">
                <?php echo count(array_filter($codes, function($c) { return $c['is_used']; })); ?>
            </div>
        </div>
        <div>
            <span style="color: var(--text-gray); font-size: 12px;">Available:</span>
            <div style="color: var(--primary-white); font-weight: bold;">
                <?php echo count(array_filter($codes, function($c) { return !$c['is_used']; })); ?>
            </div>
        </div>
    </div>
</div>

<div style="display: flex; gap: 10px; margin-bottom: 20px;">
    <button type="button" class="btn btn-primary" onclick="generateCodes()">Generate New Codes</button>
    <input type="number" id="codeQuantity" value="5" min="1" max="100" 
           style="width: 100px; padding: 8px; background: var(--accent-gray); border: 1px solid var(--border-gray); border-radius: 5px; color: var(--primary-white);">
    <span style="color: var(--text-gray); line-height: 40px;">codes</span>
</div>

<div id="messageContainer"></div>

<div class="table-container">
    <div class="table-header">
        <div class="table-title">Unique Voucher Codes</div>
    </div>
    
    <?php if (empty($codes)): ?>
        <div style="padding: 20px; text-align: center; color: var(--text-gray);">
            No codes generated yet. Click "Generate New Codes" to create unique codes with QR codes.
        </div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>QR Code ID</th>
                    <th>Voucher Code</th>
                    <th>QR Code</th>
                    <th>Status</th>
                    <th>Used At</th>
                    <th>Order ID</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($codes as $code): ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--primary-white);">
                            <code style="background: var(--primary-black); padding: 4px 8px; border-radius: 4px; color: var(--primary-white);">
                                #<?php echo $code['code_id']; ?>
                            </code>
                        </td>
                        <td style="font-weight: 600; color: var(--primary-white);">
                            <code style="background: var(--primary-black); padding: 4px 8px; border-radius: 4px; color: var(--primary-white);">
                                <?php echo htmlspecialchars($code['unique_code']); ?>
                            </code>
                        </td>
                        <td>
                            <?php if ($code['unique_code']): ?>
                                <img src="api/generate_qr_code.php?data=<?php echo urlencode($code['unique_code']); ?>&size=80" 
                                     alt="QR Code" 
                                     style="width: 80px; height: 80px; border: 1px solid var(--border-gray); border-radius: 4px; background: #fff; padding: 5px;"
                                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'80\' height=\'80\'%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' fill=\'%23dc3545\' font-size=\'10\'%3EError%3C/text%3E%3C/svg%3E'">
                            <?php else: ?>
                                <div style="width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; background: #fff; border: 1px solid var(--border-gray); border-radius: 4px; color: #dc3545; font-size: 10px;">No Code</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($code['is_used']): ?>
                                <span class="badge badge-danger">Used</span>
                            <?php else: ?>
                                <span class="badge badge-success">Available</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 12px; color: var(--text-gray);">
                            <?php echo $code['used_at'] ? date('d M Y H:i', strtotime($code['used_at'])) : '-'; ?>
                        </td>
                        <td style="font-size: 12px; color: var(--text-gray);">
                            <?php echo $code['used_by_order_id'] ? '#' . $code['used_by_order_id'] : '-'; ?>
                        </td>
                        <td style="font-size: 12px; color: var(--text-gray);">
                            <?php echo date('d M Y H:i', strtotime($code['created_at'])); ?>
                        </td>
                        <td>
                            <?php if ($code['unique_code']): ?>
                                <a href="api/generate_qr_code.php?data=<?php echo urlencode($code['unique_code']); ?>&size=400" 
                                   download="<?php echo htmlspecialchars($code['unique_code']); ?>.png"
                                   class="btn btn-primary btn-sm">
                                    Download QR
                                </a>
                            <?php else: ?>
                                <span class="btn btn-secondary btn-sm" style="opacity: 0.5;">No Code</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
const voucherId = <?php echo $voucher_id; ?>;
const codes = <?php echo json_encode($codes); ?>;

function generateCodes() {
    const quantity = parseInt(document.getElementById('codeQuantity').value) || 5;
    
    if (quantity < 1 || quantity > 100) {
        showMessage('Please enter a quantity between 1 and 100', 'error');
        return;
    }
    
    fetch('api/generate_voucher_codes.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            voucher_id: voucherId,
            quantity: quantity
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showMessage(data.message || 'Failed to generate codes', 'error');
        }
    })
    .catch(error => {
        showMessage('Error: ' + error.message, 'error');
    });
}

// Download function is now handled by direct link in HTML

function showMessage(message, type) {
    const container = document.getElementById('messageContainer');
    const alertClass = type === 'error' ? 'alert-error' : 'alert-success';
    container.innerHTML = '<div class="alert ' + alertClass + '">' + message + '</div>';
    setTimeout(() => {
        container.innerHTML = '';
    }, 5000);
}
</script>

<?php include 'includes/footer.php'; ?>

