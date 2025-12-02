# Debug: Infinite Loading Issue

## Symptoms
- Menu screen keeps loading forever
- No error message appears
- Stuck on loading spinner

## Possible Causes

1. **API Call Hanging**
   - Network timeout
   - Server not responding
   - Wrong API URL

2. **Response Format Mismatch**
   - API returns different format than expected
   - `response.success` check failing

3. **State Not Updating**
   - `setIsLoading(false)` not being called
   - Component re-rendering issues

## Debug Steps

1. **Check Console Logs**
   Look for these logs in React Native debugger:
   - "Loading menu for cafe: X"
   - "Fetching menu from API for cafe: X"
   - "API response received: ..."
   - "Menu loaded successfully"
   - "Setting loading to false"

2. **Check API Endpoint**
   - Verify API URL in `config/api.js`
   - Test API directly: `http://YOUR_IP/beandesk/api/menu.php?cafe_id=1`
   - Should return JSON with `success: true`

3. **Check Network Tab**
   - Open React Native debugger
   - Check Network tab for API calls
   - Look for failed/hanging requests

4. **Force Stop Loading**
   - Timeout is set to 20 seconds
   - After 20 seconds, loading should stop
   - If not, there's a state update issue

## Quick Fix

If still loading after fixes:
1. Close and restart the app
2. Check if API server is running
3. Verify IP address in `config/api.js`
4. Check console for error messages

## Test API Directly

```bash
curl http://192.168.1.9/beandesk/api/menu.php?cafe_id=1
```

Should return JSON response with menu data.

