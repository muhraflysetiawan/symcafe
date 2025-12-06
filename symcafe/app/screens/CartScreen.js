import React, { useEffect, useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  FlatList,
  Alert,
  ActivityIndicator,
} from 'react-native';
import { cafeService } from '../services/cafeService';
import { formatCurrency } from '../utils/currency';
import { colors, spacing, typography } from '../constants/theme';
import { storage } from '../utils/storage';
import BottomNav from '../components/BottomNav';

export default function CartScreen({ route, navigation }) {
  const { cafe } = route.params;
  const [cart, setCart] = useState([]);
  const [taxPercentage, setTaxPercentage] = useState(10);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    loadCart();
    loadTax();
  }, []);

  const loadCart = async () => {
    const cartData = await storage.getItem('cart');
    if (cartData && Array.isArray(cartData)) {
      // Filter cart to show only items from current cafe
      // This ensures carts are completely separate - no merging
      if (cafe && cafe.cafe_id) {
        const cafeCart = cartData.filter(item => {
          // Only show items that belong to the current cafe
          return item.cafe_id === cafe.cafe_id;
        });
        setCart(cafeCart);
      } else {
        setCart([]);
      }
    } else {
      setCart([]);
    }
    setIsLoading(false);
  };

  const loadTax = async () => {
    try {
      const response = await cafeService.getTax(cafe.cafe_id);
      if (response.success) {
        setTaxPercentage(response.tax_percentage);
      }
    } catch (error) {
      console.error('Error loading tax:', error);
    }
  };

  const saveCart = async (newCart) => {
    // Ensure all items in newCart have cafe_id set to current cafe
    const validatedCart = newCart.map(item => ({
      ...item,
      cafe_id: item.cafe_id || cafe.cafe_id, // Ensure cafe_id is set
      cafe: item.cafe || cafe, // Ensure cafe object is set
    }));
    
    // Get existing cart and merge with updated items from current cafe
    try {
      const existingCart = await storage.getItem('cart') || [];
      // Remove items from current cafe (to replace with updated cart)
      // This ensures carts are separate - items from other cafes are preserved
      const otherCafeItems = existingCart.filter(item => {
        // Keep items that have a different cafe_id or no cafe_id (legacy items)
        return item.cafe_id && item.cafe_id !== cafe.cafe_id;
      });
      // Combine with updated cart items - DO NOT merge quantities across cafes
      const mergedCart = [...otherCafeItems, ...validatedCart];
      await storage.setItem('cart', mergedCart);
      setCart(validatedCart);
    } catch (error) {
      console.error('Error saving cart:', error);
      // On error, save only current cafe's cart
      await storage.setItem('cart', validatedCart);
      setCart(validatedCart);
    }
  };

  const updateQuantity = (index, change) => {
    const newCart = [...cart];
    newCart[index].quantity += change;
    
    if (newCart[index].quantity <= 0) {
      newCart.splice(index, 1);
    }
    
    saveCart(newCart);
  };

  const removeItem = (index) => {
    Alert.alert(
      'Remove Item',
      'Are you sure you want to remove this item from cart?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Remove',
          style: 'destructive',
          onPress: () => {
            const newCart = [...cart];
            newCart.splice(index, 1);
            saveCart(newCart);
          },
        },
      ]
    );
  };

  const calculateSubtotal = () => {
    return cart.reduce((sum, item) => sum + parseFloat(item.price) * item.quantity, 0);
  };

  const calculateTax = () => {
    const subtotal = calculateSubtotal();
    return (subtotal * taxPercentage) / 100;
  };

  const calculateTotal = () => {
    const subtotal = calculateSubtotal();
    const taxAmount = calculateTax();
    return subtotal + taxAmount;
  };

  const handleCheckout = () => {
    if (cart.length === 0) {
      Alert.alert('Error', 'Your cart is empty');
      return;
    }
    
    navigation.navigate('Checkout', {
      cafe,
      cart,
      subtotal: calculateSubtotal(),
      tax: calculateTax(),
      total: calculateTotal(),
      taxPercentage,
    });
  };

  const renderCartItem = ({ item, index }) => (
    <View style={styles.cartItem}>
      <View style={styles.itemInfo}>
        <Text style={styles.itemName}>{item.name}</Text>
        <Text style={styles.itemPrice}>{formatCurrency(item.price)} each</Text>
        
        {item.variations && item.variations.length > 0 && (
          <Text style={styles.itemOptions}>
            {item.variations.map(v => v.option_name).join(', ')}
          </Text>
        )}
        
        {item.addons && item.addons.length > 0 && (
          <Text style={styles.itemOptions}>
            Add-ons: {item.addons.map(a => a.addon_name).join(', ')}
          </Text>
        )}
      </View>
      
      <View style={styles.itemActions}>
        <View style={styles.quantityControls}>
          <TouchableOpacity
            style={styles.quantityButton}
            onPress={() => updateQuantity(index, -1)}
          >
            <Text style={styles.quantityButtonText}>-</Text>
          </TouchableOpacity>
          <Text style={styles.quantity}>{item.quantity}</Text>
          <TouchableOpacity
            style={styles.quantityButton}
            onPress={() => updateQuantity(index, 1)}
          >
            <Text style={styles.quantityButtonText}>+</Text>
          </TouchableOpacity>
        </View>
        <Text style={styles.itemTotal}>
          {formatCurrency(parseFloat(item.price) * item.quantity)}
        </Text>
        <TouchableOpacity
          style={styles.removeButton}
          onPress={() => removeItem(index)}
        >
          <Text style={styles.removeButtonText}>×</Text>
        </TouchableOpacity>
      </View>
    </View>
  );

  if (isLoading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={colors.primaryWhite} />
      </View>
    );
  }

  const subtotal = calculateSubtotal();
  const tax = calculateTax();
  const total = calculateTotal();

  return (
    <View style={styles.container}>
      {cart.length === 0 ? (
        <View style={styles.emptyContainer}>
          <Text style={styles.emptyText}>Your cart is empty</Text>
          <TouchableOpacity
            style={styles.shopButton}
            onPress={() => navigation.goBack()}
          >
            <Text style={styles.shopButtonText}>Browse Menu</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <>
          <FlatList
            data={cart}
            renderItem={renderCartItem}
            keyExtractor={(item, index) => index.toString()}
            contentContainerStyle={styles.listContent}
          />
          
          {/* Summary Section - Fixed above bottom nav */}
          <View style={styles.summary}>
            <View style={styles.summaryRow}>
              <Text style={styles.summaryLabel}>Subtotal:</Text>
              <Text style={styles.summaryValue}>{formatCurrency(subtotal)}</Text>
            </View>
            <View style={styles.summaryRow}>
              <Text style={styles.summaryLabel}>Tax ({taxPercentage}%):</Text>
              <Text style={styles.summaryValue}>{formatCurrency(tax)}</Text>
            </View>
            <View style={[styles.summaryRow, styles.totalRow]}>
              <Text style={styles.totalLabel}>Total:</Text>
              <Text style={styles.totalValue}>{formatCurrency(total)}</Text>
            </View>
            
            <TouchableOpacity style={styles.checkoutButton} onPress={handleCheckout}>
              <Text style={styles.checkoutButtonText}>Proceed to Checkout</Text>
            </TouchableOpacity>
          </View>
        </>
      )}
      
      {/* Bottom Navigation */}
      <BottomNav />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  listContent: {
    padding: spacing.md,
    paddingBottom: 280, // Extra padding for summary section (220px) + bottom nav (60px)
  },
  cartItem: {
    backgroundColor: colors.cardBackground,
    borderRadius: 12,
    padding: spacing.md,
    marginBottom: spacing.md,
    borderWidth: 1,
    borderColor: colors.mediumGray,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  itemInfo: {
    marginBottom: spacing.sm,
  },
  itemName: {
    fontSize: 16,
    fontWeight: '700',
    color: colors.textPrimary,
    marginBottom: spacing.xs,
  },
  itemPrice: {
    fontSize: 14,
    color: colors.textSecondary,
    marginBottom: spacing.xs,
  },
  itemOptions: {
    ...typography.small,
    color: colors.textGray,
    marginTop: spacing.xs,
  },
  itemActions: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  quantityControls: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  quantityButton: {
    backgroundColor: colors.accentGray,
    borderWidth: 1,
    borderColor: colors.borderGray,
    width: 30,
    height: 30,
    borderRadius: 4,
    justifyContent: 'center',
    alignItems: 'center',
  },
  quantityButtonText: {
    ...typography.body,
    color: colors.primaryWhite,
  },
  quantity: {
    ...typography.body,
    minWidth: 30,
    textAlign: 'center',
  },
  itemTotal: {
    ...typography.body,
    color: colors.success,
    fontWeight: 'bold',
    flex: 1,
    textAlign: 'right',
    marginRight: spacing.sm,
  },
  removeButton: {
    backgroundColor: colors.error,
    width: 30,
    height: 30,
    borderRadius: 4,
    justifyContent: 'center',
    alignItems: 'center',
  },
  removeButtonText: {
    color: colors.primaryWhite,
    fontSize: 20,
    fontWeight: 'bold',
  },
  summary: {
    position: 'absolute',
    bottom: 60, // Position above bottom nav
    left: 0,
    right: 0,
    backgroundColor: colors.accentGray,
    padding: spacing.md,
    paddingBottom: spacing.md,
    borderTopWidth: 1,
    borderTopColor: colors.borderGray,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: -2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 5,
    zIndex: 10,
  },
  summaryRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: spacing.sm,
  },
  summaryLabel: {
    ...typography.body,
    color: colors.textGray,
  },
  summaryValue: {
    ...typography.body,
  },
  totalRow: {
    marginTop: spacing.sm,
    paddingTop: spacing.sm,
    borderTopWidth: 2,
    borderTopColor: colors.borderGray,
  },
  totalLabel: {
    ...typography.h3,
  },
  totalValue: {
    ...typography.h2,
    color: colors.success,
  },
  checkoutButton: {
    backgroundColor: colors.primaryBrown,
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.lg,
    borderRadius: 12,
    alignItems: 'center',
    marginTop: spacing.md,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.25,
    shadowRadius: 4,
    elevation: 5,
  },
  checkoutButtonText: {
    color: colors.primaryWhite,
    fontWeight: '700',
    fontSize: 18,
  },
  emptyContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: spacing.xl,
  },
  emptyText: {
    ...typography.h3,
    color: colors.textGray,
    marginBottom: spacing.md,
  },
  shopButton: {
    backgroundColor: colors.primaryWhite,
    padding: spacing.md,
    borderRadius: 5,
    minWidth: 150,
    alignItems: 'center',
  },
  shopButtonText: {
    color: colors.primaryBlack,
    fontWeight: '600',
  },
});

