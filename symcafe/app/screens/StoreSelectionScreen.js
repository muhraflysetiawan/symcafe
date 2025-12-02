import React, { useEffect, useState, useRef, useCallback } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  FlatList,
  Image,
  ActivityIndicator,
  Alert,
} from 'react-native';
import { cafeService } from '../services/cafeService';
import { useAuth } from '../context/AuthContext';
import { colors, spacing, typography } from '../constants/theme';
import { BASE_URL } from '../config/api';

export default function StoreSelectionScreen({ navigation }) {
  const [cafes, setCafes] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [hasError, setHasError] = useState(false);
  const isLoadingRef = React.useRef(false);
  const { user, logout } = useAuth();
  const userName = user?.name || '';

  const loadCafes = React.useCallback(async () => {
    if (isLoadingRef.current) {
      console.log('Already loading cafes, skipping');
      return;
    }

    isLoadingRef.current = true;
    setIsLoading(true);
    setHasError(false);
    
    console.log('Loading cafes from API...');
    
    // Safety timeout
    const timeoutId = setTimeout(() => {
      console.error('Cafes load timeout after 20 seconds');
      if (isLoadingRef.current) {
        isLoadingRef.current = false;
        setIsLoading(false);
        setHasError(true);
      }
    }, 20000);
    
    try {
      const response = await cafeService.getCafes();
      clearTimeout(timeoutId);
      
      console.log('API response received:', { 
        success: response?.success, 
        cafeCount: response?.cafes?.length || 0
      });
      
      if (response && response.success) {
        setCafes(response.cafes || []);
        setHasError(false);
        console.log('Cafes loaded successfully:', response.cafes?.length || 0);
      } else {
        console.error('API returned success=false:', response);
        setHasError(true);
      }
    } catch (error) {
      clearTimeout(timeoutId);
      console.error('Cafes load error:', error);
      console.error('Error details:', error.message, error.response?.data);
      setHasError(true);
      Alert.alert(
        'Error Loading Stores',
        error.message || 'Unable to connect to server. Please check your connection.',
        [
          { text: 'Retry', onPress: () => loadCafes() },
          { text: 'OK', style: 'cancel' }
        ]
      );
    } finally {
      setIsLoading(false);
      isLoadingRef.current = false;
      console.log('Cafes loading finished');
    }
  }, []);

  // Load cafes when component mounts - only once
  useEffect(() => {
    loadCafes();
    // Remove focus listener to prevent infinite loops
    // Focus listener was causing repeated reloads
  }, []); // Empty deps - only run once on mount

  const handleSelectStore = (cafe) => {
    navigation.navigate('Menu', { cafe });
  };

  const handleLogout = async () => {
    Alert.alert(
      'Logout',
      'Are you sure you want to logout?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Logout',
          style: 'destructive',
          onPress: async () => {
            await logout();
            // Auth context will update, and App.js will automatically show Login screen
          },
        },
      ]
    );
  };

  const renderCafeItem = ({ item }) => (
    <TouchableOpacity
      style={styles.cafeCard}
      onPress={() => handleSelectStore(item)}
    >
      {item.logo_url && (
        <Image
          source={{ uri: item.logo_url }}
          style={styles.cafeLogo}
          resizeMode="cover"
        />
      )}
      <View style={styles.cafeInfo}>
        <Text style={styles.cafeName}>{item.cafe_name}</Text>
        {item.address && (
          <Text style={styles.cafeAddress}>📍 {item.address}</Text>
        )}
        {item.phone && (
          <Text style={styles.cafePhone}>📞 {item.phone}</Text>
        )}
        {item.description && (
          <Text style={styles.cafeDescription} numberOfLines={2}>
            {item.description}
          </Text>
        )}
      </View>
    </TouchableOpacity>
  );

  if (hasError && !isLoading) {
    return (
      <View style={styles.loadingContainer}>
        <Text style={styles.errorText}>Unable to load stores</Text>
        <TouchableOpacity
          style={styles.retryButton}
          onPress={() => {
            setHasError(false);
            loadCafes();
          }}
        >
          <Text style={styles.retryButtonText}>Retry</Text>
        </TouchableOpacity>
      </View>
    );
  }

  if (isLoading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={colors.primaryWhite} />
        <Text style={styles.loadingText}>Loading stores...</Text>
        <TouchableOpacity
          style={[styles.retryButton, { backgroundColor: colors.accentGray, marginTop: spacing.md }]}
          onPress={() => {
            isLoadingRef.current = false;
            setIsLoading(false);
            setHasError(true);
          }}
        >
          <Text style={[styles.retryButtonText, { color: colors.primaryWhite }]}>Cancel Loading</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <View style={styles.headerBar}>
        <Text style={styles.welcomeText}>Welcome, {userName}</Text>
        <TouchableOpacity onPress={handleLogout}>
          <Text style={styles.logoutText}>Logout</Text>
        </TouchableOpacity>
      </View>

      <FlatList
        data={cafes}
        renderItem={renderCafeItem}
        keyExtractor={(item) => item.cafe_id.toString()}
        contentContainerStyle={styles.listContent}
        ListEmptyComponent={
          <View style={styles.emptyContainer}>
            <Text style={styles.emptyText}>No cafes available</Text>
          </View>
        }
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.primaryBlack,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: colors.primaryBlack,
  },
  headerBar: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: spacing.md,
    backgroundColor: colors.accentGray,
    borderBottomWidth: 1,
    borderBottomColor: colors.borderGray,
  },
  welcomeText: {
    ...typography.body,
    color: colors.primaryWhite,
  },
  logoutText: {
    ...typography.body,
    color: colors.textGray,
  },
  listContent: {
    padding: spacing.md,
  },
  cafeCard: {
    backgroundColor: colors.accentGray,
    borderRadius: 8,
    padding: spacing.md,
    marginBottom: spacing.md,
    flexDirection: 'row',
    borderWidth: 1,
    borderColor: colors.borderGray,
  },
  cafeLogo: {
    width: 80,
    height: 80,
    borderRadius: 8,
    marginRight: spacing.md,
  },
  cafeInfo: {
    flex: 1,
  },
  cafeName: {
    ...typography.h3,
    marginBottom: spacing.xs,
  },
  cafeAddress: {
    ...typography.caption,
    marginBottom: spacing.xs,
  },
  cafePhone: {
    ...typography.caption,
    marginBottom: spacing.xs,
  },
  cafeDescription: {
    ...typography.small,
    marginTop: spacing.xs,
  },
  emptyContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: spacing.xl,
  },
  emptyText: {
    ...typography.body,
    color: colors.textGray,
  },
  loadingText: {
    ...typography.body,
    color: colors.textGray,
    marginTop: spacing.md,
  },
  errorText: {
    ...typography.h3,
    color: colors.error || '#ff4444',
    marginBottom: spacing.md,
    textAlign: 'center',
  },
  retryButton: {
    backgroundColor: colors.primaryWhite,
    padding: spacing.md,
    borderRadius: 5,
    marginTop: spacing.md,
    minWidth: 150,
    alignItems: 'center',
  },
  retryButtonText: {
    color: colors.primaryBlack,
    fontWeight: '600',
  },
});

