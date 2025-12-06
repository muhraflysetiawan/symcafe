import React, { useEffect, useState, useRef } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  FlatList,
  Image,
  ActivityIndicator,
  TextInput,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { cafeService } from '../services/cafeService';
import { colors, spacing, typography } from '../constants/theme';
import { BASE_URL } from '../config/api';
import BottomNav from '../components/BottomNav';

export default function CafeListScreen({ route, navigation }) {
  const { searchQuery: initialSearchQuery = '' } = route.params || {};
  const [cafes, setCafes] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState(initialSearchQuery);
  const isLoadingRef = useRef(false);

  const loadCafes = async (search = '') => {
    if (isLoadingRef.current) return;

    isLoadingRef.current = true;
    setIsLoading(true);

    try {
      const response = await cafeService.getCafes(search);
      if (response && response.success) {
        setCafes(response.cafes || []);
      }
    } catch (error) {
      console.error('Cafes load error:', error);
    } finally {
      setIsLoading(false);
      isLoadingRef.current = false;
    }
  };

  useEffect(() => {
    loadCafes(initialSearchQuery);
  }, [initialSearchQuery]);

  const handleSearch = () => {
    loadCafes(searchQuery.trim());
  };

  const handleSelectStore = (cafe) => {
    navigation.navigate('Menu', { cafe });
  };

  const renderCafeItem = ({ item }) => (
    <TouchableOpacity
      style={styles.cafeCard}
      onPress={() => handleSelectStore(item)}
      activeOpacity={0.8}
    >
      {item.logo_url ? (
        <Image
          source={{ uri: BASE_URL + item.logo_url }}
          style={styles.cafeLogo}
          resizeMode="cover"
        />
      ) : (
        <View style={[styles.cafeLogo, styles.cafeLogoPlaceholder]}>
          <Ionicons name="cafe-outline" size={40} color={colors.textGray} />
        </View>
      )}
      <View style={styles.cafeInfo}>
        <Text style={styles.cafeName}>{item.cafe_name}</Text>
        {item.description && (
          <Text style={styles.cafeDescription} numberOfLines={2}>
            {item.description}
          </Text>
        )}
        {item.address && (
          <View style={styles.cafeAddressContainer}>
            <Ionicons name="location-outline" size={14} color="#666666" />
            <Text style={styles.cafeAddress} numberOfLines={1}>
              {item.address}
            </Text>
          </View>
        )}
        <TouchableOpacity
          style={styles.viewCafeButton}
          onPress={() => handleSelectStore(item)}
        >
          <Text style={styles.viewCafeButtonText}>View Cafe</Text>
        </TouchableOpacity>
      </View>
    </TouchableOpacity>
  );

  return (
    <View style={styles.container}>
      <View style={styles.searchContainer}>
        <View style={styles.searchInputContainer}>
          <Ionicons name="search" size={20} color={colors.textGray} style={styles.searchIcon} />
          <TextInput
            style={styles.searchInput}
            placeholder="search cafe"
            placeholderTextColor={colors.textGray}
            value={searchQuery}
            onChangeText={setSearchQuery}
            onSubmitEditing={handleSearch}
            returnKeyType="search"
          />
        </View>
      </View>

      {isLoading ? (
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color={colors.primaryWhite} />
          <Text style={styles.loadingText}>Searching cafes...</Text>
        </View>
      ) : (
        <FlatList
          data={cafes}
          renderItem={renderCafeItem}
          keyExtractor={(item) => item.cafe_id.toString()}
          contentContainerStyle={styles.listContent}
          ListEmptyComponent={
            <View style={styles.emptyContainer}>
              <Text style={styles.emptyText}>
                {searchQuery.trim() 
                  ? `No cafes found for "${searchQuery}"` 
                  : 'No cafes available'}
              </Text>
            </View>
          }
        />
      )}
      
      {/* Bottom Navigation */}
      <BottomNav />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#FFFFFF',
  },
  searchContainer: {
    padding: spacing.md,
    backgroundColor: '#FFFFFF',
  },
  searchInputContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#E0E0E0',
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
  },
  searchIcon: {
    marginRight: spacing.sm,
  },
  searchInput: {
    flex: 1,
    color: '#000000',
    fontSize: 16,
    padding: 0,
  },
  listContent: {
    padding: spacing.md,
  },
  cafeCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    padding: spacing.md,
    marginBottom: spacing.md,
    flexDirection: 'row',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
    borderWidth: 0,
  },
  cafeLogo: {
    width: 100,
    height: 100,
    borderRadius: 8,
    marginRight: spacing.md,
  },
  cafeLogoPlaceholder: {
    backgroundColor: '#8B4513',
    justifyContent: 'center',
    alignItems: 'center',
  },
  cafeInfo: {
    flex: 1,
    justifyContent: 'space-between',
  },
  cafeName: {
    fontSize: 18,
    fontWeight: '700',
    color: '#000000',
    marginBottom: spacing.xs,
  },
  cafeDescription: {
    fontSize: 14,
    color: '#000000',
    marginBottom: spacing.xs,
    flex: 1,
  },
  cafeAddressContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: spacing.sm,
    gap: spacing.xs,
  },
  cafeAddress: {
    fontSize: 12,
    color: '#000000',
    flex: 1,
  },
  viewCafeButton: {
    backgroundColor: '#8B4513',
    paddingVertical: spacing.sm,
    paddingHorizontal: spacing.md,
    borderRadius: 8,
    alignSelf: 'flex-start',
  },
  viewCafeButtonText: {
    color: '#FFFFFF',
    fontWeight: '600',
    fontSize: 14,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: spacing.xl,
    backgroundColor: '#FFFFFF',
  },
  loadingText: {
    fontSize: 16,
    color: '#666666',
    marginTop: spacing.md,
  },
  emptyContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: spacing.xl,
    backgroundColor: '#FFFFFF',
  },
  emptyText: {
    fontSize: 16,
    color: '#666666',
    textAlign: 'center',
  },
});

