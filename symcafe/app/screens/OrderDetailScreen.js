import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  Share,
  Image,
  Linking,
  Alert,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { formatCurrency } from '../utils/currency';
import { colors, spacing, typography } from '../constants/theme';
import { BASE_URL } from '../config/api';
import BottomNav from '../components/BottomNav';

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

export default function OrderDetailScreen({ route, navigation }) {
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

  const handleViewReceipt = async () => {
    try {
      const receiptUrl = `${BASE_URL}receipt.php?order_id=${order.order_id}`;
      const supported = await Linking.canOpenURL(receiptUrl);
      if (supported) {
        await Linking.openURL(receiptUrl);
      } else {
        Alert.alert('Error', 'Cannot open receipt URL');
      }
    } catch (error) {
      console.error('Error opening receipt:', error);
      Alert.alert('Error', 'Failed to open receipt');
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      {/* Order Header with Status */}
      <View style={styles.headerSection}>
        <View style={styles.headerTop}>
          <View style={styles.headerLeft}>
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

      {/* Cafe Info with Logo */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Store Information</Text>
        <View style={styles.cafeInfoRow}>
          {order.logo_url ? (
            <Image 
              source={{ uri: BASE_URL + order.logo_url }} 
              style={styles.cafeLogo}
              resizeMode="cover"
            />
          ) : (
            <View style={styles.cafeLogoPlaceholder}>
              <Ionicons name="storefront" size={32} color={colors.textGray} />
            </View>
          )}
          <View style={styles.cafeInfo}>
            <Text style={styles.infoText}>{order.cafe_name}</Text>
            {order.cafe_address && (
              <View style={styles.infoRow}>
                <Ionicons name="location" size={14} color={colors.textGray} />
                <Text style={styles.infoSubtext}>{order.cafe_address}</Text>
              </View>
            )}
            {order.cafe_phone && (
              <View style={styles.infoRow}>
                <Ionicons name="call" size={14} color={colors.textGray} />
                <Text style={styles.infoSubtext}>{order.cafe_phone}</Text>
              </View>
            )}
          </View>
        </View>
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

      {/* Action Buttons */}
      <View style={styles.actionButtons}>
        <TouchableOpacity style={styles.receiptButton} onPress={handleViewReceipt}>
          <Ionicons name="receipt" size={20} color={colors.primaryWhite} />
          <Text style={styles.receiptButtonText}>View Receipt</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.shareButton} onPress={handleShare}>
          <Ionicons name="share-social" size={20} color={colors.primaryWhite} />
          <Text style={styles.shareButtonText}>Share Order</Text>
        </TouchableOpacity>
      </View>
      
      {/* Bottom Navigation */}
      <BottomNav />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  content: {
    padding: spacing.md,
    paddingBottom: spacing.xl,
  },
  headerSection: {
    backgroundColor: colors.cardBackground,
    borderRadius: 12,
    padding: spacing.lg,
    marginBottom: spacing.md,
    borderWidth: 1,
    borderColor: colors.mediumGray,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  headerTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
  },
  headerLeft: {
    flex: 1,
  },
  orderId: {
    ...typography.h2,
    color: colors.textPrimary,
    marginBottom: spacing.xs,
  },
  orderDate: {
    ...typography.caption,
    color: colors.textSecondary,
  },
  statusBadge: {
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
    borderRadius: 6,
    marginLeft: spacing.sm,
  },
  statusText: {
    ...typography.small,
    color: colors.primaryBlack,
    fontWeight: '600',
  },
  section: {
    backgroundColor: colors.cardBackground,
    borderRadius: 12,
    padding: spacing.lg,
    marginBottom: spacing.md,
    borderWidth: 1,
    borderColor: colors.mediumGray,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  sectionTitle: {
    ...typography.h3,
    color: colors.textPrimary,
    marginBottom: spacing.md,
    fontWeight: '600',
  },
  cafeInfoRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
  },
  cafeLogo: {
    width: 70,
    height: 70,
    borderRadius: 12,
    marginRight: spacing.md,
    backgroundColor: colors.lightGray,
  },
  cafeLogoPlaceholder: {
    width: 70,
    height: 70,
    borderRadius: 12,
    marginRight: spacing.md,
    backgroundColor: colors.lightGray,
    justifyContent: 'center',
    alignItems: 'center',
  },
  cafeInfo: {
    flex: 1,
  },
  infoText: {
    ...typography.body,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: spacing.xs,
  },
  infoRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: spacing.xs,
  },
  infoSubtext: {
    ...typography.caption,
    color: colors.textSecondary,
    marginLeft: spacing.xs,
    flex: 1,
  },
  itemRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: spacing.md,
    paddingBottom: spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.mediumGray,
  },
  itemInfo: {
    flex: 1,
    marginRight: spacing.sm,
  },
  itemName: {
    ...typography.body,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: spacing.xs,
  },
  itemOptions: {
    ...typography.small,
    color: colors.textSecondary,
    marginBottom: spacing.xs,
  },
  itemQuantity: {
    ...typography.caption,
    color: colors.textSecondary,
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
    color: colors.textSecondary,
  },
  detailValue: {
    ...typography.body,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  paymentBadge: {
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
    borderRadius: 6,
  },
  paymentText: {
    ...typography.small,
    color: colors.primaryBlack,
    fontWeight: '600',
  },
  notesContainer: {
    marginTop: spacing.sm,
    padding: spacing.sm,
    backgroundColor: colors.lightGray,
    borderRadius: 8,
  },
  notesText: {
    ...typography.body,
    marginTop: spacing.xs,
    fontStyle: 'italic',
    color: colors.textPrimary,
  },
  summaryRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: spacing.sm,
  },
  summaryLabel: {
    ...typography.body,
    color: colors.textSecondary,
  },
  summaryValue: {
    ...typography.body,
    color: colors.textPrimary,
  },
  totalRow: {
    marginTop: spacing.sm,
    paddingTop: spacing.sm,
    borderTopWidth: 2,
    borderTopColor: colors.mediumGray,
  },
  totalLabel: {
    ...typography.h3,
    color: colors.textPrimary,
  },
  totalValue: {
    ...typography.h2,
    color: colors.success,
    fontWeight: 'bold',
  },
  actionButtons: {
    flexDirection: 'row',
    gap: spacing.md,
    marginTop: spacing.md,
    marginBottom: spacing.xl,
  },
  receiptButton: {
    flex: 1,
    backgroundColor: colors.primaryBrown,
    padding: spacing.md,
    borderRadius: 8,
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'center',
    gap: spacing.xs,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.2,
    shadowRadius: 4,
    elevation: 3,
  },
  receiptButtonText: {
    ...typography.body,
    color: colors.primaryWhite,
    fontWeight: '600',
  },
  shareButton: {
    flex: 1,
    backgroundColor: colors.accentGray,
    padding: spacing.md,
    borderRadius: 8,
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'center',
    gap: spacing.xs,
    borderWidth: 1,
    borderColor: colors.mediumGray,
  },
  shareButtonText: {
    ...typography.body,
    color: colors.primaryWhite,
    fontWeight: '600',
  },
});

