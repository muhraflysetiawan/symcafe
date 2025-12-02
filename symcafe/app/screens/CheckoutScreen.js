import React, { useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  ScrollView,
  TextInput,
  Alert,
  ActivityIndicator,
} from 'react-native';
import { orderService } from '../services/orderService';
import { formatCurrency } from '../utils/currency';
import { colors, spacing, typography } from '../constants/theme';
import { storage } from '../utils/storage';

export default function CheckoutScreen({ route, navigation }) {
  const { cafe, cart, subtotal, tax, total, taxPercentage } = route.params;
  
  const [orderType, setOrderType] = useState('take-away');
  const [paymentMethod, setPaymentMethod] = useState('cash');
  const [customerNotes, setCustomerNotes] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const paymentMethods = ['cash', 'qris', 'debit', 'credit'];

  const handlePlaceOrder = async () => {
    if (cart.length === 0) {
      Alert.alert('Error', 'Your cart is empty');
      return;
    }

    setIsSubmitting(true);
    try {
      const orderData = {
        cafe_id: cafe.cafe_id,
        cart: cart,
        order_type: orderType,
        payment_method: paymentMethod,
        customer_notes: customerNotes,
        subtotal: subtotal,
        tax: tax,
        total: total,
      };

      console.log('[CheckoutScreen] Placing order...', {
        cafe_id: cafe.cafe_id,
        cart_items: cart.length,
        total: total,
      });

      const response = await orderService.placeOrder(orderData);
      console.log('[CheckoutScreen] Order response:', response);
      
      if (response && response.success) {
        // Clear cart
        await storage.removeItem('cart');
        
        Alert.alert(
          'Success',
          response.payment_status === 'paid'
            ? `Order placed and paid successfully! Order #${response.order_id}`
            : `Order placed successfully! Order #${response.order_id}. Please pay at the store.`,
          [
            {
              text: 'OK',
              onPress: () => {
                navigation.reset({
                  index: 0,
                  routes: [{ name: 'Home' }],
                });
              },
            },
          ]
        );
      } else {
        const errorMsg = response?.message || 'Failed to place order';
        console.error('[CheckoutScreen] Order failed:', errorMsg);
        Alert.alert('Order Failed', errorMsg);
      }
    } catch (error) {
      console.error('[CheckoutScreen] Error:', error);
      console.error('[CheckoutScreen] Error details:', {
        message: error.message,
        response: error.response?.data,
        status: error.response?.status,
      });
      
      const errorMessage = error.response?.data?.message || error.message || 'Failed to place order. Please try again.';
      Alert.alert('Order Failed', errorMessage);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      {/* Order Summary */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Order Summary</Text>
        {cart.map((item, index) => (
          <View key={index} style={styles.summaryItem}>
            <Text style={styles.summaryItemName}>
              {item.name} × {item.quantity}
            </Text>
            <Text style={styles.summaryItemPrice}>
              {formatCurrency(parseFloat(item.price) * item.quantity)}
            </Text>
          </View>
        ))}
        
        <View style={styles.summaryTotal}>
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
        </View>
      </View>

      {/* Order Type */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Order Type</Text>
        <View style={styles.radioGroup}>
          <TouchableOpacity
            style={[
              styles.radioButton,
              orderType === 'take-away' && styles.radioButtonSelected,
            ]}
            onPress={() => setOrderType('take-away')}
          >
            <Text
              style={[
                styles.radioButtonText,
                orderType === 'take-away' && styles.radioButtonTextSelected,
              ]}
            >
              Take Away
            </Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[
              styles.radioButton,
              orderType === 'dine-in' && styles.radioButtonSelected,
            ]}
            onPress={() => setOrderType('dine-in')}
          >
            <Text
              style={[
                styles.radioButtonText,
                orderType === 'dine-in' && styles.radioButtonTextSelected,
              ]}
            >
              Dine In
            </Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Payment Method */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Payment Method</Text>
        <View style={styles.paymentGrid}>
          {paymentMethods.map((method) => (
            <TouchableOpacity
              key={method}
              style={[
                styles.paymentButton,
                paymentMethod === method && styles.paymentButtonSelected,
              ]}
              onPress={() => setPaymentMethod(method)}
            >
              <Text
                style={[
                  styles.paymentButtonText,
                  paymentMethod === method && styles.paymentButtonTextSelected,
                ]}
              >
                {method.charAt(0).toUpperCase() + method.slice(1)}
              </Text>
            </TouchableOpacity>
          ))}
        </View>
      </View>

      {/* Customer Notes */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Notes (Optional)</Text>
        <TextInput
          style={styles.notesInput}
          placeholder="Add any special instructions..."
          placeholderTextColor={colors.textGray}
          value={customerNotes}
          onChangeText={setCustomerNotes}
          multiline
          numberOfLines={4}
          textAlignVertical="top"
        />
      </View>

      {/* Place Order Button */}
      <TouchableOpacity
        style={[styles.placeOrderButton, isSubmitting && styles.buttonDisabled]}
        onPress={handlePlaceOrder}
        disabled={isSubmitting}
      >
        {isSubmitting ? (
          <ActivityIndicator color={colors.primaryBlack} />
        ) : (
          <Text style={styles.placeOrderButtonText}>Place Order</Text>
        )}
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.primaryBlack,
  },
  content: {
    padding: spacing.md,
  },
  section: {
    backgroundColor: colors.accentGray,
    borderRadius: 8,
    padding: spacing.md,
    marginBottom: spacing.md,
    borderWidth: 1,
    borderColor: colors.borderGray,
  },
  sectionTitle: {
    ...typography.h3,
    marginBottom: spacing.md,
  },
  summaryItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: spacing.sm,
  },
  summaryItemName: {
    ...typography.body,
    flex: 1,
  },
  summaryItemPrice: {
    ...typography.body,
    color: colors.textGray,
  },
  summaryTotal: {
    marginTop: spacing.md,
    paddingTop: spacing.md,
    borderTopWidth: 1,
    borderTopColor: colors.borderGray,
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
  radioGroup: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  radioButton: {
    flex: 1,
    padding: spacing.md,
    borderRadius: 5,
    borderWidth: 2,
    borderColor: colors.borderGray,
    backgroundColor: colors.primaryBlack,
    alignItems: 'center',
  },
  radioButtonSelected: {
    borderColor: colors.primaryWhite,
    backgroundColor: colors.accentGray,
  },
  radioButtonText: {
    ...typography.body,
    color: colors.textGray,
  },
  radioButtonTextSelected: {
    color: colors.primaryWhite,
    fontWeight: '600',
  },
  paymentGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
  },
  paymentButton: {
    flex: 1,
    minWidth: '45%',
    padding: spacing.md,
    borderRadius: 5,
    borderWidth: 2,
    borderColor: colors.borderGray,
    backgroundColor: colors.primaryBlack,
    alignItems: 'center',
  },
  paymentButtonSelected: {
    borderColor: colors.primaryWhite,
    backgroundColor: colors.accentGray,
  },
  paymentButtonText: {
    ...typography.body,
    color: colors.textGray,
  },
  paymentButtonTextSelected: {
    color: colors.primaryWhite,
    fontWeight: '600',
  },
  notesInput: {
    backgroundColor: colors.primaryBlack,
    borderWidth: 2,
    borderColor: colors.borderGray,
    borderRadius: 5,
    padding: spacing.md,
    fontSize: 16,
    color: colors.primaryWhite,
    minHeight: 100,
  },
  placeOrderButton: {
    backgroundColor: colors.primaryWhite,
    padding: spacing.md,
    borderRadius: 5,
    alignItems: 'center',
    marginTop: spacing.md,
    marginBottom: spacing.xl,
  },
  buttonDisabled: {
    opacity: 0.6,
  },
  placeOrderButtonText: {
    color: colors.primaryBlack,
    fontWeight: '600',
    fontSize: 16,
  },
});

