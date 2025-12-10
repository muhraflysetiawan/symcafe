# Fix: Expo Go SDK Version Mismatch

## Problem
Your Expo Go app is for SDK 54, but your project uses SDK 49.

## Solution Options

### Option 1: Upgrade Project to SDK 54 (RECOMMENDED)

Since this is a fresh project, upgrading is safe and recommended.

#### Step 1: Update package.json

```bash
cd symcafe/app
```

Update `package.json` to use SDK 54:

```json
{
  "dependencies": {
    "expo": "~54.0.0",
    "react": "18.3.1",
    "react-native": "0.76.5"
  }
}
```

#### Step 2: Run Expo Install (AUTOMATIC)

This will automatically update all dependencies to compatible versions:

```bash
npx expo install --fix
```

#### Step 3: Update Dependencies Manually (If Needed)

Update specific packages:

```bash
npx expo install expo@latest
npx expo install react-native@latest
npx expo install react@latest
npx expo install @react-navigation/native@latest
npx expo install @react-navigation/native-stack@latest
npx expo install @react-navigation/bottom-tabs@latest
npx expo install react-native-screens@latest
npx expo install react-native-safe-area-context@latest
npx expo install @react-native-async-storage/async-storage@latest
npx expo install expo-status-bar@latest
```

#### Step 4: Clean Install

```bash
rm -rf node_modules
npm install
```

#### Step 5: Restart Expo

```bash
npm start
```

---

### Option 2: Install Older Expo Go (Alternative)

If you prefer to stay on SDK 49:

1. **Uninstall current Expo Go** from your phone
2. **Download Expo Go SDK 49**:
   - Android: [Direct APK Download](https://d1ahtucjixef4r.cloudfront.net/Exponent-2.26.1.apk)
   - iOS: Not available in App Store (need to use TestFlight or upgrade project)

**Note**: This is harder on iOS. Option 1 is recommended.

---

## Quick Fix (Recommended Steps)

Run these commands in order:

```bash
cd symcafe/app

# Upgrade Expo SDK
npx expo install expo@latest

# Fix all dependencies
npx expo install --fix

# Clean and reinstall
rm -rf node_modules package-lock.json
npm install

# Start Expo
npm start
```

---

## After Upgrade

1. Scan QR code again with Expo Go
2. App should now work!
3. Test all features to make sure nothing broke

## Troubleshooting

### If upgrade fails:
- Check `package.json` - make sure versions are updated
- Delete `node_modules` and reinstall
- Clear Expo cache: `npx expo start -c`

### If app doesn't load:
- Check API URL in `config/api.js` (should use your IP, not localhost)
- Ensure backend server is running
- Check network connection

