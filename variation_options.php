<?php
require_once 'config/config.php';
requireLogin();
requireCafeSetup();
requireRole(['owner']);

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

$variation_id = isset($_GET['variation_id']) ? (int)$_GET['variation_id'] : 0;

if (!$variation_id) {
    header('Location: variations.php');
    exit();
}

// Get variation info
$stmt = $conn->prepare("SELECT * FROM product_variations WHERE variation_id = ? AND cafe_id = ?");
$stmt->execute([$variation_id, $cafe_id]);
$variation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$variation) {
    header('Location: variations.php');
    exit();
}

$message = '';
$message_type = '';

// Handle delete
if (isset($_GET['delete'])) {
    $option_id = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM variation_options WHERE option_id = ? AND variation_id = ?");
        if ($stmt->execute([$option_id, $variation_id])) {
            $message = 'Option deleted successfully';
            $message_type = 'success';
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get all options for this variation
$stmt = $conn->prepare("SELECT * FROM variation_options WHERE variation_id = ? ORDER BY display_order, option_name");
$stmt->execute([$variation_id]);
$options = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Variation Options';
include 'includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">
    Options for: <?php echo htmlspecialchars($variation['variation_name']); ?>
    <a href="variations.php" class="btn btn-secondary btn-sm" style="margin-left: 15px;">← Back to Variations</a>
</h2>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type == 'error' ? 'error' : 'success'; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="table-container">
    <div class="table-header">
        <div class="table-title">Variation Options</div>
        <a href="forms/variation_option_form.php?variation_id=<?php echo $variation_id; ?>" class="btn btn-primary btn-sm">Add New Option</a>
    </div>
    
    <?php if (empty($options)): ?>
        <div style="padding: 20px; text-align: center; color: var(--text-gray);">
            No options found. <a href="forms/variation_option_form.php?variation_id=<?php echo $variation_id; ?>" style="color: var(--primary-white);">Add your first option</a>
        </div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Option Name</th>
                    <th>Price Adjustment</th>
                    <th>Default</th>
                    <th>Display Order</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($options as $option): ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--primary-white);"><?php echo htmlspecialchars($option['option_name']); ?></td>
                        <td>
                            <?php if ($option['price_adjustment'] > 0): ?>
                                <span style="color: #28a745;">+<?php echo formatCurrency($option['price_adjustment']); ?></span>
                            <?php elseif ($option['price_adjustment'] < 0): ?>
                                <span style="color: #dc3545;"><?php echo formatCurrency($option['price_adjustment']); ?></span>
                            <?php else: ?>
                                <span style="color: var(--text-gray);">No change</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($option['is_default']): ?>
                                <span class="badge badge-success">Default</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $option['display_order']; ?></td>
                        <td>
                            <a href="forms/variation_option_form.php?id=<?php echo $option['option_id']; ?>&variation_id=<?php echo $variation_id; ?>" class="btn btn-primary btn-sm">Edit</a>
                            <a href="?delete=<?php echo $option['option_id']; ?>&variation_id=<?php echo $variation_id; ?>" 
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Are you sure you want to delete this option?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

