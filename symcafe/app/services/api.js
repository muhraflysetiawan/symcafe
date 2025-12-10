import axios from 'axios';
import { API_BASE_URL } from '../config/api';
import AsyncStorage from '@react-native-async-storage/async-storage';

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
  timeout: 15000, // 15 seconds timeout
  withCredentials: true, // Include cookies for session support
});

// Request interceptor to add session/auth if needed
api.interceptors.request.use(
  async (config) => {
    // For session-based auth, we rely on cookies sent automatically
    // For future token-based auth, add token here
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Handle unauthorized
      AsyncStorage.removeItem('userData');
    }
    return Promise.reject(error);
  }
);

export default api;

