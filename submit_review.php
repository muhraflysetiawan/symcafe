<?php
require_once 'config/config.php';
requireLogin();

// Only customers can submit reviews
if ($_SESSION['user_role'] != 'customer') {
    header('Location: dashboard.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$customer_id = $_SESSION['user_id'];

$error = '';
$success = '';

// Get item_id and order_id from URL
$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$item_id) {
    header('Location: customer_orders.php');
    exit();
}

// Verify that the customer has ordered this item
$stmt = $conn->prepare("
    SELECT 
        mi.item_id,
        mi.item_name,
        mi.image,
        c.cafe_name,
        o.order_id,
        o.created_at as order_date
    FROM menu_items mi
    JOIN cafes c ON mi.cafe_id = c.cafe_id
    JOIN order_items oi ON mi.item_id = oi.item_id
    JOIN orders o ON oi.order_id = o.order_id
    WHERE mi.item_id = ? 
        AND o.customer_id = ?
        " . ($order_id > 0 ? "AND o.order_id = ?" : "") . "
    LIMIT 1
");

if ($order_id > 0) {
    $stmt->execute([$item_id, $customer_id, $order_id]);
} else {
    $stmt->execute([$item_id, $customer_id]);
}

$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    $_SESSION['error'] = 'You can only review products you have ordered';
    header('Location: customer_orders.php');
    exit();
}

// Check if review already exists
$stmt = $conn->prepare("
    SELECT review_id, rating, comment 
    FROM product_reviews 
    WHERE item_id = ? AND customer_id = ?" . ($order_id > 0 ? " AND order_id = ?" : "") . "
");
if ($order_id > 0) {
    $stmt->execute([$item_id, $customer_id, $order_id]);
} else {
    $stmt->execute([$item_id, $customer_id]);
}
$existing_review = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comment = sanitizeInput($_POST['comment'] ?? '');
    $review_order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    
    if ($rating < 1 || $rating > 5) {
        $error = 'Please select a valid rating (1-5 stars)';
    } else {
        try {
            if ($existing_review) {
                // Update existing review
                $stmt = $conn->prepare("
                    UPDATE product_reviews 
                    SET rating = ?, comment = ?, updated_at = NOW()
                    WHERE review_id = ?
                ");
                $stmt->execute([$rating, $comment, $existing_review['review_id']]);
                $success = 'Review updated successfully!';
            } else {
                // Create new review
                $stmt = $conn->prepare("
                    INSERT INTO product_reviews (item_id, customer_id, order_id, rating, comment)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$item_id, $customer_id, $review_order_id ?: null, $rating, $comment]);
                $success = 'Review submitted successfully!';
            }
            
            // Redirect after 2 seconds
            header('Refresh: 2; url=customer_orders.php');
        } catch (Exception $e) {
            $error = 'Failed to submit review: ' . $e->getMessage();
        }
    }
}

$page_title = 'Submit Review';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .review-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
        }
        .product-info {
            background: var(--accent-gray);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .product-info img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
        }
        .star-rating {
            display: flex;
            gap: 10px;
            margin: 20px 0;
            font-size: 40px;
            cursor: pointer;
        }
        .star-rating .star {
            color: var(--text-gray);
            transition: color 0.2s;
        }
        .star-rating .star:hover,
        .star-rating .star.active {
            color: #ffc107;
        }
    </style>
</head>
<body>
    <nav style="background: var(--primary-black); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-gray);">
        <h1 style="color: var(--primary-white); margin: 0; font-size: 24px;"><?php echo APP_NAME; ?></h1>
        <div style="display: flex; gap: 15px; align-items: center;">
            <a href="customer_menu.php" style="color: var(--primary-white); text-decoration: none;">Menu</a>
            <a href="customer_orders.php" style="color: var(--primary-white); text-decoration: none;">My Orders</a>
            <span style="color: var(--text-gray);"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            <a href="logout.php" style="color: var(--text-gray); text-decoration: none;">Logout</a>
        </div>
    </nav>

    <div class="review-container">
        <h2 style="color: var(--primary-white); margin-bottom: 20px;">
            <?php echo $existing_review ? 'Update Review' : 'Submit Review'; ?>
        </h2>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="product-info">
            <?php if ($product['image'] && file_exists($product['image'])): ?>
                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['item_name']); ?>">
            <?php else: ?>
                <div style="width: 100px; height: 100px; background: var(--primary-black); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 40px;">☕</div>
            <?php endif; ?>
            <div>
                <h3 style="color: var(--primary-white); margin: 0 0 5px 0;"><?php echo htmlspecialchars($product['item_name']); ?></h3>
                <p style="color: var(--text-gray); margin: 5px 0;"><?php echo htmlspecialchars($product['cafe_name']); ?></p>
                <?php if ($product['order_date']): ?>
                    <p style="color: var(--text-gray); margin: 5px 0; font-size: 14px;">
                        Ordered: <?php echo date('d M Y', strtotime($product['order_date'])); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <form method="POST" class="form-container">
            <div class="form-group">
                <label>Rating *</label>
                <div class="star-rating" id="starRating">
                    <span class="star" data-rating="1">★</span>
                    <span class="star" data-rating="2">★</span>
                    <span class="star" data-rating="3">★</span>
                    <span class="star" data-rating="4">★</span>
                    <span class="star" data-rating="5">★</span>
                </div>
                <input type="hidden" name="rating" id="ratingInput" value="<?php echo $existing_review['rating'] ?? 0; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="comment">Comment (Optional)</label>
                <textarea id="comment" name="comment" rows="6" placeholder="Share your experience with this product..."><?php echo htmlspecialchars($existing_review['comment'] ?? ''); ?></textarea>
            </div>
            
            <input type="hidden" name="order_id" value="<?php echo $order_id ?: $product['order_id']; ?>">
            
            <button type="submit" class="btn btn-primary btn-block">
                <?php echo $existing_review ? 'Update Review' : 'Submit Review'; ?>
            </button>
            <a href="customer_orders.php" class="btn btn-secondary btn-block" style="margin-top: 10px; text-align: center; display: block;">Cancel</a>
        </form>
    </div>
    
    <script>
        const stars = document.querySelectorAll('.star-rating .star');
        const ratingInput = document.getElementById('ratingInput');
        const currentRating = <?php echo $existing_review['rating'] ?? 0; ?>;
        
        // Initialize stars based on existing rating
        if (currentRating > 0) {
            for (let i = 0; i < currentRating; i++) {
                stars[i].classList.add('active');
            }
        }
        
        stars.forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.dataset.rating);
                ratingInput.value = rating;
                
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
            });
            
            star.addEventListener('mouseenter', function() {
                const rating = parseInt(this.dataset.rating);
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.style.color = '#ffc107';
                    } else {
                        s.style.color = '';
                    }
                });
            });
        });
        
        document.querySelector('.star-rating').addEventListener('mouseleave', function() {
            const current = parseInt(ratingInput.value) || 0;
            stars.forEach((s, index) => {
                if (index < current) {
                    s.style.color = '';
                }
            });
        });
    </script>
</body>
</html>

