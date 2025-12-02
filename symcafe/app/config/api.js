// API Configuration
// IMPORTANT: For mobile devices, replace 'localhost' with your computer's IP address
// Example: 'http://192.168.1.100/beandesk/api'
// To find your IP: Run 'ipconfig' (Windows) or 'ifconfig' (Mac/Linux)

export const API_BASE_URL = __DEV__ 
  ? 'http://10.61.4.45/beandesk/api' // Replace YOUR_IP_HERE with your local IP (e.g., 192.168.1.100)
  : 'https://10.61.4.45/api'; // Production

export const BASE_URL = __DEV__
  ? 'http://10.61.4.45/beandesk/' // Replace YOUR_IP_HERE with your local IP
  : 'https://10.61.4.45/';

// API Endpoints
export const API_ENDPOINTS = {
  LOGIN: '/login.php',
  REGISTER: '/register.php',
  CAFES: '/cafes.php',
  MENU: '/menu.php',
  TAX: '/tax.php',
  PLACE_ORDER: '/place_order.php',
  ORDERS: '/orders.php',
};

