import React, { useEffect, useState, useCallback } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  FlatList,
  ActivityIndicator,
  RefreshControl,
  Alert,
  Image,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { orderService } from '../services/orderService';
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

export default function OrdersScreen({ navigation }) {
  const [orders, setOrders] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  useEffect(() => {
    loadOrders();
    
    // Refresh when screen comes into focus
    const unsubscribe = navigation.addListener('focus', () => {
      loadOrders();
    });
    
    return unsubscribe;
  }, [navigation]);

  const loadOrders = async () => {
    setIsLoading(true);
    try {
      console.log('[OrdersScreen] Loading orders...');
      const response = await orderService.getOrders();
      console.log('[OrdersScreen] Response:', response);
      
      if (response && response.success) {
        setOrders(response.orders || []);
        console.log('[OrdersScreen] Orders loaded:', (response.orders || []).length);
      } else {
        console.error('[OrdersScreen] Failed to load orders:', response);
        Alert.alert('Error', response?.message || 'Failed to load orders');
      }
    } catch (error) {
      console.error('[OrdersScreen] Error:', error);
      console.error('[OrdersScreen] Error details:', {
        message: error.message,
        response: error.response?.data,
        status: error.response?.status,
      });
      
      const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch orders';
      Alert.alert('Error Loading Orders', errorMessage);
      setOrders([]);
    } finally {
      setIsLoading(false);
      setRefreshing(false);
    }
  };

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    loadOrders();
  }, []);

  const handleOrderPress = (order) => {
    navigation.navigate('OrderDetail', { order });
  };

  const renderOrderItem = ({ item }) => {
    const statusColor = getStatusColor(item.order_status);
    const statusLabel = getStatusLabel(item.order_status);
    
    return (
      <TouchableOpacity
        style={styles.orderCard}
        onPress={() => handleOrderPress(item)}
        activeOpacity={0.7}
      >
        <View style={styles.orderCardContent}>
          {/* Cafe Logo and Info */}
          <View style={styles.cafeInfoRow}>
            {item.logo_url ? (
              <Image 
                source={{ uri: BASE_URL + item.logo_url }} 
                style={styles.cafeLogo}
                resizeMode="cover"
              />
            ) : (
              <View style={styles.cafeLogoPlaceholder}>
                <Ionicons name="storefront" size={24} color={colors.textGray} />
              </View>
            )}
            <View style={styles.cafeInfo}>
              <Text style={styles.cafeName}>{item.cafe_name}</Text>
              {item.cafe_address && (
                <Text style={styles.cafeAddress} numberOfLines={1}>
                  <Ionicons name="location" size={12} color={colors.textGray} /> {item.cafe_address}
                </Text>
              )}
            </View>
          </View>
          
          {/* Order Header */}
          <View style={styles.orderHeader}>
            <View style={styles.orderHeaderLeft}>
              <Text style={styles.orderId}>
                Order #{String(item.order_id).padStart(6, '0')}
              </Text>
              <Text style={styles.orderDate}>
                {new Date(item.created_at).toLocaleString('id-ID', {
                  year: 'numeric',
                  month: 'short',
                  day: 'numeric',
                  hour: '2-digit',
                  minute: '2-digit',
                })}
              </Text>
            </View>
            <View style={[styles.statusBadge, { backgroundColor: statusColor }]}>
              <Text style={styles.statusText}>{statusLabel}</Text>
            </View>
          </View>
          
          {/* Order Type */}
          <View style={styles.orderTypeContainer}>
            <Ionicons name="restaurant" size={14} color={colors.textGray} />
            <Text style={styles.orderType}>
              {item.order_type?.charAt(0).toUpperCase() + item.order_type?.slice(1) || 'Take Away'}
            </Text>
          </View>
          
          {/* Order Footer */}
          <View style={styles.orderFooter}>
            <View>
              <Text style={styles.totalLabel}>Total</Text>
              <Text style={styles.orderTotal}>{formatCurrency(item.total_amount)}</Text>
            </View>
            <View style={[styles.paymentBadge, {
              backgroundColor: item.payment_status === 'paid' ? colors.success : colors.warning,
            }]}>
              <Ionicons 
                name={item.payment_status === 'paid' ? 'checkmark-circle' : 'time'} 
                size={14} 
                color={colors.primaryBlack} 
              />
              <Text style={styles.paymentText}>
                {item.payment_status === 'paid' ? 'Paid' : 'Unpaid'}
              </Text>
            </View>
          </View>
        </View>
      </TouchableOpacity>
    );
  };

  if (isLoading && orders.length === 0) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={colors.primaryWhite} />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <FlatList
        data={orders}
        renderItem={renderOrderItem}
        keyExtractor={(item) => item.order_id.toString()}
        contentContainerStyle={styles.listContent}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={onRefresh}
            tintColor={colors.primaryWhite}
          />
        }
        ListEmptyComponent={
          <View style={styles.emptyContainer}>
            <Text style={styles.emptyText}>No orders yet</Text>
            <Text style={styles.emptySubtext}>Start browsing stores to place your first order!</Text>
          </View>
        }
      />
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
  },
  orderCard: {
    backgroundColor: colors.cardBackground,
    borderRadius: 12,
    marginBottom: spacing.md,
    borderWidth: 1,
    borderColor: colors.mediumGray,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
    overflow: 'hidden',
  },
  orderCardContent: {
    padding: spacing.lg,
  },
  cafeInfoRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: spacing.md,
    paddingBottom: spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.mediumGray,
  },
  cafeLogo: {
    width: 50,
    height: 50,
    borderRadius: 8,
    marginRight: spacing.sm,
    backgroundColor: colors.lightGray,
  },
  cafeLogoPlaceholder: {
    width: 50,
    height: 50,
    borderRadius: 8,
    marginRight: spacing.sm,
    backgroundColor: colors.lightGray,
    justifyContent: 'center',
    alignItems: 'center',
  },
  cafeInfo: {
    flex: 1,
  },
  cafeName: {
    ...typography.body,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: spacing.xs,
  },
  cafeAddress: {
    ...typography.small,
    color: colors.textSecondary,
    flexDirection: 'row',
    alignItems: 'center',
  },
  orderHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: spacing.sm,
  },
  orderHeaderLeft: {
    flex: 1,
  },
  orderId: {
    ...typography.body,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: spacing.xs,
  },
  orderDate: {
    ...typography.small,
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
    fontSize: 10,
  },
  orderTypeContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: spacing.sm,
  },
  orderType: {
    ...typography.caption,
    color: colors.textSecondary,
    marginLeft: spacing.xs,
  },
  orderFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: spacing.sm,
    paddingTop: spacing.sm,
    borderTopWidth: 1,
    borderTopColor: colors.mediumGray,
  },
  totalLabel: {
    ...typography.small,
    color: colors.textSecondary,
    marginBottom: spacing.xs,
  },
  orderTotal: {
    ...typography.h3,
    color: colors.success,
    fontWeight: 'bold',
  },
  paymentBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
    borderRadius: 6,
    gap: spacing.xs,
  },
  paymentText: {
    ...typography.small,
    color: colors.primaryBlack,
    fontWeight: '600',
    fontSize: 10,
  },
  emptyContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: spacing.xl,
    minHeight: 400,
  },
  emptyText: {
    ...typography.h3,
    color: colors.textGray,
    marginBottom: spacing.sm,
  },
  emptySubtext: {
    ...typography.body,
    color: colors.textGray,
    textAlign: 'center',
  },
});

