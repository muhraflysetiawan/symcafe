<?php
/**
 * Inventory and Pricing Helper Functions
 */

/**
 * Calculate ingredient cost for a product based on its recipe
 */
function calculateProductIngredientCost($conn, $item_id) {
    $total_cost = 0;
    
    try {
        // Get all recipe items (direct materials and sub-recipes)
        $stmt = $conn->prepare("
            SELECT pr.recipe_id, pr.material_id, pr.sub_recipe_id, pr.quantity,
                   m.current_cost, m.unit_type
            FROM product_recipes pr
            LEFT JOIN raw_materials m ON pr.material_id = m.material_id
            WHERE pr.item_id = ?
        ");
        $stmt->execute([$item_id]);
        $recipe_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($recipe_items as $item) {
            if ($item['material_id']) {
                // Direct material
                $total_cost += $item['quantity'] * $item['current_cost'];
            } elseif ($item['sub_recipe_id']) {
                // Sub-recipe - calculate recursively
                $sub_recipe_cost = calculateSubRecipeCost($conn, $item['sub_recipe_id']);
                $total_cost += $item['quantity'] * $sub_recipe_cost;
            }
        }
    } catch (Exception $e) {
        error_log("Error calculating ingredient cost: " . $e->getMessage());
    }
    
    return $total_cost;
}

/**
 * Calculate cost of a sub-recipe
 */
function calculateSubRecipeCost($conn, $sub_recipe_id) {
    $total_cost = 0;
    
    try {
        $stmt = $conn->prepare("
            SELECT sri.material_id, sri.quantity, m.current_cost
            FROM sub_recipe_ingredients sri
            JOIN raw_materials m ON sri.material_id = m.material_id
            WHERE sri.sub_recipe_id = ?
        ");
        $stmt->execute([$sub_recipe_id]);
        $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($ingredients as $ingredient) {
            $total_cost += $ingredient['quantity'] * $ingredient['current_cost'];
        }
    } catch (Exception $e) {
        error_log("Error calculating sub-recipe cost: " . $e->getMessage());
    }
    
    return $total_cost;
}

/**
 * Calculate suggested price based on cost and margin
 */
function calculateSuggestedPrice($ingredient_cost, $margin_percent = 40, $tax_percent = 0) {
    if ($ingredient_cost <= 0) return 0;
    
    // Price = Cost / (1 - Margin% - Tax%)
    $margin_decimal = $margin_percent / 100;
    $tax_decimal = $tax_percent / 100;
    
    $suggested_price = $ingredient_cost / (1 - $margin_decimal - $tax_decimal);
    
    return $suggested_price;
}

/**
 * Apply psychological pricing (e.g., 19900 instead of 20000)
 */
function applyPsychologicalPricing($price) {
    // Round to nearest 100, then subtract 1-10 to make it look cheaper
    $rounded = round($price / 100) * 100;
    
    if ($rounded >= 1000) {
        // For prices >= 1000, subtract 100
        return $rounded - 100;
    } elseif ($rounded >= 100) {
        // For prices >= 100, subtract 10
        return $rounded - 10;
    } else {
        // For prices < 100, subtract 1
        return max(1, $rounded - 1);
    }
}

/**
 * Recalculate pricing for all products using a specific material
 */
function recalculateProductPricing($conn, $cafe_id, $material_id = null) {
    try {
        if ($material_id) {
            // Recalculate only products using this material
            $stmt = $conn->prepare("
                SELECT DISTINCT pr.item_id
                FROM product_recipes pr
                JOIN menu_items mi ON pr.item_id = mi.item_id
                WHERE mi.cafe_id = ? AND pr.material_id = ?
            ");
            $stmt->execute([$cafe_id, $material_id]);
        } else {
            // Recalculate all products
            $stmt = $conn->prepare("
                SELECT item_id FROM menu_items WHERE cafe_id = ?
            ");
            $stmt->execute([$cafe_id]);
        }
        
        $products = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $affected_products = [];
        
        foreach ($products as $item_id) {
            $ingredient_cost = calculateProductIngredientCost($conn, $item_id);
            
            // Get or create pricing record
            $stmt = $conn->prepare("SELECT * FROM product_pricing WHERE item_id = ?");
            $stmt->execute([$item_id]);
            $pricing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($pricing) {
                $margin_percent = $pricing['desired_margin_percent'];
                $suggested_price = calculateSuggestedPrice($ingredient_cost, $margin_percent);
                $min_price = $ingredient_cost * 1.2; // 20% minimum margin
                $max_price = $ingredient_cost * 2.5; // 150% maximum margin
                
                $stmt = $conn->prepare("
                    UPDATE product_pricing 
                    SET ingredient_cost = ?, suggested_price = ?, min_price = ?, max_price = ?, last_calculated_at = NOW()
                    WHERE item_id = ?
                ");
                $stmt->execute([$ingredient_cost, $suggested_price, $min_price, $max_price, $item_id]);
            } else {
                // Create new pricing record
                $margin_percent = 40; // Default
                $suggested_price = calculateSuggestedPrice($ingredient_cost, $margin_percent);
                $min_price = $ingredient_cost * 1.2;
                $max_price = $ingredient_cost * 2.5;
                
                $stmt = $conn->prepare("
                    INSERT INTO product_pricing (item_id, ingredient_cost, desired_margin_percent, suggested_price, min_price, max_price, last_calculated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$item_id, $ingredient_cost, $margin_percent, $suggested_price, $min_price, $max_price]);
            }
            
            $affected_products[] = $item_id;
        }
        
        // Create notification if material cost changed
        if ($material_id && !empty($affected_products)) {
            $stmt = $conn->prepare("SELECT material_name FROM raw_materials WHERE material_id = ?");
            $stmt->execute([$material_id]);
            $material = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($material) {
                $message = "Cost change detected for '{$material['material_name']}'. Price recommendations updated for " . count($affected_products) . " product(s).";
                
                $stmt = $conn->prepare("
                    INSERT INTO price_change_notifications (cafe_id, material_id, affected_products, notification_message)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$cafe_id, $material_id, json_encode($affected_products), $message]);
            }
        }
        
        return $affected_products;
    } catch (Exception $e) {
        error_log("Error recalculating product pricing: " . $e->getMessage());
        return [];
    }
}

/**
 * Get available batches for a material using FEFO (First Expire First Out)
 */
function getAvailableBatches($conn, $material_id, $quantity_needed) {
    try {
        $stmt = $conn->prepare("
            SELECT batch_id, quantity, expiration_date, cost_per_unit
            FROM material_batches
            WHERE material_id = ? 
            AND is_used = 0
            AND quantity > 0
            AND (expiration_date IS NULL OR expiration_date >= CURDATE())
            ORDER BY 
                CASE 
                    WHEN expiration_date IS NULL THEN 1
                    ELSE 0
                END,
                expiration_date ASC,
                received_date ASC
        ");
        $stmt->execute([$material_id]);
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $allocated = [];
        $remaining = $quantity_needed;
        
        foreach ($batches as $batch) {
            if ($remaining <= 0) break;
            
            $take = min($remaining, $batch['quantity']);
            $allocated[] = [
                'batch_id' => $batch['batch_id'],
                'quantity' => $take,
                'cost_per_unit' => $batch['cost_per_unit']
            ];
            $remaining -= $take;
        }
        
        if ($remaining > 0) {
            // Not enough stock available
            return ['success' => false, 'shortage' => $remaining, 'allocated' => $allocated];
        }
        
        return ['success' => true, 'allocated' => $allocated];
    } catch (Exception $e) {
        error_log("Error getting available batches: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Deduct material stock from batches
 */
function deductMaterialStock($conn, $order_item_id, $material_id, $quantity_used) {
    try {
        // Check if we're already in a transaction - if so, don't start a new one
        $in_transaction = $conn->inTransaction();
        
        $allocation = getAvailableBatches($conn, $material_id, $quantity_used);
        
        if (!$allocation['success']) {
            return ['success' => false, 'error' => 'Insufficient stock', 'shortage' => $allocation['shortage'] ?? 0];
        }
        
        // Only start transaction if we're not already in one
        if (!$in_transaction) {
            $conn->beginTransaction();
        }
        
        foreach ($allocation['allocated'] as $alloc) {
            // Update batch quantity
            $stmt = $conn->prepare("
                UPDATE material_batches 
                SET quantity = quantity - ? 
                WHERE batch_id = ? AND quantity >= ?
            ");
            $stmt->execute([$alloc['quantity'], $alloc['batch_id'], $alloc['quantity']]);
            
            // Check if update was successful
            if ($stmt->rowCount() == 0) {
                throw new Exception("Failed to update batch {$alloc['batch_id']} - insufficient quantity");
            }
            
            // Mark batch as used if quantity reaches 0
            $stmt = $conn->prepare("
                UPDATE material_batches 
                SET is_used = 1 
                WHERE batch_id = ? AND quantity <= 0
            ");
            $stmt->execute([$alloc['batch_id']]);
            
            // Log usage (only if table exists)
            try {
                $stmt = $conn->prepare("
                    INSERT INTO material_usage_log (order_item_id, material_id, batch_id, quantity_used, cost_at_time)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$order_item_id, $material_id, $alloc['batch_id'], $alloc['quantity'], $alloc['cost_per_unit']]);
            } catch (PDOException $e) {
                // Table might not exist - log but don't fail
                error_log("Warning: Could not log material usage (table may not exist): " . $e->getMessage());
            }
        }
        
        // Only commit if we started the transaction
        if (!$in_transaction) {
            $conn->commit();
        }
        
        return ['success' => true];
    } catch (Exception $e) {
        // Only rollback if we started the transaction
        if (!$in_transaction && $conn->inTransaction()) {
            try {
                $conn->rollBack();
            } catch (Exception $rollback_error) {
                error_log("Error during rollback in deductMaterialStock: " . $rollback_error->getMessage());
            }
        }
        error_log("Error deducting material stock: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

?>

