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
if (isset($_GET['delete']) && $_GET['delete']) {
    $user_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND cafe_id = ? AND role = 'cashier'");
    if ($stmt->execute([$user_id, $cafe_id])) {
        $message = 'Cashier deleted successfully';
        $message_type = 'success';
    } else {
        $message = 'Failed to delete cashier';
        $message_type = 'error';
    }
}

// Get all cashiers
$stmt = $conn->prepare("SELECT * FROM users WHERE cafe_id = ? AND role = 'cashier' ORDER BY created_at DESC");
$stmt->execute([$cafe_id]);
$cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Cashier Management';
include 'includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">Cashier Management</h2>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<div class="table-container">
    <div class="table-header">
        <div class="table-title">Cashier Accounts</div>
        <a href="forms/cashier_form.php" class="btn btn-primary btn-sm">+ Add Cashier</a>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Username</th>
                <th>Email</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($cashiers)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: var(--text-gray);">No cashiers found. <a href="forms/cashier_form.php" style="color: var(--primary-white);">Add your first cashier</a></td>
                </tr>
            <?php else: ?>
                <?php foreach ($cashiers as $cashier): ?>
                    <tr>
                        <td style="color: var(--primary-white); font-weight: 500;"><?php echo htmlspecialchars($cashier['name']); ?></td>
                        <td><?php echo htmlspecialchars($cashier['username']); ?></td>
                        <td><?php echo htmlspecialchars($cashier['email']); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($cashier['created_at'])); ?></td>
                        <td>
                            <a href="forms/cashier_form.php?id=<?php echo $cashier['user_id']; ?>" class="btn btn-secondary btn-sm">Edit</a>
                            <a href="?delete=<?php echo $cashier['user_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this cashier?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>

