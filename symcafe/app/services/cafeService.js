import api from './api';
import { API_ENDPOINTS } from '../config/api';

export const cafeService = {
  async getCafes() {
    try {
      const response = await api.get(API_ENDPOINTS.CAFES);
      return response.data;
    } catch (error) {
      throw error.response?.data?.message || error.message || 'Failed to fetch cafes';
    }
  },

  async getMenu(cafeId) {
    try {
      const response = await api.get(`${API_ENDPOINTS.MENU}?cafe_id=${cafeId}`);
      return response.data;
    } catch (error) {
      throw error.response?.data?.message || error.message || 'Failed to fetch menu';
    }
  },

  async getTax(cafeId) {
    try {
      const response = await api.get(`${API_ENDPOINTS.TAX}?cafe_id=${cafeId}`);
      return response.data;
    } catch (error) {
      throw error.response?.data?.message || error.message || 'Failed to fetch tax';
    }
  },
};

