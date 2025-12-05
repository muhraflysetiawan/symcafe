import api from './api';
import { API_ENDPOINTS } from '../config/api';
import AsyncStorage from '@react-native-async-storage/async-storage';

export const orderService = {
  async placeOrder(orderData) {
    try {
      // Get user_id from AsyncStorage for mobile authentication
      const userData = await AsyncStorage.getItem('userData');
      const user = userData ? JSON.parse(userData) : null;
      
      // Try different possible field names for user ID
      const userId = user?.user_id || user?.id || user?.userId;
      
      console.log('[orderService] User data check:', { 
        hasUser: !!user, 
        userId,
        userKeys: user ? Object.keys(user) : [],
        userData: user
      });
      
      if (!userId) {
        console.error('[orderService] No user_id found in user data!');
        console.error('[orderService] Available user fields:', user);
        throw new Error('User not authenticated. Please login again.');
      }
      
      // Add user_id to order data (for mobile auth)
      const orderDataWithUser = { 
        ...orderData, 
        user_id: parseInt(userId) // Ensure it's an integer
      };
      
      console.log('[orderService] Placing order with user_id:', userId);
      console.log('[orderService] Order data:', {
        cafe_id: orderDataWithUser.cafe_id,
        user_id: orderDataWithUser.user_id,
        cart_items: orderDataWithUser.cart?.length,
        total: orderDataWithUser.total
      });
      const response = await api.post(API_ENDPOINTS.PLACE_ORDER, orderDataWithUser);
      
      // Handle case where response.data might be a string (if PHP notices were included)
      let responseData = response.data;
      
      // If response is a string, try to extract JSON from it
      if (typeof responseData === 'string') {
        console.log('[orderService] Response is string, attempting to extract JSON...');
        // Try to find JSON object in the string
        const jsonMatch = responseData.match(/\{[\s\S]*\}/);
        if (jsonMatch) {
          try {
            responseData = JSON.parse(jsonMatch[0]);
            console.log('[orderService] Successfully extracted JSON from string');
          } catch (e) {
            console.error('[orderService] Failed to parse extracted JSON:', e);
            throw new Error('Invalid response format from server');
          }
        } else {
          throw new Error('No JSON found in server response');
        }
      }
      
      console.log('[orderService] Place order response:', responseData);
      return responseData;
    } catch (error) {
      console.error('[orderService] Place order error:', error);
      console.error('[orderService] Error response:', error.response?.data);
      throw error.response?.data?.message || error.message || 'Failed to place order';
    }
  },

  async getOrders() {
    try {
      // Get user_id from AsyncStorage for mobile authentication
      const userData = await AsyncStorage.getItem('userData');
      const user = userData ? JSON.parse(userData) : null;
      const userId = user?.id;
      
      console.log('[orderService] Getting orders for user_id:', userId);
      
      // Add user_id as query parameter if available (for mobile auth)
      const url = userId ? `${API_ENDPOINTS.ORDERS}?user_id=${userId}` : API_ENDPOINTS.ORDERS;
      console.log('[orderService] Fetching from URL:', url);
      
      const response = await api.get(url);
      
      // Handle case where response.data might be a string (if PHP notices were included)
      let responseData = response.data;
      
      // If response is a string, try to extract JSON from it
      if (typeof responseData === 'string') {
        console.log('[orderService] Response is string, attempting to extract JSON...');
        // Try to find JSON object in the string
        const jsonMatch = responseData.match(/\{[\s\S]*\}/);
        if (jsonMatch) {
          try {
            responseData = JSON.parse(jsonMatch[0]);
            console.log('[orderService] Successfully extracted JSON from string');
          } catch (e) {
            console.error('[orderService] Failed to parse extracted JSON:', e);
            throw new Error('Invalid response format from server');
          }
        } else {
          throw new Error('No JSON found in server response');
        }
      }
      
      console.log('[orderService] Get orders response:', responseData);
      return responseData;
    } catch (error) {
      console.error('[orderService] Get orders error:', error);
      console.error('[orderService] Error response:', error.response?.data);
      throw error.response?.data?.message || error.message || 'Failed to fetch orders';
    }
  },
};

