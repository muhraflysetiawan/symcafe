import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  Share,
} from 'react-native';
import { formatCurrency } from '../utils/currency';
import { colors, spacing, typography } from '../constants/theme';
import { BASE_URL } from '../config/api';

const getStatusColor = (status) => {
  switch (status?.toLowerCase()) {
    case 'completed':
      return colors.success;
    case 'ready':
      return '#17a2b8';
    case 'processing':
      return colors.warning;
    case 'customer_cash_payment':
      return '#ffc107';
    case 'pending':
      return colors.textGray;
    case 'cancelled':
      return colors.error;
    default:
      return colors.textGray;
  }
};

const getStatusLabel = (status) => {
  if (!status) return 'Pending';
  return status
    .split('_')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
};

export default function OrderDetailScreen({ route }) {
  const { order } = route.params;

  const handleShare = async () => {
    try {
      const receiptUrl = `${BASE_URL}receipt.php?order_id=${order.order_id}`;
      await Share.share({
        message: `Order #${String(order.order_id).padStart(6, '0')} from ${order.cafe_name}\nTotal: ${formatCurrency(order.total_amount)}\n\nView receipt: ${receiptUrl}`,
      });
    } catch (error) {
      console.error('Error sharing:', error);
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      {/* Order Header */}
      <View style={styles.section}>
        <View style={styles.headerRow}>
          <View>
            <Text style={styles.orderId}>
              Order #{String(order.order_id).padStart(6, '0')}
            </Text>
            <Text style={styles.orderDate}>
              {new Date(order.created_at).toLocaleString('id-ID', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
              })}
            </Text>
          </View>
          <View style={[styles.statusBadge, { backgroundColor: getStatusColor(order.order_status) }]}>
            <Text style={styles.statusText}>
              {getStatusLabel(order.order_status)}
            </Text>
          </View>
        </View>
      </View>

      {/* Cafe Info */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Store Information</Text>
        <Text style={styles.infoText}>{order.cafe_name}</Text>
        {order.cafe_address && (
          <Text style={styles.infoSubtext}>📍 {order.cafe_address}</Text>
        )}
        {order.cafe_phone && (
          <Text style={styles.infoSubtext}>📞 {order.cafe_phone}</Text>
        )}
      </View>

      {/* Order Items */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Order Items</Text>
        {order.items?.map((item, index) => (
          <View key={index} style={styles.itemRow}>
            <View style={styles.itemInfo}>
              <Text style={styles.itemName}>{item.item_name}</Text>
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
              <Text style={styles.itemQuantity}>
                {item.quantity} × {formatCurrency(item.price)}
              </Text>
            </View>
            <Text style={styles.itemTotal}>
              {formatCurrency(parseFloat(item.subtotal))}
            </Text>
          </View>
        ))}
      </View>

      {/* Order Details */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Order Details</Text>
        <View style={styles.detailRow}>
          <Text style={styles.detailLabel}>Order Type:</Text>
          <Text style={styles.detailValue}>
            {order.order_type?.charAt(0).toUpperCase() + order.order_type?.slice(1) || 'Take Away'}
          </Text>
        </View>
        <View style={styles.detailRow}>
          <Text style={styles.detailLabel}>Payment Status:</Text>
          <View style={[styles.paymentBadge, {
            backgroundColor: order.payment_status === 'paid' ? colors.success : colors.warning,
          }]}>
            <Text style={styles.paymentText}>
              {order.payment_status === 'paid' ? 'Paid' : 'Unpaid'}
            </Text>
          </View>
        </View>
        {order.payment && (
          <View style={styles.detailRow}>
            <Text style={styles.detailLabel}>Payment Method:</Text>
            <Text style={styles.detailValue}>
              {order.payment.payment_method?.charAt(0).toUpperCase() + order.payment.payment_method?.slice(1) || 'N/A'}
            </Text>
          </View>
        )}
        {order.customer_notes && (
          <View style={styles.notesContainer}>
            <Text style={styles.detailLabel}>Notes:</Text>
            <Text style={styles.notesText}>{order.customer_notes}</Text>
          </View>
        )}
      </View>

      {/* Order Summary */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Order Summary</Text>
        <View style={styles.summaryRow}>
          <Text style={styles.summaryLabel}>Subtotal:</Text>
          <Text style={styles.summaryValue}>{formatCurrency(order.subtotal)}</Text>
        </View>
        {order.discount > 0 && (
          <View style={styles.summaryRow}>
            <Text style={styles.summaryLabel}>Discount:</Text>
            <Text style={styles.summaryValue}>-{formatCurrency(order.discount)}</Text>
          </View>
        )}
        {order.tax > 0 && (
          <View style={styles.summaryRow}>
            <Text style={styles.summaryLabel}>Tax:</Text>
            <Text style={styles.summaryValue}>{formatCurrency(order.tax)}</Text>
          </View>
        )}
        <View style={[styles.summaryRow, styles.totalRow]}>
          <Text style={styles.totalLabel}>Total:</Text>
          <Text style={styles.totalValue}>{formatCurrency(order.total_amount)}</Text>
        </View>
      </View>

      {/* Share Button */}
      <TouchableOpacity style={styles.shareButton} onPress={handleShare}>
        <Text style={styles.shareButtonText}>Share Order</Text>
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
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
  },
  orderId: {
    ...typography.h2,
    marginBottom: spacing.xs,
  },
  orderDate: {
    ...typography.caption,
    color: colors.textGray,
  },
  statusBadge: {
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
    borderRadius: 4,
  },
  statusText: {
    ...typography.small,
    color: colors.primaryBlack,
    fontWeight: '600',
  },
  sectionTitle: {
    ...typography.h3,
    marginBottom: spacing.md,
  },
  infoText: {
    ...typography.body,
    fontWeight: '600',
    marginBottom: spacing.xs,
  },
  infoSubtext: {
    ...typography.caption,
    color: colors.textGray,
    marginBottom: spacing.xs,
  },
  itemRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: spacing.md,
    paddingBottom: spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.borderGray,
  },
  itemInfo: {
    flex: 1,
    marginRight: spacing.sm,
  },
  itemName: {
    ...typography.body,
    fontWeight: '600',
    marginBottom: spacing.xs,
  },
  itemOptions: {
    ...typography.small,
    color: colors.textGray,
    marginBottom: spacing.xs,
  },
  itemQuantity: {
    ...typography.caption,
    color: colors.textGray,
  },
  itemTotal: {
    ...typography.body,
    color: colors.success,
    fontWeight: 'bold',
  },
  detailRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: spacing.sm,
  },
  detailLabel: {
    ...typography.body,
    color: colors.textGray,
  },
  detailValue: {
    ...typography.body,
    fontWeight: '600',
  },
  paymentBadge: {
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
    borderRadius: 4,
  },
  paymentText: {
    ...typography.small,
    color: colors.primaryBlack,
    fontWeight: '600',
  },
  notesContainer: {
    marginTop: spacing.sm,
  },
  notesText: {
    ...typography.body,
    marginTop: spacing.xs,
    fontStyle: 'italic',
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
  shareButton: {
    backgroundColor: colors.accentGray,
    padding: spacing.md,
    borderRadius: 5,
    alignItems: 'center',
    marginBottom: spacing.xl,
    borderWidth: 1,
    borderColor: colors.borderGray,
  },
  shareButtonText: {
    ...typography.body,
    color: colors.primaryWhite,
    fontWeight: '600',
  },
});

