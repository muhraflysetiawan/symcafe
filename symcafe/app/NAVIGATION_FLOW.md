# Navigation Flow After Login

## Expected Flow

1. **User logs in** → `LoginScreen` calls `login()` from AuthContext
2. **AuthContext updates** → `isLoggedIn` becomes `true`
3. **App.js re-renders** → Shows "Home" screen (HomeTabs)
4. **HomeTabs displays** → Shows "Stores" tab (StoreSelectionScreen) as default
5. **User selects store** → Navigates to "Menu" screen with cafe data

## Current Navigation Structure

### When NOT Logged In:
- Login Screen
- Register Screen

### When Logged In:
- Home (HomeTabs)
  - Stores Tab (StoreSelectionScreen) ← Should show first
  - Orders Tab (OrdersScreen)
- Menu Screen (requires cafe parameter)
- Cart Screen (requires cafe parameter)
- Checkout Screen
- Order Detail Screen

## Important Notes

1. **Menu Screen requires cafe parameter** - It cannot be accessed without selecting a store first
2. **After login, user should see Store Selection** - Not menu directly
3. **Navigation is automatic** - No manual navigation.reset() needed, AuthContext handles it

## If Menu Shows Without Store Selection

This would indicate:
- Possible cached navigation state
- Missing route parameter validation
- Auto-navigation logic somewhere

Check: Does MenuScreen validate that `cafe` parameter exists?

