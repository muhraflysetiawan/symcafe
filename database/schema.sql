SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `cafes` (
  `cafe_id` int(11) NOT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `cafe_name` varchar(100) NOT NULL,
  `address` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `logo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `cafe_settings` (
  `setting_id` int(11) NOT NULL,
  `cafe_id` int(11) DEFAULT NULL,
  `primary_color` varchar(7) DEFAULT '#FFFFFF',
  `secondary_color` varchar(7) DEFAULT '#252525',
  `accent_color` varchar(7) DEFAULT '#3A3A3A',
  `gradient_end_color` varchar(7) DEFAULT '#3A3A3A',
  `tax_percentage` decimal(5,2) DEFAULT 10.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `cafe_id` int(11) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `customer_favorites` (
  `favorite_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `cafe_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `inventory_logs` (
  `log_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `change_type` enum('in','out') DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `material_batches` (
  `batch_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `batch_number` varchar(50) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `cost_per_unit` decimal(10,2) NOT NULL,
  `expiration_date` date DEFAULT NULL,
  `received_date` date NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `material_cost_history` (
  `history_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `old_cost` decimal(10,2) NOT NULL,
  `new_cost` decimal(10,2) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `changed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `material_usage_log` (
  `usage_id` int(11) NOT NULL,
  `order_item_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `quantity_used` decimal(10,2) NOT NULL,
  `cost_at_time` decimal(10,2) NOT NULL,
  `used_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `menu_categories` (
  `category_id` int(11) NOT NULL,
  `cafe_id` int(11) DEFAULT NULL,
  `category_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `menu_engineering_data` (
  `engineering_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `total_sales` int(11) DEFAULT 0,
  `total_revenue` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `contribution_margin` decimal(10,2) DEFAULT 0.00,
  `popularity_rank` int(11) DEFAULT NULL,
  `profitability_rank` int(11) DEFAULT NULL,
  `quadrant` enum('star','plowhorse','puzzle','dog') DEFAULT NULL,
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `menu_items` (
  `item_id` int(11) NOT NULL,
  `cafe_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `item_name` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `cost_per_unit` decimal(10,2) DEFAULT 0.00,
  `stock` int(11) DEFAULT 0,
  `status` enum('available','unavailable') DEFAULT 'available',
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `cafe_id` int(11) DEFAULT NULL,
  `cashier_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `order_type` enum('dine-in','take-away','online') DEFAULT 'dine-in',
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `tax` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `payment_status` enum('paid','unpaid') DEFAULT 'unpaid',
  `payment_method` varchar(50) DEFAULT NULL,
  `order_status` enum('pending','customer_cash_payment','processing','ready','completed','cancelled') DEFAULT 'pending',
  `order_source` enum('pos','customer_online') DEFAULT 'pos',
  `customer_notes` text DEFAULT NULL,
  `prepared_by` int(11) DEFAULT NULL,
  `prepared_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `item_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `order_item_addons` (
  `order_item_addon_id` int(11) NOT NULL,
  `order_item_id` int(11) NOT NULL,
  `addon_id` int(11) NOT NULL,
  `addon_name` varchar(100) NOT NULL,
  `addon_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `order_item_variations` (
  `order_item_variation_id` int(11) NOT NULL,
  `order_item_id` int(11) NOT NULL,
  `variation_id` int(11) NOT NULL,
  `option_id` int(11) NOT NULL,
  `option_name` varchar(100) NOT NULL,
  `price_adjustment` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `order_notifications` (
  `notification_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `notification_type` enum('order_placed','order_processing','order_ready','order_completed','order_cancelled') NOT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `payment_method` varchar(100) NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `amount_given` decimal(10,2) DEFAULT NULL,
  `change_amount` decimal(10,2) DEFAULT NULL,
  `paid_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `payment_categories` (
  `payment_category_id` int(11) NOT NULL,
  `cafe_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `price_change_notifications` (
  `notification_id` int(11) NOT NULL,
  `cafe_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `affected_products` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`affected_products`)),
  `notification_message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `product_addons` (
  `addon_id` int(11) NOT NULL,
  `cafe_id` int(11) NOT NULL,
  `addon_name` varchar(100) NOT NULL,
  `addon_category` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `product_addon_assignments` (
  `assignment_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `addon_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `product_analytics_cache` (
  `cache_id` int(11) NOT NULL,
  `cafe_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `period_type` enum('weekly','monthly') NOT NULL,
  `period_start` date NOT NULL,
  `units_sold` int(11) DEFAULT 0,
  `revenue` decimal(10,2) DEFAULT 0.00,
  `profit` decimal(10,2) DEFAULT 0.00,
  `profit_margin` decimal(5,2) DEFAULT 0.00,
  `trend_direction` enum('up','down','stable') DEFAULT 'stable',
  `trend_percentage` decimal(5,2) DEFAULT 0.00,
  `peak_hours` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`peak_hours`)),
  `avg_order_value` decimal(10,2) DEFAULT 0.00,
  `cached_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `product_analytics_settings` (
  `setting_id` int(11) NOT NULL,
  `cafe_id` int(11) NOT NULL,
  `low_demand_threshold` int(11) DEFAULT 10,
  `high_demand_threshold` int(11) DEFAULT 50,
  `trend_period_weeks` int(11) DEFAULT 4,
  `profit_margin_warning` decimal(5,2) DEFAULT 20.00,
  `auto_recommendations_enabled` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `product_pricing` (
  `pricing_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `ingredient_cost` decimal(10,2) DEFAULT 0.00,
  `desired_margin_percent` decimal(5,2) DEFAULT 40.00,
  `suggested_price` decimal(10,2) DEFAULT 0.00,
  `min_price` decimal(10,2) DEFAULT 0.00,
  `max_price` decimal(10,2) DEFAULT 0.00,
  `competitor_price` decimal(10,2) DEFAULT NULL,
  `psychological_price` decimal(10,2) DEFAULT NULL,
  `last_calculated_at` timestamp NULL DEFAULT NULL,
  `auto_update_enabled` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `product_recipes` (
  `recipe_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `material_id` int(11) DEFAULT NULL,
  `sub_recipe_id` int(11) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;
CREATE TABLE `product_recommendations` (
  `recommendation_id` int(11) NOT NULL,
  `cafe_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `recommendation_type` enum('discount','voucher','bundle','price_reduction','price_increase','repositioning','delete','upsell','stock_optimization','premium_version') NOT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `suggested_value` decimal(10,2) DEFAULT NULL,
  `estimated_impact` text DEFAULT NULL,
  `status` enum('pending','applied','dismissed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `applied_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `product_reviews` (
  `review_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `product_variations` (
  `variation_id` int(11) NOT NULL,
  `cafe_id` int(11) NOT NULL,
  `variation_name` varchar(100) NOT NULL,
  `variation_type` enum('size','temperature','sweetness','custom') DEFAULT 'custom',
  `is_required` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `product_variation_assignments` (
  `assignment_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `variation_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `raw_materials` (
  `material_id` int(11) NOT NULL,
  `cafe_id` int(11) NOT NULL,
  `material_name` varchar(100) NOT NULL,
  `material_category` varchar(50) DEFAULT NULL,
  `unit_type` enum('gram','ml','piece','kg','liter','pack','other') DEFAULT 'piece',
  `current_cost` decimal(10,2) DEFAULT 0.00,
  `min_stock_level` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `sub_recipes` (
  `sub_recipe_id` int(11) NOT NULL,
  `cafe_id` int(11) NOT NULL,
  `sub_recipe_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `sub_recipe_ingredients` (
  `sub_recipe_ingredient_id` int(11) NOT NULL,
  `sub_recipe_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','owner','cashier','customer') DEFAULT 'cashier',
  `cafe_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `variation_options` (
  `option_id` int(11) NOT NULL,
  `variation_id` int(11) NOT NULL,
  `option_name` varchar(100) NOT NULL,
  `price_adjustment` decimal(10,2) DEFAULT 0.00,
  `display_order` int(11) DEFAULT 0,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `vouchers` (
  `voucher_id` int(11) NOT NULL,
  `cafe_id` int(11) NOT NULL,
  `voucher_code` varchar(50) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL,
  `min_order_amount` decimal(10,2) DEFAULT 0.00,
  `max_order_amount` decimal(10,2) DEFAULT NULL,
  `applicable_products` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `usage_limit` int(11) DEFAULT NULL,
  `used_count` int(11) DEFAULT 0,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `voucher_performance` (
  `performance_id` int(11) NOT NULL,
  `voucher_id` int(11) NOT NULL,
  `cafe_id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `total_uses` int(11) DEFAULT 0,
  `unique_customers` int(11) DEFAULT 0,
  `redemption_rate` decimal(5,2) DEFAULT 0.00,
  `total_discount_given` decimal(10,2) DEFAULT 0.00,
  `total_revenue_before_discount` decimal(10,2) DEFAULT 0.00,
  `total_revenue_after_discount` decimal(10,2) DEFAULT 0.00,
  `avg_order_value_with_voucher` decimal(10,2) DEFAULT 0.00,
  `avg_order_value_without_voucher` decimal(10,2) DEFAULT 0.00,
  `transaction_growth` decimal(5,2) DEFAULT 0.00,
  `peak_hour` int(11) DEFAULT NULL,
  `peak_day` varchar(20) DEFAULT NULL,
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `voucher_product_impact` (
  `impact_id` int(11) NOT NULL,
  `voucher_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `units_sold_with_voucher` int(11) DEFAULT 0,
  `units_sold_without_voucher` int(11) DEFAULT 0,
  `revenue_with_voucher` decimal(10,2) DEFAULT 0.00,
  `revenue_without_voucher` decimal(10,2) DEFAULT 0.00,
  `profit_with_voucher` decimal(10,2) DEFAULT 0.00,
  `profit_without_voucher` decimal(10,2) DEFAULT 0.00,
  `ingredient_cost_increase` decimal(10,2) DEFAULT 0.00,
  `price_sensitivity_score` decimal(5,2) DEFAULT 0.00,
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `voucher_profit_impact` (
  `impact_id` int(11) NOT NULL,
  `voucher_id` int(11) NOT NULL,
  `cafe_id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `total_revenue_before_discount` decimal(10,2) DEFAULT 0.00,
  `total_discount_given` decimal(10,2) DEFAULT 0.00,
  `net_revenue_after_discount` decimal(10,2) DEFAULT 0.00,
  `gross_profit_before_voucher` decimal(10,2) DEFAULT 0.00,
  `gross_profit_after_voucher` decimal(10,2) DEFAULT 0.00,
  `profit_margin_before` decimal(5,2) DEFAULT 0.00,
  `profit_margin_after` decimal(5,2) DEFAULT 0.00,
  `cogs_before` decimal(10,2) DEFAULT 0.00,
  `cogs_after` decimal(10,2) DEFAULT 0.00,
  `profit_difference` decimal(10,2) DEFAULT 0.00,
  `profit_impact_percent` decimal(5,2) DEFAULT 0.00,
  `recommendation` enum('continue','adjust','discontinue') DEFAULT NULL,
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `voucher_usage_log` (
  `log_id` int(11) NOT NULL,
  `voucher_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `discount_amount` decimal(10,2) NOT NULL,
  `order_total_before_discount` decimal(10,2) NOT NULL,
  `order_total_after_discount` decimal(10,2) NOT NULL,
  `used_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `hour_of_day` int(11) DEFAULT NULL,
  `day_of_week` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `voucher_codes` (
  `code_id` int(11) NOT NULL,
  `voucher_id` int(11) NOT NULL,
  `unique_code` varchar(50) NOT NULL,
  `qr_code_data` text DEFAULT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `used_at` timestamp NULL DEFAULT NULL,
  `used_by_order_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);
ALTER TABLE `cafes`
  ADD PRIMARY KEY (`cafe_id`),
  ADD UNIQUE KEY `owner_id` (`owner_id`);
ALTER TABLE `cafe_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `cafe_id` (`cafe_id`);
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`),
  ADD KEY `cafe_id` (`cafe_id`);
ALTER TABLE `customer_favorites`
  ADD PRIMARY KEY (`favorite_id`),
  ADD UNIQUE KEY `unique_favorite` (`customer_id`,`cafe_id`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_cafe` (`cafe_id`);
ALTER TABLE `inventory_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `item_id` (`item_id`);
ALTER TABLE `material_batches`
  ADD PRIMARY KEY (`batch_id`),
  ADD KEY `idx_expiration` (`expiration_date`,`is_used`),
  ADD KEY `idx_material_expiration` (`material_id`,`expiration_date`,`is_used`);
ALTER TABLE `material_cost_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `idx_material_changed` (`material_id`,`changed_at`);
ALTER TABLE `material_usage_log`
  ADD PRIMARY KEY (`usage_id`),
  ADD KEY `order_item_id` (`order_item_id`),
  ADD KEY `idx_material_usage` (`material_id`,`used_at`),
  ADD KEY `idx_batch_usage` (`batch_id`);
ALTER TABLE `menu_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD KEY `cafe_id` (`cafe_id`);
ALTER TABLE `menu_engineering_data`
  ADD PRIMARY KEY (`engineering_id`),
  ADD KEY `idx_period` (`period_start`,`period_end`),
  ADD KEY `idx_item_period` (`item_id`,`period_start`,`period_end`);
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `cafe_id` (`cafe_id`),
  ADD KEY `category_id` (`category_id`);
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `cafe_id` (`cafe_id`),
  ADD KEY `cashier_id` (`cashier_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `prepared_by` (`prepared_by`);
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `item_id` (`item_id`);
ALTER TABLE `order_item_addons`
  ADD PRIMARY KEY (`order_item_addon_id`),
  ADD KEY `order_item_id` (`order_item_id`),
  ADD KEY `addon_id` (`addon_id`);
ALTER TABLE `order_item_variations`
  ADD PRIMARY KEY (`order_item_variation_id`),
  ADD KEY `order_item_id` (`order_item_id`),
  ADD KEY `variation_id` (`variation_id`),
  ADD KEY `option_id` (`option_id`);
ALTER TABLE `order_notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_order` (`order_id`),
  ADD KEY `idx_read` (`is_read`,`created_at`);
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `order_id` (`order_id`);
ALTER TABLE `payment_categories`
  ADD PRIMARY KEY (`payment_category_id`),
  ADD UNIQUE KEY `unique_cafe_payment` (`cafe_id`,`category_name`);
ALTER TABLE `price_change_notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `material_id` (`material_id`),
  ADD KEY `idx_unread` (`cafe_id`,`is_read`,`created_at`);
ALTER TABLE `product_addons`
  ADD PRIMARY KEY (`addon_id`),
  ADD UNIQUE KEY `unique_cafe_addon` (`cafe_id`,`addon_name`);
ALTER TABLE `product_addon_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD UNIQUE KEY `unique_product_addon` (`item_id`,`addon_id`),
  ADD KEY `addon_id` (`addon_id`);
ALTER TABLE `product_analytics_cache`
  ADD PRIMARY KEY (`cache_id`),
  ADD KEY `idx_cafe_period` (`cafe_id`,`period_type`,`period_start`),
  ADD KEY `idx_item_period` (`item_id`,`period_type`,`period_start`);
ALTER TABLE `product_analytics_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `unique_cafe_settings` (`cafe_id`);
ALTER TABLE `product_pricing`
  ADD PRIMARY KEY (`pricing_id`),
  ADD UNIQUE KEY `unique_item_pricing` (`item_id`);
ALTER TABLE `product_recipes`
  ADD PRIMARY KEY (`recipe_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `material_id` (`material_id`),
  ADD KEY `sub_recipe_id` (`sub_recipe_id`);
ALTER TABLE `product_recommendations`
  ADD PRIMARY KEY (`recommendation_id`),
  ADD KEY `idx_cafe_status` (`cafe_id`,`status`),
  ADD KEY `idx_item` (`item_id`);
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD UNIQUE KEY `unique_review` (`item_id`,`customer_id`,`order_id`),
  ADD KEY `idx_item` (`item_id`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_order` (`order_id`),
  ADD KEY `idx_rating` (`rating`);
ALTER TABLE `product_variations`
  ADD PRIMARY KEY (`variation_id`),
  ADD UNIQUE KEY `unique_cafe_variation` (`cafe_id`,`variation_name`);
ALTER TABLE `product_variation_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD UNIQUE KEY `unique_product_variation` (`item_id`,`variation_id`),
  ADD KEY `variation_id` (`variation_id`);
ALTER TABLE `raw_materials`
  ADD PRIMARY KEY (`material_id`),
  ADD UNIQUE KEY `unique_cafe_material` (`cafe_id`,`material_name`);
ALTER TABLE `sub_recipes`
  ADD PRIMARY KEY (`sub_recipe_id`),
  ADD UNIQUE KEY `unique_cafe_sub_recipe` (`cafe_id`,`sub_recipe_name`);
ALTER TABLE `sub_recipe_ingredients`
  ADD PRIMARY KEY (`sub_recipe_ingredient_id`),
  ADD UNIQUE KEY `unique_sub_recipe_material` (`sub_recipe_id`,`material_id`),
  ADD KEY `material_id` (`material_id`);
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `cafe_id` (`cafe_id`);
ALTER TABLE `variation_options`
  ADD PRIMARY KEY (`option_id`),
  ADD UNIQUE KEY `unique_variation_option` (`variation_id`,`option_name`);
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`voucher_id`),
  ADD UNIQUE KEY `unique_cafe_voucher` (`cafe_id`,`voucher_code`);
ALTER TABLE `voucher_performance`
  ADD PRIMARY KEY (`performance_id`),
  ADD KEY `idx_voucher_period` (`voucher_id`,`period_start`,`period_end`),
  ADD KEY `idx_cafe_period` (`cafe_id`,`period_start`,`period_end`);
ALTER TABLE `voucher_product_impact`
  ADD PRIMARY KEY (`impact_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `idx_voucher_item_period` (`voucher_id`,`item_id`,`period_start`,`period_end`);
ALTER TABLE `voucher_profit_impact`
  ADD PRIMARY KEY (`impact_id`),
  ADD KEY `cafe_id` (`cafe_id`),
  ADD KEY `idx_voucher_profit_period` (`voucher_id`,`period_start`,`period_end`);
ALTER TABLE `voucher_usage_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `idx_voucher_usage` (`voucher_id`,`used_at`),
  ADD KEY `idx_customer_usage` (`customer_id`,`used_at`);
ALTER TABLE `voucher_codes`
  ADD PRIMARY KEY (`code_id`),
  ADD UNIQUE KEY `unique_code` (`unique_code`),
  ADD KEY `voucher_id` (`voucher_id`),
  ADD KEY `idx_used` (`is_used`,`voucher_id`);
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `cafes`
  MODIFY `cafe_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
ALTER TABLE `cafe_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;
ALTER TABLE `customer_favorites`
  MODIFY `favorite_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `inventory_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `material_batches`
  MODIFY `batch_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
ALTER TABLE `material_cost_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `material_usage_log`
  MODIFY `usage_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;
ALTER TABLE `menu_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;
ALTER TABLE `menu_engineering_data`
  MODIFY `engineering_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `menu_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=149;
ALTER TABLE `order_item_addons`
  MODIFY `order_item_addon_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=119;
ALTER TABLE `order_item_variations`
  MODIFY `order_item_variation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=149;
ALTER TABLE `order_notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=158;
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;
ALTER TABLE `payment_categories`
  MODIFY `payment_category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
ALTER TABLE `price_change_notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `product_addons`
  MODIFY `addon_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `product_addon_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;
ALTER TABLE `product_analytics_cache`
  MODIFY `cache_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `product_analytics_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `product_pricing`
  MODIFY `pricing_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
ALTER TABLE `product_recipes`
  MODIFY `recipe_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `product_recommendations`
  MODIFY `recommendation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=553;
ALTER TABLE `product_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `product_variations`
  MODIFY `variation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `product_variation_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;
ALTER TABLE `raw_materials`
  MODIFY `material_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
ALTER TABLE `sub_recipes`
  MODIFY `sub_recipe_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `sub_recipe_ingredients`
  MODIFY `sub_recipe_ingredient_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;
ALTER TABLE `variation_options`
  MODIFY `option_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
ALTER TABLE `vouchers`
  MODIFY `voucher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
ALTER TABLE `voucher_performance`
  MODIFY `performance_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `voucher_product_impact`
  MODIFY `impact_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `voucher_profit_impact`
  MODIFY `impact_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `voucher_usage_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;
ALTER TABLE `voucher_codes`
  MODIFY `code_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);
ALTER TABLE `cafes`
  ADD CONSTRAINT `cafes_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
ALTER TABLE `cafe_settings`
  ADD CONSTRAINT `cafe_settings_ibfk_1` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`cafe_id`) ON DELETE CASCADE;
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`cafe_id`);
ALTER TABLE `customer_favorites`
  ADD CONSTRAINT `customer_favorites_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_favorites_ibfk_2` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`cafe_id`) ON DELETE CASCADE;
ALTER TABLE `inventory_logs`
  ADD CONSTRAINT `inventory_logs_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`);
ALTER TABLE `material_batches`
  ADD CONSTRAINT `material_batches_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `raw_materials` (`material_id`) ON DELETE CASCADE;
ALTER TABLE `material_cost_history`
  ADD CONSTRAINT `material_cost_history_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `raw_materials` (`material_id`) ON DELETE CASCADE;
ALTER TABLE `material_usage_log`
  ADD CONSTRAINT `material_usage_log_ibfk_1` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`order_item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `material_usage_log_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `raw_materials` (`material_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `material_usage_log_ibfk_3` FOREIGN KEY (`batch_id`) REFERENCES `material_batches` (`batch_id`) ON DELETE SET NULL;
ALTER TABLE `menu_categories`
  ADD CONSTRAINT `menu_categories_ibfk_1` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`cafe_id`);
ALTER TABLE `menu_engineering_data`
  ADD CONSTRAINT `menu_engineering_data_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE CASCADE;
ALTER TABLE `menu_items`
  ADD CONSTRAINT `menu_items_ibfk_1` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`cafe_id`),
  ADD CONSTRAINT `menu_items_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `menu_categories` (`category_id`);
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`cafe_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  ADD CONSTRAINT `orders_ibfk_4` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`);
ALTER TABLE `order_item_addons`
  ADD CONSTRAINT `order_item_addons_ibfk_1` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`order_item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_item_addons_ibfk_2` FOREIGN KEY (`addon_id`) REFERENCES `product_addons` (`addon_id`);
ALTER TABLE `order_item_variations`
  ADD CONSTRAINT `order_item_variations_ibfk_1` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`order_item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_item_variations_ibfk_2` FOREIGN KEY (`variation_id`) REFERENCES `product_variations` (`variation_id`),
  ADD CONSTRAINT `order_item_variations_ibfk_3` FOREIGN KEY (`option_id`) REFERENCES `variation_options` (`option_id`);
ALTER TABLE `order_notifications`
  ADD CONSTRAINT `order_notifications_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_notifications_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);
ALTER TABLE `payment_categories`
  ADD CONSTRAINT `payment_categories_ibfk_1` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`cafe_id`) ON DELETE CASCADE;
ALTER TABLE `price_change_notifications`
  ADD CONSTRAINT `price_change_notifications_ibfk_1` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`cafe_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `price_change_notifications_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `raw_materials` (`material_id`) ON DELETE CASCADE;
ALTER TABLE `product_addons`
  ADD CONSTRAINT `product_addons_ibfk_1` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`cafe_id`) ON DELETE CASCADE;
ALTER TABLE `product_addon_assignments`
  ADD CONSTRAINT `product_addon_assignments_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_addon_assignments_ibfk_2` FOREIGN KEY (`addon_id`) REFERENCES `product_addons` (`addon_id`) ON DELETE CASCADE;
ALTER TABLE `product_analytics_cache`
  ADD CONSTRAINT `product_analytics_cache_ibfk_1` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`cafe_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_analytics_cache_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE CASCADE;
ALTER TABLE `product_analytics_settings`
  ADD CONSTRAINT `product_analytics_settings_ibfk_1` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`cafe_id`) ON DELETE CASCADE;
ALTER TABLE `product_pricing`
  ADD CONSTRAINT `product_pricing_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE CASCADE;
ALTER TABLE `product_recipes`
  ADD CONSTRAINT `product_recipes_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_recipes_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `raw_materials` (`material_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `product_recipes_ibfk_3` FOREIGN KEY (`sub_recipe_id`) REFERENCES `sub_recipes` (`sub_recipe_id`) ON DELETE SET NULL;
ALTER TABLE `product_recommendations`
  ADD CONSTRAINT `product_recommendations_ibfk_1` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`cafe_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_recommendations_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE CASCADE;
ALTER TABLE `product_reviews`
  ADD CONSTRAINT `product_reviews_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_reviews_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_reviews_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE SET NULL;
ALTER TABLE `product_variations`
  ADD CONSTRAINT `product_variations_ibfk_1` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`cafe_id`) ON DELETE CASCADE;
ALTER TABLE `product_variation_assignments`
  ADD CONSTRAINT `product_variation_assignments_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_variation_assignments_ibfk_2` FOREIGN KEY (`variation_id`) REFERENCES `product_variations` (`variation_id`) ON DELETE CASCADE;
ALTER TABLE `raw_materials`
  ADD CONSTRAINT `raw_materials_ibfk_1` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`cafe_id`) ON DELETE CASCADE;
ALTER TABLE `sub_recipes`
  ADD CONSTRAINT `sub_recipes_ibfk_1` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`cafe_id`) ON DELETE CASCADE;
ALTER TABLE `sub_recipe_ingredients`
  ADD CONSTRAINT `sub_recipe_ingredients_ibfk_1` FOREIGN KEY (`sub_recipe_id`) REFERENCES `sub_recipes` (`sub_recipe_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sub_recipe_ingredients_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `raw_materials` (`material_id`) ON DELETE CASCADE;
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`cafe_id`) ON DELETE CASCADE;
ALTER TABLE `variation_options`
  ADD CONSTRAINT `variation_options_ibfk_1` FOREIGN KEY (`variation_id`) REFERENCES `product_variations` (`variation_id`) ON DELETE CASCADE;
ALTER TABLE `vouchers`
  ADD CONSTRAINT `vouchers_ibfk_1` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`cafe_id`) ON DELETE CASCADE;
ALTER TABLE `voucher_performance`
  ADD CONSTRAINT `voucher_performance_ibfk_1` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`voucher_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `voucher_performance_ibfk_2` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`cafe_id`) ON DELETE CASCADE;
ALTER TABLE `voucher_product_impact`
  ADD CONSTRAINT `voucher_product_impact_ibfk_1` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`voucher_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `voucher_product_impact_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE CASCADE;
ALTER TABLE `voucher_profit_impact`
  ADD CONSTRAINT `voucher_profit_impact_ibfk_1` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`voucher_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `voucher_profit_impact_ibfk_2` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`cafe_id`) ON DELETE CASCADE;
ALTER TABLE `voucher_usage_log`
  ADD CONSTRAINT `voucher_usage_log_ibfk_1` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`voucher_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `voucher_usage_log_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `voucher_usage_log_ibfk_3` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE SET NULL;
COMMIT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
