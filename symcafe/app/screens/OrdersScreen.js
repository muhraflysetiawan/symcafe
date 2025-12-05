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
} from 'react-native';
import { orderService } from '../services/orderService';
import { formatCurrency } from '../utils/currency';
import { colors, spacing, typography } from '../constants/theme';

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
      >
        <View style={styles.orderHeader}>
          <View>
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
        
        <View style={styles.orderInfo}>
          <Text style={styles.cafeName}>{item.cafe_name}</Text>
          <Text style={styles.orderType}>
            {item.order_type?.charAt(0).toUpperCase() + item.order_type?.slice(1) || 'Take Away'}
          </Text>
        </View>
        
        <View style={styles.orderFooter}>
          <Text style={styles.orderTotal}>{formatCurrency(item.total_amount)}</Text>
          <View style={[styles.paymentBadge, {
            backgroundColor: item.payment_status === 'paid' ? colors.success : colors.warning,
          }]}>
            <Text style={styles.paymentText}>
              {item.payment_status === 'paid' ? 'Paid' : 'Unpaid'}
            </Text>
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
  orderHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: spacing.sm,
  },
  orderId: {
    ...typography.body,
    fontWeight: '600',
    color: colors.primaryWhite,
    marginBottom: spacing.xs,
  },
  orderDate: {
    ...typography.small,
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
    fontSize: 10,
  },
  orderInfo: {
    marginBottom: spacing.sm,
  },
  cafeName: {
    ...typography.body,
    fontWeight: '600',
    marginBottom: spacing.xs,
  },
  orderType: {
    ...typography.caption,
    color: colors.textGray,
  },
  orderFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: spacing.sm,
    paddingTop: spacing.sm,
    borderTopWidth: 1,
    borderTopColor: colors.borderGray,
  },
  orderTotal: {
    ...typography.h3,
    color: colors.success,
    fontWeight: 'bold',
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

