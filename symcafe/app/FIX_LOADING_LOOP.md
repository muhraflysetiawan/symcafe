# Fix: Infinite Loading Loop in MenuScreen

## Problem
- Menu screen keeps loading forever
- When navigating between tabs (Orders → Stores), menu takes forever to load
- Creates infinite loading circles

## Root Cause
1. `loadMenu` function has `isLoading` in dependency array
2. When `isLoading` changes, `loadMenu` is recreated
3. This triggers `useEffect` to run again
4. Creates infinite loop

## Solution Applied

1. **Use Refs Instead of State for Loading Guard**
   - Added `isLoadingRef` to track if API call is in progress
   - Prevents multiple simultaneous API calls
   - Doesn't trigger re-renders

2. **Remove Focus Listener Reload**
   - Focus listener was reloading menu every time
   - Now only reloads cart (lightweight)
   - Menu loads only once per cafe selection

3. **Stable Function References**
   - `loadMenu` only depends on `cafe?.cafe_id`
   - `loadCart` has no dependencies
   - Functions are stable, won't cause re-renders

4. **Simplified useEffect**
   - Only depends on `cafe?.cafe_id`
   - Removed function dependencies that cause loops

## Changes Made

- Added `isLoadingRef` and `hasLoadedRef` for tracking
- Removed `isLoading` from `loadMenu` dependencies
- Focus listener only reloads cart, not menu
- Better error handling and retry logic

## Testing

After fix:
1. ✅ Menu loads once when store is selected
2. ✅ No infinite loading loops
3. ✅ Switching tabs doesn't reload menu unnecessarily
4. ✅ Cart updates correctly on focus

