import React, { useEffect, useState, useRef } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  FlatList,
  Image,
  ActivityIndicator,
  Alert,
  TextInput,
  ScrollView,
  Dimensions,
} from 'react-native';
import { Ionicons, MaterialIcons } from '@expo/vector-icons';
import { cafeService } from '../services/cafeService';
import { useAuth } from '../context/AuthContext';
import { colors, spacing, typography } from '../constants/theme';
import { BASE_URL, API_BASE_URL, API_ENDPOINTS } from '../config/api';
import { storage } from '../utils/storage';

const { width: SCREEN_WIDTH } = Dimensions.get('window');
const BANNER_HEIGHT = 200;
const AUTO_SCROLL_INTERVAL = 5000; // 5 seconds

export default function StoreSelectionScreen({ navigation }) {
  const [cafes, setCafes] = useState([]);
  const [banners, setBanners] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [hasError, setHasError] = useState(false);
  const [truncatedDescriptions, setTruncatedDescriptions] = useState({});
  const [searchQuery, setSearchQuery] = useState('');
  const [notificationCount, setNotificationCount] = useState(0);
  const [currentBannerIndex, setCurrentBannerIndex] = useState(0);
  const scrollViewRef = useRef(null);
  const autoScrollTimerRef = useRef(null);
  const isLoadingRef = useRef(false);
  const { user, logout } = useAuth();
  const userName = user?.name || '';

  // Load promotional banners
  const loadBanners = async () => {
    try {
      const response = await cafeService.getPromotionalBanners();
      if (response && response.success) {
        setBanners(response.banners || []);
      }
    } catch (error) {
      console.error('Error loading banners:', error);
      setBanners([]);
    }
  };

  // Load cafes
  const loadCafes = React.useCallback(async (search = '') => {
    if (isLoadingRef.current) {
      console.log('Already loading cafes, skipping');
      return;
    }

    isLoadingRef.current = true;
    setIsLoading(true);
    setHasError(false);
    
    console.log('Loading cafes from API...', search ? `with search: ${search}` : '');
    
    const timeoutId = setTimeout(() => {
      console.error('Cafes load timeout after 20 seconds');
      if (isLoadingRef.current) {
        isLoadingRef.current = false;
        setIsLoading(false);
        setHasError(true);
      }
    }, 20000);
    
    try {
      const response = await cafeService.getCafes(search);
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
      console.error('Error message:', error.message);
      console.error('Error response:', error.response?.data);
      console.error('Error status:', error.response?.status);
      console.error('API URL:', API_BASE_URL + API_ENDPOINTS.CAFES);
      setHasError(true);
    } finally {
      setIsLoading(false);
      isLoadingRef.current = false;
      console.log('Cafes loading finished');
    }
  }, []);

  // Auto-scroll banner
  useEffect(() => {
    if (banners.length > 1) {
      autoScrollTimerRef.current = setInterval(() => {
        setCurrentBannerIndex((prevIndex) => {
          const nextIndex = (prevIndex + 1) % banners.length;
          if (scrollViewRef.current) {
            scrollViewRef.current.scrollTo({
              x: nextIndex * SCREEN_WIDTH,
              animated: true,
            });
          }
          return nextIndex;
        });
      }, AUTO_SCROLL_INTERVAL);
    }

    return () => {
      if (autoScrollTimerRef.current) {
        clearInterval(autoScrollTimerRef.current);
      }
    };
  }, [banners.length]);

  // Initial load
  useEffect(() => {
    loadCafes();
    loadBanners();
    
    // Listen for focus events if needed
    const unsubscribe = navigation.addListener('focus', () => {
      // Reload data if needed
    });

    return unsubscribe;
  }, []);

  // Handle banner scroll
  const handleBannerScroll = (event) => {
    const offsetX = event.nativeEvent.contentOffset.x;
    const index = Math.round(offsetX / SCREEN_WIDTH);
    if (index !== currentBannerIndex) {
      setCurrentBannerIndex(index);
      // Reset auto-scroll timer
      if (autoScrollTimerRef.current) {
        clearInterval(autoScrollTimerRef.current);
      }
      autoScrollTimerRef.current = setInterval(() => {
        setCurrentBannerIndex((prevIndex) => {
          const nextIndex = (prevIndex + 1) % banners.length;
          if (scrollViewRef.current) {
            scrollViewRef.current.scrollTo({
              x: nextIndex * SCREEN_WIDTH,
              animated: true,
            });
          }
          return nextIndex;
        });
      }, AUTO_SCROLL_INTERVAL);
    }
  };

  // Navigate to previous banner
  const goToPreviousBanner = () => {
    const prevIndex = currentBannerIndex === 0 ? banners.length - 1 : currentBannerIndex - 1;
    setCurrentBannerIndex(prevIndex);
    if (scrollViewRef.current) {
      scrollViewRef.current.scrollTo({
        x: prevIndex * SCREEN_WIDTH,
        animated: true,
      });
    }
  };

  // Navigate to next banner
  const goToNextBanner = () => {
    const nextIndex = (currentBannerIndex + 1) % banners.length;
    setCurrentBannerIndex(nextIndex);
    if (scrollViewRef.current) {
      scrollViewRef.current.scrollTo({
        x: nextIndex * SCREEN_WIDTH,
        animated: true,
      });
    }
  };

  // Handle search
  const handleSearch = () => {
    if (searchQuery.trim()) {
      // Navigate to cafe list with search query
      navigation.navigate('CafeList', { searchQuery: searchQuery.trim() });
    }
  };

  // Handle select store
  const handleSelectStore = (cafe) => {
    navigation.navigate('Menu', { cafe });
  };

  // Handle notifications press
  const handleNotificationsPress = () => {
    Alert.alert('Notifications', 'No new notifications');
  };

  // Handle logout
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
          },
        },
      ]
    );
  };

  // Render banner item
  const renderBanner = (banner, index) => (
    <TouchableOpacity
      key={banner.banner_id || index}
      style={styles.bannerItem}
      activeOpacity={0.9}
      onPress={() => {
        if (banner.banner_link && banner.banner_link !== '#') {
          // Handle banner link if needed
        }
      }}
    >
      <Image
        source={{ uri: banner.banner_image_url ? BASE_URL + banner.banner_image_url : BASE_URL + 'assets/bg.jpg' }}
        style={styles.bannerImage}
        resizeMode="cover"
      />
      {banner.banner_title && (
        <View style={styles.bannerOverlay}>
          <Text style={styles.bannerTitle}>{banner.banner_title}</Text>
        </View>
      )}
    </TouchableOpacity>
  );

  // Render cafe card
  const renderCafeItem = ({ item }) => (
    <TouchableOpacity
      style={styles.cafeCard}
      onPress={() => handleSelectStore(item)}
      activeOpacity={0.8}
    >
      <View style={styles.cafeLogoContainer}>
        {item.logo_url ? (
          <Image
            source={{ uri: BASE_URL + item.logo_url }}
            style={styles.cafeLogo}
            resizeMode="cover"
            onError={(error) => {
              console.log('Image load error:', error.nativeEvent.error);
              console.log('Image URL:', BASE_URL + item.logo_url);
            }}
          />
        ) : (
          <View style={[styles.cafeLogo, styles.cafeLogoPlaceholder]}>
            <Ionicons name="cafe-outline" size={40} color={colors.primaryWhite} />
          </View>
        )}
      </View>
      <View style={styles.cafeInfo}>
        <View style={styles.cafeHeader}>
          <Text style={styles.cafeName}>{item.cafe_name}</Text>
        </View>
        {item.description && (
          <View style={styles.descriptionContainer}>
            <Text 
              style={styles.cafeDescription} 
              numberOfLines={1}
              ellipsizeMode="clip"
              onTextLayout={(event) => {
                const { lines } = event.nativeEvent;
                // Check if text was truncated by comparing rendered text length with original
                if (lines.length > 0) {
                  const renderedText = lines.map(line => line.text).join('');
                  if (renderedText.length < item.description.length) {
                    setTruncatedDescriptions(prev => ({
                      ...prev,
                      [item.cafe_id]: true
                    }));
                  }
                }
              }}
            >
              {item.description}
            </Text>
            {truncatedDescriptions[item.cafe_id] && (
              <Text style={styles.moreText}>...more</Text>
            )}
          </View>
        )}
        {item.address && (
          <View style={styles.cafeAddressContainer}>
            <Ionicons name="location-outline" size={14} color="#666666" />
            <Text style={styles.cafeAddress} numberOfLines={1}>
              {item.address}
            </Text>
          </View>
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

  return (
    <View style={styles.container}>
      {/* Colored Header with Curved Bottom */}
      <View style={styles.coloredHeader}>
        <View style={styles.headerContent}>
          <View style={styles.headerLeft}>
            <Text style={styles.headerTitle}>Symcafe</Text>
          </View>
          <View style={styles.headerRight}>
            <TouchableOpacity
              style={styles.headerButton}
              onPress={handleNotificationsPress}
            >
              <Ionicons name="notifications-outline" size={24} color={colors.primaryWhite} />
              {notificationCount > 0 && (
                <View style={styles.badge}>
                  <Text style={styles.badgeText}>{notificationCount}</Text>
                </View>
              )}
            </TouchableOpacity>
          </View>
        </View>
        <View style={styles.headerCurve} />
      </View>

      {/* Welcome Section */}
      <View style={styles.welcomeContainer}>
        <Text style={styles.welcomeGreeting}>
          Hi, {userName || 'Guest'}
        </Text>
        <Text style={styles.welcomeTitle}>Welcome to Symcafe Mobile</Text>
      </View>

      {/* Search Bar */}
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

      <ScrollView
        style={styles.scrollView}
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        {/* Promotional Banner Carousel */}
        {banners.length > 0 && (
          <View style={styles.bannerContainer}>
            <ScrollView
              ref={scrollViewRef}
              horizontal
              pagingEnabled
              showsHorizontalScrollIndicator={false}
              onMomentumScrollEnd={handleBannerScroll}
              style={styles.bannerScrollView}
            >
              {banners.map((banner, index) => renderBanner(banner, index))}
            </ScrollView>
            
            {/* Banner Navigation Buttons */}
            {banners.length > 1 && (
              <>
                <TouchableOpacity
                  style={[styles.bannerNavButton, styles.bannerNavButtonLeft]}
                  onPress={goToPreviousBanner}
                >
                  <Ionicons name="chevron-back" size={24} color={colors.primaryWhite} />
                </TouchableOpacity>
                <TouchableOpacity
                  style={[styles.bannerNavButton, styles.bannerNavButtonRight]}
                  onPress={goToNextBanner}
                >
                  <Ionicons name="chevron-forward" size={24} color={colors.primaryWhite} />
                </TouchableOpacity>
                
                {/* Banner Indicators */}
                <View style={styles.bannerIndicators}>
                  {banners.map((_, index) => (
                    <View
                      key={index}
                      style={[
                        styles.bannerIndicator,
                        index === currentBannerIndex && styles.bannerIndicatorActive,
                      ]}
                    />
                  ))}
                </View>
              </>
            )}
          </View>
        )}

        {/* Explore Cafes Section */}
        <View style={styles.exploreSection}>
          <View style={styles.sectionTitleContainer}>
            <Text style={styles.sectionTitle}>Explore Cafe</Text>
            <TouchableOpacity onPress={() => navigation.navigate('CafeList', { searchQuery: '' })}>
              <Text style={styles.moreLink}>more</Text>
            </TouchableOpacity>
          </View>
          {isLoading ? (
            <View style={styles.loadingContainer}>
              <ActivityIndicator size="large" color={colors.primaryWhite} />
              <Text style={styles.loadingText}>Loading cafes...</Text>
            </View>
          ) : cafes.length === 0 ? (
            <View style={styles.emptyContainer}>
              <Text style={styles.emptyText}>No cafes available</Text>
            </View>
          ) : (
            <FlatList
              data={cafes}
              renderItem={renderCafeItem}
              keyExtractor={(item) => item.cafe_id.toString()}
              scrollEnabled={false}
              contentContainerStyle={styles.cafeList}
            />
          )}
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#FFFFFF',
  },
  coloredHeader: {
    backgroundColor: '#8B4513', // Dark orange-brown color
    paddingTop: spacing.lg,
    paddingBottom: spacing.xl,
    paddingHorizontal: spacing.md,
    position: 'relative',
  },
  headerContent: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    zIndex: 1,
  },
  headerLeft: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  headerTitle: {
    fontSize: 24,
    fontWeight: '700',
    color: colors.primaryWhite,
  },
  logo: {
    width: 40,
    height: 40,
  },
  headerRight: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
  },
  headerButton: {
    position: 'relative',
    padding: spacing.xs,
  },
  badge: {
    position: 'absolute',
    top: -2,
    right: -2,
    backgroundColor: colors.error,
    borderRadius: 10,
    minWidth: 20,
    height: 20,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 4,
  },
  badgeText: {
    color: colors.primaryWhite,
    fontSize: 10,
    fontWeight: '600',
  },
  headerCurve: {
    position: 'absolute',
    bottom: -20,
    left: 0,
    right: 0,
    height: 30,
    backgroundColor: '#FFFFFF',
    borderTopLeftRadius: 25,
    borderTopRightRadius: 25,
  },
  welcomeContainer: {
    paddingHorizontal: spacing.md,
    paddingTop: spacing.lg,
    paddingBottom: spacing.sm,
    backgroundColor: '#FFFFFF',
  },
  welcomeGreeting: {
    fontSize: 18,
    fontWeight: '600',
    color: '#000000',
    marginBottom: spacing.xs,
  },
  welcomeTitle: {
    fontSize: 16,
    fontWeight: '400',
    color: '#666666',
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
  scrollView: {
    flex: 1,
    backgroundColor: '#FFFFFF',
  },
  scrollContent: {
    paddingBottom: spacing.xl,
    backgroundColor: '#FFFFFF',
  },
  bannerContainer: {
    height: BANNER_HEIGHT,
    marginHorizontal: spacing.md,
    marginVertical: spacing.md,
    borderRadius: 12,
    overflow: 'hidden',
    backgroundColor: '#F5F5F5',
    position: 'relative',
  },
  bannerScrollView: {
    height: BANNER_HEIGHT,
  },
  bannerItem: {
    width: SCREEN_WIDTH,
    height: BANNER_HEIGHT,
    position: 'relative',
  },
  bannerImage: {
    width: '100%',
    height: '100%',
  },
  bannerOverlay: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    padding: spacing.md,
  },
  bannerTitle: {
    ...typography.h3,
    color: colors.primaryWhite,
    textAlign: 'center',
  },
  bannerNavButton: {
    position: 'absolute',
    top: '50%',
    transform: [{ translateY: -20 }],
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    width: 40,
    height: 40,
    borderRadius: 20,
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 10,
  },
  bannerNavButtonLeft: {
    left: spacing.md,
  },
  bannerNavButtonRight: {
    right: spacing.md,
  },
  bannerIndicators: {
    position: 'absolute',
    bottom: spacing.sm,
    left: 0,
    right: 0,
    flexDirection: 'row',
    justifyContent: 'center',
    gap: spacing.xs,
  },
  bannerIndicator: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: 'rgba(255, 255, 255, 0.5)',
  },
  bannerIndicatorActive: {
    backgroundColor: colors.primaryWhite,
    width: 24,
  },
  exploreSection: {
    padding: spacing.md,
    backgroundColor: '#FFFFFF',
  },
  sectionTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: '#000000',
    marginBottom: spacing.md,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  cafeList: {
    gap: spacing.md,
  },
  sectionTitleContainer: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: spacing.md,
  },
  moreLink: {
    fontSize: 14,
    color: '#000000',
    fontWeight: '400',
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
    borderWidth: 1,
    borderColor: '#F0F0F0',
    minHeight: 120, // Minimum card height
  },
  cafeLogoContainer: {
    marginRight: spacing.md,
    justifyContent: 'center',
    alignItems: 'center',
    width: (SCREEN_WIDTH - (spacing.md * 4)) * 0.28, // ~28% of available card width
    aspectRatio: 1, // Square logo box
  },
  cafeLogo: {
    width: '100%',
    height: '100%',
    borderRadius: 8,
    backgroundColor: '#F5F5F5',
  },
  cafeLogoPlaceholder: {
    backgroundColor: '#8B4513',
    justifyContent: 'center',
    alignItems: 'center',
  },
  cafeInfo: {
    flex: 1,
    justifyContent: 'space-between',
    paddingVertical: spacing.xs,
  },
  cafeHeader: {
    marginBottom: spacing.xs,
  },
  cafeName: {
    fontSize: 18,
    fontWeight: '700',
    color: '#000000',
    marginBottom: spacing.xs,
  },
  descriptionContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: spacing.sm,
    flexWrap: 'wrap',
  },
  cafeDescription: {
    fontSize: 14,
    color: '#666666',
    lineHeight: 20,
    flex: 1,
  },
  moreText: {
    fontSize: 14,
    color: '#8B4513',
    fontWeight: '600',
    marginLeft: 4,
  },
  cafeAddressContainer: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    marginBottom: spacing.md,
    gap: spacing.xs,
  },
  cafeAddress: {
    fontSize: 13,
    color: '#666666',
    flex: 1,
    lineHeight: 18,
  },
  loadingContainer: {
    padding: spacing.xl,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#FFFFFF',
  },
  loadingText: {
    fontSize: 16,
    color: '#666666',
    marginTop: spacing.md,
  },
  emptyContainer: {
    padding: spacing.xl,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#FFFFFF',
  },
  emptyText: {
    fontSize: 16,
    color: '#666666',
  },
  errorText: {
    fontSize: 20,
    fontWeight: '600',
    color: colors.error || '#ff4444',
    marginBottom: spacing.md,
    textAlign: 'center',
  },
  retryButton: {
    backgroundColor: '#8B4513',
    padding: spacing.md,
    borderRadius: 8,
    marginTop: spacing.md,
    minWidth: 150,
    alignItems: 'center',
  },
  retryButtonText: {
    color: '#FFFFFF',
    fontWeight: '600',
  },
});
