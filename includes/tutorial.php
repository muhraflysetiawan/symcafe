<?php
// Tutorial steps for Owner and Cashier roles
function getTutorialSteps($role) {
    if ($role == 'owner') {
        return [
            [
                'title' => 'Welcome to ' . APP_NAME . '!',
                'icon' => '🎉',
                'description' => 'Learn how to use all the features of your café management system.',
                'steps' => [
                    ['icon' => '📊', 'title' => 'Dashboard', 'description' => 'View your business overview, today\'s orders, revenue, and favorite products. Monitor your café performance at a glance.', 'link' => 'dashboard.php'],
                    ['icon' => '☕', 'title' => 'Products Management', 'description' => 'Add, edit, and manage your menu items. Set prices, stock levels, categories, and upload product images.', 'link' => 'products.php'],
                    ['icon' => '📁', 'title' => 'Categories', 'description' => 'Organize your products into categories like Beverages, Food, Desserts, etc. Makes menu navigation easier.', 'link' => 'categories.php'],
                    ['icon' => '💳', 'title' => 'POS / Transactions', 'description' => 'Process customer transactions quickly. Add items, apply vouchers, choose payment methods, and print receipts.', 'link' => 'pos.php'],
                    ['icon' => '📋', 'title' => 'Customer Orders', 'description' => 'View and manage online orders from customers. Update order status and prepare items for pickup/delivery.', 'link' => 'cashier_orders.php'],
                    ['icon' => '📜', 'title' => 'Transaction History', 'description' => 'View all past transactions with detailed information. Filter by date, payment method, and search for specific orders.', 'link' => 'transactions.php'],
                    ['icon' => '👥', 'title' => 'Manage Cashiers', 'description' => 'Add cashier accounts for your staff. Cashiers can process transactions but cannot modify products or settings.', 'link' => 'cashiers.php'],
                    ['icon' => '💳', 'title' => 'Payment Categories', 'description' => 'Set up different payment methods like Cash, QRIS, Debit, Credit Card. Customize payment options for your café.', 'link' => 'payment_categories.php'],
                    ['icon' => '🎫', 'title' => 'Vouchers & Analytics', 'description' => 'Create discount vouchers to attract customers. Track voucher usage and analyze promotion effectiveness.', 'link' => 'voucher_analytics.php'],
                    ['icon' => '🎨', 'title' => 'Theme Settings', 'description' => 'Customize your café\'s branding. Upload logo, set primary/secondary colors, and personalize the system appearance.', 'link' => 'theme_settings.php'],
                    ['icon' => '👤', 'title' => 'Profile', 'description' => 'Manage your account information. Update your name, email, password, and café details.', 'link' => 'profile.php'],
                ]
            ],
            [
                'title' => 'Quick Start Guide',
                'icon' => '🚀',
                'description' => 'Get your café up and running in 5 steps.',
                'steps' => [
                    ['icon' => '1️⃣', 'title' => 'Complete Café Setup', 'description' => 'Add your café name, address, phone number, and upload your logo in Profile settings.'],
                    ['icon' => '2️⃣', 'title' => 'Create Categories', 'description' => 'Organize your menu by creating categories like "Coffee", "Food", "Desserts" in the Categories section.'],
                    ['icon' => '3️⃣', 'title' => 'Add Products', 'description' => 'Start adding menu items with prices, stock levels, and images in the Products section.'],
                    ['icon' => '4️⃣', 'title' => 'Set Payment Methods', 'description' => 'Configure payment options your café accepts (Cash, QRIS, Debit, etc.) in Payment Categories.'],
                    ['icon' => '5️⃣', 'title' => 'Start Selling', 'description' => 'Use the POS system to process transactions. Add cashiers if you have staff members.'],
                ]
            ],
            [
                'title' => 'Advanced Features',
                'icon' => '⚡',
                'description' => 'Unlock the power of advanced features.',
                'steps' => [
                    ['icon' => '🎫', 'title' => 'Create Vouchers', 'description' => 'Create promotional vouchers with discount amounts, minimum orders, and validity periods to attract customers.'],
                    ['icon' => '📊', 'title' => 'View Analytics', 'description' => 'Track sales performance, popular products, and revenue trends in the Dashboard and Sales Reports.'],
                    ['icon' => '👥', 'title' => 'Manage Team', 'description' => 'Add cashier accounts with login credentials. Control access levels for your staff members.'],
                    ['icon' => '🎨', 'title' => 'Brand Your System', 'description' => 'Upload your café logo and customize colors in Theme Settings to match your brand identity.'],
                ]
            ]
        ];
    } else if ($role == 'cashier') {
        return [
            [
                'title' => 'Welcome, Cashier!',
                'icon' => '👋',
                'description' => 'Learn how to use the cashier features effectively.',
                'steps' => [
                    ['icon' => '📊', 'title' => 'Dashboard', 'description' => 'View today\'s orders, revenue, and quick statistics about your café\'s performance.', 'link' => 'dashboard.php'],
                    ['icon' => '💳', 'title' => 'POS / Transactions', 'description' => 'Process customer transactions. Add items to cart, apply vouchers, select payment method, and complete the sale.', 'link' => 'pos.php'],
                    ['icon' => '📋', 'title' => 'Customer Orders', 'description' => 'View online orders from customers. Update order status when items are ready for pickup or delivery.', 'link' => 'cashier_orders.php'],
                    ['icon' => '📜', 'title' => 'Transaction History', 'description' => 'View all past transactions. Search for specific orders and view detailed receipt information.', 'link' => 'transactions.php'],
                    ['icon' => '👤', 'title' => 'Profile', 'description' => 'Update your account information and change your password.', 'link' => 'profile.php'],
                ]
            ],
            [
                'title' => 'Processing Transactions',
                'icon' => '💳',
                'description' => 'Step-by-step guide to process a sale.',
                'steps' => [
                    ['icon' => '1️⃣', 'title' => 'Open POS', 'description' => 'Go to POS / Transactions from the sidebar. The POS interface will load with product categories.'],
                    ['icon' => '2️⃣', 'title' => 'Select Products', 'description' => 'Click on product categories to view items. Click products to add them to the cart. Adjust quantities as needed.'],
                    ['icon' => '3️⃣', 'title' => 'Apply Voucher (Optional)', 'description' => 'If customer has a voucher code, enter it in the voucher field to apply discount.'],
                    ['icon' => '4️⃣', 'title' => 'Choose Payment Method', 'description' => 'Select the payment method (Cash, QRIS, Debit, Credit) and enter the amount if paying with cash.'],
                    ['icon' => '5️⃣', 'title' => 'Complete Transaction', 'description' => 'Click "Process Transaction" to complete the sale. Print or view the receipt.'],
                ]
            ],
            [
                'title' => 'Managing Orders',
                'icon' => '📋',
                'description' => 'How to handle customer orders.',
                'steps' => [
                    ['icon' => '👀', 'title' => 'View Orders', 'description' => 'Go to Customer Orders to see all online orders. Orders are sorted by status (Pending, Processing, Ready, Completed).'],
                    ['icon' => '✅', 'title' => 'Update Status', 'description' => 'Click on an order to view details. Update the status as you prepare the items (Processing → Ready → Completed).'],
                    ['icon' => '💰', 'title' => 'Mark as Paid', 'description' => 'For cash payments, mark orders as paid when customer pays at pickup. Online payments are automatically marked as paid.'],
                ]
            ]
        ];
    }
    
    return [];
}
?>

