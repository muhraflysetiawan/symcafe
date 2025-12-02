import React, { useEffect, useState } from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { StatusBar } from 'expo-status-bar';
import { View, ActivityIndicator, StyleSheet } from 'react-native';
import { colors } from './constants/theme';
import { AuthProvider, useAuth } from './context/AuthContext';

// Screens
import LoginScreen from './screens/LoginScreen';
import RegisterScreen from './screens/RegisterScreen';
import StoreSelectionScreen from './screens/StoreSelectionScreen';
import MenuScreen from './screens/MenuScreen';
import CartScreen from './screens/CartScreen';
import CheckoutScreen from './screens/CheckoutScreen';
import OrdersScreen from './screens/OrdersScreen';
import OrderDetailScreen from './screens/OrderDetailScreen';

const Stack = createNativeStackNavigator();
const Tab = createBottomTabNavigator();

function HomeTabs() {
  return (
    <Tab.Navigator
      screenOptions={{
        headerStyle: {
          backgroundColor: colors.primaryBlack,
        },
        headerTintColor: colors.primaryWhite,
        tabBarStyle: {
          backgroundColor: colors.primaryBlack,
          borderTopColor: colors.borderGray,
        },
        tabBarActiveTintColor: colors.primaryWhite,
        tabBarInactiveTintColor: colors.textGray,
      }}
    >
      <Tab.Screen 
        name="Stores" 
        component={StoreSelectionScreen}
        options={{
          title: 'Stores',
          headerTitle: 'SYMCAFE',
        }}
      />
      <Tab.Screen 
        name="Orders" 
        component={OrdersScreen}
        options={{
          title: 'My Orders',
          headerTitle: 'My Orders',
        }}
      />
    </Tab.Navigator>
  );
}

function AppNavigator() {
  const { isLoggedIn, isLoading } = useAuth();

  if (isLoading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={colors.primaryWhite} />
        <StatusBar style="light" />
      </View>
    );
  }

  return (
    <NavigationContainer>
      <StatusBar style="light" />
      <Stack.Navigator
        screenOptions={{
          headerStyle: {
            backgroundColor: colors.primaryBlack,
          },
          headerTintColor: colors.primaryWhite,
          contentStyle: {
            backgroundColor: colors.primaryBlack,
          },
        }}
      >
        {!isLoggedIn ? (
          <>
            <Stack.Screen 
              name="Login" 
              component={LoginScreen}
              options={{ headerShown: false }}
            />
            <Stack.Screen 
              name="Register" 
              component={RegisterScreen}
              options={{ title: 'Register' }}
            />
          </>
        ) : (
          <>
            <Stack.Screen 
              name="Home" 
              component={HomeTabs}
              options={{ headerShown: false }}
            />
            <Stack.Screen 
              name="Menu" 
              component={MenuScreen}
              options={{ title: 'Menu' }}
            />
            <Stack.Screen 
              name="Cart" 
              component={CartScreen}
              options={{ title: 'Shopping Cart' }}
            />
            <Stack.Screen 
              name="Checkout" 
              component={CheckoutScreen}
              options={{ title: 'Checkout' }}
            />
            <Stack.Screen 
              name="OrderDetail" 
              component={OrderDetailScreen}
              options={{ title: 'Order Details' }}
            />
          </>
        )}
      </Stack.Navigator>
    </NavigationContainer>
  );
}

const styles = StyleSheet.create({
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: colors.primaryBlack,
  },
});

export default function App() {
  return (
    <AuthProvider>
      <AppNavigator />
    </AuthProvider>
  );
}

