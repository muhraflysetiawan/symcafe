import api from './api';
import { API_ENDPOINTS } from '../config/api';
import AsyncStorage from '@react-native-async-storage/async-storage';

export const authService = {
  async login(email, password) {
    try {
      const response = await api.post(API_ENDPOINTS.LOGIN, {
        email,
        password,
      });
      
      if (response.data.success) {
        // Store user data
        const userData = response.data.user;
        console.log('[authService] Login successful, storing user:', userData);
        await AsyncStorage.setItem('userData', JSON.stringify(userData));
        
        // Verify it was stored
        const stored = await AsyncStorage.getItem('userData');
        console.log('[authService] User data stored, verification:', stored ? 'OK' : 'FAILED');
        
        return response.data;
      }
      throw new Error(response.data.message || 'Login failed');
    } catch (error) {
      throw error.response?.data?.message || error.message || 'Login failed';
    }
  },

  async register(name, email, password, phone = '') {
    try {
      const response = await api.post(API_ENDPOINTS.REGISTER, {
        name,
        email,
        password,
        phone,
      });
      
      if (response.data.success) {
        return response.data;
      }
      throw new Error(response.data.message || 'Registration failed');
    } catch (error) {
      throw error.response?.data?.message || error.message || 'Registration failed';
    }
  },

  async logout() {
    await AsyncStorage.removeItem('userData');
  },

  async getUserData() {
    const userData = await AsyncStorage.getItem('userData');
    return userData ? JSON.parse(userData) : null;
  },

  async isLoggedIn() {
    const userData = await this.getUserData();
    return userData !== null;
  },
};

