import React from 'react';
import { View, TouchableOpacity, Text, StyleSheet } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useNavigation, useRoute } from '@react-navigation/native';
import { colors, spacing, typography } from '../constants/theme';

export default function BottomNav() {
  const navigation = useNavigation();
  const route = useRoute();

  const isActive = (screenName) => {
    if (screenName === 'Home' && (route.name === 'Stores' || route.name === 'StoreSelection')) {
      return true;
    }
    if (screenName === 'Orders' && route.name === 'Orders') {
      return true;
    }
    return route.name === screenName;
  };

  const navigateToScreen = (screenName) => {
    if (screenName === 'Home') {
      navigation.navigate('Home', { screen: 'Stores' });
    } else if (screenName === 'Orders') {
      navigation.navigate('Home', { screen: 'Orders' });
    } else {
      navigation.navigate(screenName);
    }
  };

  const navItems = [
    {
      name: 'Home',
      label: 'Stores',
      icon: 'storefront',
      screen: 'Home',
    },
    {
      name: 'Orders',
      label: 'Orders',
      icon: 'receipt',
      screen: 'Orders',
    },
  ];

  return (
    <View style={styles.container}>
      {navItems.map((item) => {
        const active = isActive(item.name);
        return (
          <TouchableOpacity
            key={item.name}
            style={[styles.navItem, active && styles.navItemActive]}
            onPress={() => navigateToScreen(item.screen)}
            activeOpacity={0.7}
          >
            <Ionicons
              name={active ? item.icon : `${item.icon}-outline`}
              size={24}
              color={active ? colors.primaryWhite : colors.textGray}
            />
            <Text style={[styles.navLabel, active && styles.navLabelActive]}>
              {item.label}
            </Text>
          </TouchableOpacity>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    backgroundColor: colors.primaryBlack,
    borderTopWidth: 1,
    borderTopColor: colors.borderGray,
    paddingVertical: spacing.sm,
    paddingHorizontal: spacing.md,
    justifyContent: 'space-around',
    alignItems: 'center',
    height: 60,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: -2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 5,
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    zIndex: 100,
  },
  navItem: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: spacing.xs,
  },
  navItemActive: {
    // Active state styling
  },
  navLabel: {
    ...typography.small,
    color: colors.textGray,
    marginTop: spacing.xs,
    fontWeight: '500',
  },
  navLabelActive: {
    color: colors.primaryWhite,
    fontWeight: '600',
  },
});

