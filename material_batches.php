<?php
require_once 'config/config.php';
requireLogin();
requireCafeSetup();
requireRole(['owner']);

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

$material_id = isset($_GET['material_id']) ? (int)$_GET['material_id'] : 0;

if (!$material_id) {
    header('Location: raw_materials.php');
    exit();
}

// Get material info
$stmt = $conn->prepare("SELECT * FROM raw_materials WHERE material_id = ? AND cafe_id = ?");
$stmt->execute([$material_id, $cafe_id]);
$material = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$material) {
    header('Location: raw_materials.php');
    exit();
}

$message = '';
$message_type = '';

// Handle delete
if (isset($_GET['delete'])) {
    $batch_id = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM material_batches WHERE batch_id = ? AND material_id = ?");
        if ($stmt->execute([$batch_id, $material_id])) {
            $message = 'Batch deleted successfully';
            $message_type = 'success';
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get all batches for this material (FEFO order - First Expire First Out)
$stmt = $conn->prepare("
    SELECT b.*, 
           CASE 
               WHEN b.expiration_date IS NULL THEN 1
               WHEN b.expiration_date < CURDATE() THEN 0
               WHEN b.expiration_date = CURDATE() THEN 2
               ELSE 3
           END as expiry_priority
    FROM material_batches b
    WHERE b.material_id = ?
    ORDER BY 
        expiry_priority ASC,
        b.expiration_date ASC,
        b.received_date ASC
");
$stmt->execute([$material_id]);
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total available stock
$total_stock = 0;
foreach ($batches as $batch) {
    if (!$batch['is_used']) {
        $total_stock += $batch['quantity'];
    }
}

$page_title = 'Material Batches';
include 'includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">
    Batches for: <?php echo htmlspecialchars($material['material_name']); ?>
    <a href="raw_materials.php" class="btn btn-secondary btn-sm" style="margin-left: 15px;">← Back to Materials</a>
</h2>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type == 'error' ? 'error' : 'success'; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div style="background: var(--accent-gray); padding: 15px; border-radius: 5px; margin-bottom: 20px;">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <div>
            <div style="color: var(--text-gray); font-size: 12px;">Total Available Stock</div>
            <div style="color: var(--primary-white); font-size: 24px; font-weight: bold;"><?php echo number_format($total_stock, 2); ?> <?php echo $material['unit_type']; ?></div>
        </div>
        <div>
            <div style="color: var(--text-gray); font-size: 12px;">Current Cost</div>
            <div style="color: var(--primary-white); font-size: 20px; font-weight: bold;"><?php echo formatCurrency($material['current_cost']); ?> / <?php echo $material['unit_type']; ?></div>
        </div>
        <div>
            <div style="color: var(--text-gray); font-size: 12px;">Minimum Level</div>
            <div style="color: <?php echo $total_stock <= $material['min_stock_level'] ? '#dc3545' : 'var(--primary-white)'; ?>; font-size: 20px; font-weight: bold;">
                <?php echo number_format($material['min_stock_level'], 2); ?> <?php echo $material['unit_type']; ?>
            </div>
        </div>
    </div>
</div>

<div class="table-container">
    <div class="table-header">
        <div class="table-title">Material Batches (FEFO Order)</div>
        <a href="forms/batch_form.php?material_id=<?php echo $material_id; ?>" class="btn btn-primary btn-sm">Add New Batch</a>
    </div>
    
    <?php if (empty($batches)): ?>
        <div style="padding: 20px; text-align: center; color: var(--text-gray);">
            No batches found. <a href="forms/batch_form.php?material_id=<?php echo $material_id; ?>" style="color: var(--primary-white);">Add your first batch</a>
        </div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Batch Number</th>
                    <th>Quantity</th>
                    <th>Cost per Unit</th>
                    <th>Received Date</th>
                    <th>Expiration Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($batches as $batch): ?>
                    <?php
                    $expiry_status = '';
                    $row_class = '';
                    if ($batch['expiration_date']) {
                        $days_until_expiry = (strtotime($batch['expiration_date']) - time()) / (60 * 60 * 24);
                        if ($days_until_expiry < 0) {
                            $expiry_status = '<span class="badge badge-danger">Expired</span>';
                            $row_class = 'style="background: rgba(220, 53, 69, 0.1);"';
                        } elseif ($days_until_expiry <= 7) {
                            $expiry_status = '<span class="badge badge-warning">Expires in ' . ceil($days_until_expiry) . ' days</span>';
                            $row_class = 'style="background: rgba(255, 193, 7, 0.1);"';
                        } else {
                            $expiry_status = '<span class="badge badge-success">Valid</span>';
                        }
                    } else {
                        $expiry_status = '<span class="badge badge-secondary">No expiry</span>';
                    }
                    ?>
                    <tr <?php echo $row_class; ?>>
                        <td style="font-weight: 600; color: var(--primary-white);"><?php echo htmlspecialchars($batch['batch_number']); ?></td>
                        <td><?php echo number_format($batch['quantity'], 2); ?> <?php echo $material['unit_type']; ?></td>
                        <td><?php echo formatCurrency($batch['cost_per_unit']); ?></td>
                        <td><?php echo date('d M Y', strtotime($batch['received_date'])); ?></td>
                        <td>
                            <?php if ($batch['expiration_date']): ?>
                                <?php echo date('d M Y', strtotime($batch['expiration_date'])); ?>
                            <?php else: ?>
                                <span style="color: var(--text-gray);">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($batch['is_used']): ?>
                                <span class="badge badge-secondary">Used</span>
                            <?php else: ?>
                                <?php echo $expiry_status; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$batch['is_used']): ?>
                                <a href="forms/batch_form.php?id=<?php echo $batch['batch_id']; ?>&material_id=<?php echo $material_id; ?>" class="btn btn-primary btn-sm">Edit</a>
                            <?php endif; ?>
                            <a href="?delete=<?php echo $batch['batch_id']; ?>&material_id=<?php echo $material_id; ?>" 
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Are you sure you want to delete this batch?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

