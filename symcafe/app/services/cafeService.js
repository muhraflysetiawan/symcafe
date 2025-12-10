import api from './api';
import { API_ENDPOINTS } from '../config/api';

export const cafeService = {
  async getCafes(searchQuery = '') {
    try {
      const url = searchQuery 
        ? `${API_ENDPOINTS.CAFES}?search=${encodeURIComponent(searchQuery)}`
        : API_ENDPOINTS.CAFES;
      const response = await api.get(url);
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

  async getPromotionalBanners() {
    try {
      const response = await api.get(API_ENDPOINTS.PROMOTIONAL_BANNERS);
      return response.data;
    } catch (error) {
      throw error.response?.data?.message || error.message || 'Failed to fetch banners';
    }
  },
};

