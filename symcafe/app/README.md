# SYMCAFE Mobile App

React Native (Expo) mobile application for customer orders, matching the website's functionality and styling.

## Features

- Customer authentication (Login/Register)
- Store selection
- Browse menu items with variations and add-ons
- Shopping cart
- Order placement and checkout
- Order history and tracking

## Setup

1. Install dependencies:
```bash
npm install
```

2. Configure API URL in `config/api.js`:
   - For local development: Update `API_BASE_URL` to your local IP address
   - Example: `http://192.168.1.100/beandesk/api` (replace with your actual IP)
   - **Important**: Mobile devices cannot use `localhost` - use your computer's IP address instead
   - To find your IP: 
     - Windows: Run `ipconfig` in Command Prompt, look for IPv4 Address
     - Mac/Linux: Run `ifconfig` or `ip addr`, look for inet address
   - For production: Update to your production domain

3. Make sure your backend API is accessible:
   - Ensure XAMPP/WAMP server is running
   - Check that the API endpoints are accessible via browser: `http://YOUR_IP/beandesk/api/cafes.php`
   - For mobile devices on the same network, ensure firewall allows connections

4. Start the development server:
```bash
npm start
```

5. Run on device:
   - Press `i` for iOS simulator (can use localhost)
   - Press `a` for Android emulator (can use 10.0.2.2 instead of localhost)
   - Scan QR code with Expo Go app on physical device (must use IP address)

## Project Structure

```
symcafe/app/
├── App.js                 # Main app entry with navigation
├── screens/              # Screen components
│   ├── LoginScreen.js
│   ├── RegisterScreen.js
│   ├── StoreSelectionScreen.js
│   ├── MenuScreen.js
│   ├── CartScreen.js
│   ├── CheckoutScreen.js
│   ├── OrdersScreen.js
│   └── OrderDetailScreen.js
├── services/            # API services
│   ├── api.js
│   ├── authService.js
│   ├── cafeService.js
│   └── orderService.js
├── components/          # Reusable components
├── constants/           # Theme and constants
│   └── theme.js
├── utils/              # Utility functions
│   ├── currency.js
│   └── storage.js
└── config/             # Configuration
    └── api.js
```

## API Endpoints

The app connects to the PHP backend API located at `/api/`:
- `/api/login.php` - Customer login
- `/api/register.php` - Customer registration
- `/api/cafes.php` - Get list of cafes
- `/api/menu.php` - Get menu by cafe
- `/api/tax.php` - Get tax percentage
- `/api/place_order.php` - Place order
- `/api/orders.php` - Get customer orders

## Styling

The app uses the same dark theme as the website:
- Primary Black: `#252525`
- Primary White: `#FFFFFF`
- Accent Gray: `#3A3A3A`
- Text Gray: `#CCCCCC`

## Requirements

- Node.js 16+
- Expo CLI
- iOS Simulator (Mac) or Android Studio (for emulator)
- Or Expo Go app on physical device

