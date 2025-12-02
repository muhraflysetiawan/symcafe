<?php
require_once 'config/config.php';
requireLogin();
requireCafeSetup();
requireRole(['owner']);

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

$message = '';
$message_type = '';

// Handle delete
if (isset($_GET['delete'])) {
    $variation_id = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM product_variations WHERE variation_id = ? AND cafe_id = ?");
        if ($stmt->execute([$variation_id, $cafe_id])) {
            $message = 'Variation deleted successfully';
            $message_type = 'success';
        } else {
            $message = 'Failed to delete variation';
            $message_type = 'error';
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get all variations with their options
try {
    $stmt = $conn->prepare("
        SELECT v.*, 
               COUNT(DISTINCT o.option_id) as option_count,
               COUNT(DISTINCT pva.item_id) as product_count
        FROM product_variations v
        LEFT JOIN variation_options o ON v.variation_id = o.variation_id
        LEFT JOIN product_variation_assignments pva ON v.variation_id = pva.variation_id
        WHERE v.cafe_id = ?
        GROUP BY v.variation_id
        ORDER BY v.display_order, v.variation_name
    ");
    $stmt->execute([$cafe_id]);
    $variations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $variations = [];
    if (empty($message)) {
        $message = 'Variations table not found. Please run the migration script first.';
        $message_type = 'error';
    }
}

$page_title = 'Product Variations';
include 'includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">Product Variations Management</h2>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type == 'error' ? 'error' : 'success'; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="table-container">
    <div class="table-header">
        <div class="table-title">Variation Types</div>
        <a href="forms/variation_form.php" class="btn btn-primary btn-sm">Add New Variation</a>
    </div>
    
    <?php if (empty($variations)): ?>
        <div style="padding: 20px; text-align: center; color: var(--text-gray);">
            No variations found. <a href="forms/variation_form.php" style="color: var(--primary-white);">Create your first variation</a>
        </div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Variation Name</th>
                    <th>Type</th>
                    <th>Required</th>
                    <th>Options</th>
                    <th>Used in Products</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($variations as $variation): ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--primary-white);"><?php echo htmlspecialchars($variation['variation_name']); ?></td>
                        <td>
                            <span class="badge badge-info"><?php echo ucfirst($variation['variation_type']); ?></span>
                        </td>
                        <td>
                            <?php if ($variation['is_required']): ?>
                                <span class="badge badge-warning">Required</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Optional</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $variation['option_count']; ?> options</td>
                        <td><?php echo $variation['product_count']; ?> products</td>
                        <td>
                            <a href="forms/variation_form.php?id=<?php echo $variation['variation_id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                            <a href="variation_options.php?variation_id=<?php echo $variation['variation_id']; ?>" class="btn btn-secondary btn-sm">Manage Options</a>
                            <a href="?delete=<?php echo $variation['variation_id']; ?>" 
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Are you sure you want to delete this variation? This will also delete all its options.')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

