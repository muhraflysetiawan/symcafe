# Setup Instructions for SYMCAFE Mobile App

## Prerequisites

- Node.js 16+ installed
- Expo CLI installed globally: `npm install -g expo-cli`
- XAMPP/WAMP server running with the beandesk project
- Mobile device or emulator

## Step-by-Step Setup

### 1. Install Dependencies

Navigate to the app directory and install dependencies:

```bash
cd symcafe/app
npm install
```

### 2. Configure API URL

**CRITICAL**: Mobile devices cannot connect to `localhost`. You must use your computer's IP address.

1. Find your computer's IP address:
   - **Windows**: Open Command Prompt and run `ipconfig`. Look for "IPv4 Address" (e.g., 192.168.1.100)
   - **Mac/Linux**: Open Terminal and run `ifconfig` or `ip addr`. Look for "inet" address

2. Open `config/api.js` and replace `YOUR_IP_HERE` with your actual IP address:

```javascript
export const API_BASE_URL = __DEV__ 
  ? 'http://192.168.1.100/beandesk/api' // Replace 192.168.1.100 with your IP
  : 'https://your-domain.com/api';

export const BASE_URL = __DEV__
  ? 'http://192.168.1.100/beandesk/' // Replace 192.168.1.100 with your IP
  : 'https://your-domain.com/';
```

### 3. Start Backend Server

Make sure your XAMPP/WAMP server is running and the beandesk project is accessible:
- Apache should be running
- MySQL should be running
- Test in browser: `http://localhost/beandesk/` should load

### 4. Test API Endpoints

Verify API endpoints are accessible:
- Open browser and go to: `http://YOUR_IP/beandesk/api/cafes.php`
- You should see JSON data (not an error page)

**Note**: Make sure your firewall allows incoming connections on port 80 (or your Apache port).

### 5. Start Expo Development Server

```bash
npm start
```

This will open Expo DevTools in your browser.

### 6. Run on Device/Emulator

#### Option A: Physical Device (Recommended for testing)

1. Install "Expo Go" app from App Store (iOS) or Play Store (Android)
2. Make sure your phone and computer are on the same Wi-Fi network
3. Scan the QR code from Expo DevTools with:
   - **iOS**: Use the Camera app
   - **Android**: Use Expo Go app to scan QR code

#### Option B: iOS Simulator (Mac only)

1. Press `i` in the terminal where Expo is running
2. iOS Simulator will open automatically
3. Can use `localhost` in API config for simulator

#### Option C: Android Emulator

1. Start Android Studio and launch an emulator
2. Press `a` in the terminal where Expo is running
3. For Android emulator, use `10.0.2.2` instead of localhost/IP in API config

### 7. Troubleshooting

#### "Network request failed" or "Connection refused"

- Verify your IP address is correct in `config/api.js`
- Ensure XAMPP/WAMP Apache is running
- Check firewall settings (allow port 80)
- Make sure phone and computer are on the same network
- Try accessing API URL directly in phone's browser: `http://YOUR_IP/beandesk/api/cafes.php`

#### "Login failed" or "Authentication required"

- Verify API endpoints are working (test in browser)
- Check that the backend database is accessible
- Ensure customer accounts exist in the database

#### Expo Go can't connect

- Restart Expo server: `npm start`
- Clear Expo cache: `expo start -c`
- Try using Tunnel connection option in Expo DevTools

### 8. Production Build (Optional)

When ready to build for production:

```bash
# Build for iOS (requires Mac and Apple Developer account)
expo build:ios

# Build for Android
expo build:android
```

## API Endpoints Used

The app connects to these API endpoints:
- `/api/login.php` - Customer login
- `/api/register.php` - Customer registration  
- `/api/cafes.php` - Get list of cafes
- `/api/menu.php?cafe_id=X` - Get menu by cafe
- `/api/tax.php?cafe_id=X` - Get tax percentage
- `/api/place_order.php` - Place order (POST)
- `/api/orders.php` - Get customer orders

All endpoints should return JSON with `success: true/false` and appropriate data.

## Notes

- The app uses session-based authentication via PHP sessions
- Cart data is stored locally using AsyncStorage
- Make sure your backend API has CORS headers enabled (already included in API files)
- For development, it's recommended to use a physical device for the best testing experience

