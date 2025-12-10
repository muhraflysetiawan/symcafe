# Quick Fix Guide: Expo Go SDK Mismatch

## The Problem
- Expo Go on your phone: SDK 54
- Your project: SDK 49
- They don't match! ❌

## The Solution (3 Simple Steps)

### Step 1: Upgrade Expo SDK
```bash
cd symcafe/app
npx expo install expo@latest
```

### Step 2: Auto-fix All Dependencies
```bash
npx expo install --fix
```

This automatically updates all packages to compatible versions.

### Step 3: Reinstall and Restart
```bash
# Remove old packages
rm -rf node_modules

# Install new versions
npm install

# Start Expo
npm start
```

## That's It! 🎉

After Step 3:
1. Scan the QR code again with Expo Go
2. App should work now! ✅

## If Something Goes Wrong

### Clear Cache and Try Again:
```bash
npx expo start -c
```

### Manual Clean:
```bash
rm -rf node_modules package-lock.json
npm install
npm start
```

## Why This Works

- `expo install --fix` automatically updates all dependencies to match SDK 54
- It keeps everything compatible
- No manual version checking needed

## Need More Details?

See `EXPO_GO_FIX.md` for complete instructions.

