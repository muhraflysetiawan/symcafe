import React, { useEffect, useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  FlatList,
  Image,
  ActivityIndicator,
  Alert,
  Modal,
  ScrollView,
} from 'react-native';
import { cafeService } from '../services/cafeService';
import { formatCurrency } from '../utils/currency';
import { colors, spacing, typography } from '../constants/theme';
import { storage } from '../utils/storage';
import { BASE_URL } from '../config/api';
import BottomNav from '../components/BottomNav';

export default function MenuScreen({ route, navigation }) {
  const { cafe } = route.params || {};
  const [menuData, setMenuData] = useState(null);
  const [cart, setCart] = useState([]);
  const [selectedProduct, setSelectedProduct] = useState(null);
  const [selectedVariations, setSelectedVariations] = useState({});
  const [selectedAddons, setSelectedAddons] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [hasError, setHasError] = useState(false);
  const isLoadingRef = React.useRef(false);
  const hasLoadedRef = React.useRef(false);

  // Validate cafe parameter exists
  React.useEffect(() => {
    if (!cafe || !cafe.cafe_id) {
      setHasError(true);
      setIsLoading(false);
      Alert.alert('Error', 'Store information is missing. Please select a store first.', [
        {
          text: 'OK',
          onPress: () => navigation.navigate('Home', { screen: 'Stores' }),
        },
      ]);
      return;
    }
    setHasError(false);
  }, [cafe, navigation]);

  // Load menu function - for retry button
  const loadMenu = React.useCallback(async () => {
    if (!cafe || !cafe.cafe_id) {
      setIsLoading(false);
      isLoadingRef.current = false;
      return;
    }

    if (isLoadingRef.current) {
      return;
    }

    isLoadingRef.current = true;
    setIsLoading(true);
    setHasError(false);
    
    try {
      const response = await cafeService.getMenu(cafe.cafe_id);
      if (response && response.success) {
        setMenuData(response);
        setHasError(false);
        hasLoadedRef.current = true;
      } else {
        setHasError(true);
      }
    } catch (error) {
      console.error('Menu load error:', error);
      setHasError(true);
    } finally {
      setIsLoading(false);
      isLoadingRef.current = false;
    }
  }, [cafe?.cafe_id]);

  // Load cart function - filter by current cafe
  const loadCart = React.useCallback(async () => {
    try {
      const cartData = await storage.getItem('cart');
      if (cartData && Array.isArray(cartData)) {
        // Filter cart to show only items from current cafe
        // CRITICAL: Only show items that match the current cafe_id
        if (cafe && cafe.cafe_id) {
          const cafeCart = cartData.filter(item => {
            // Strict check: item must have cafe_id and it must match current cafe
            return item && item.cafe_id && item.cafe_id === cafe.cafe_id;
          });
          console.log(`[MenuScreen] Loaded cart for cafe ${cafe.cafe_id}: ${cafeCart.length} items`);
          setCart(cafeCart);
        } else {
          console.log('[MenuScreen] No cafe_id, clearing cart');
          setCart([]);
        }
      } else {
        console.log('[MenuScreen] No cart data, clearing cart');
        setCart([]);
      }
    } catch (error) {
      console.error('Cart load error:', error);
      setCart([]);
    }
  }, [cafe]);

  // Reload cart when screen comes into focus (e.g., when returning from cart/checkout)
  useEffect(() => {
    const unsubscribe = navigation.addListener('focus', () => {
      // Reload cart when screen comes into focus to ensure it's current
      if (cafe && cafe.cafe_id) {
        loadCart();
      }
    });
    
    return unsubscribe;
  }, [navigation, cafe, loadCart]);

  // Load menu when cafe is available - only once per cafe
  useEffect(() => {
    if (!cafe || !cafe.cafe_id) {
      setIsLoading(false);
      setCart([]); // Clear cart if no cafe
      return;
    }
    
    // Reset flags when cafe changes
    const currentCafeId = cafe.cafe_id;
    hasLoadedRef.current = false;
    isLoadingRef.current = false;
    // Clear cart state when switching cafes to prevent showing wrong items
    setCart([]);
    
    let isMounted = true;
    
    // Initial load - inline to avoid closure issues
    const initialize = async () => {
      if (!isMounted) return;
      
      console.log('Loading menu for cafe:', currentCafeId);
      
      // Load menu
      if (isLoadingRef.current) {
        console.log('Already loading, skipping');
        return;
      }
      
      isLoadingRef.current = true;
      setIsLoading(true);
      setHasError(false);
      
      // Safety timeout - force loading to stop after 20 seconds
      const timeoutId = setTimeout(() => {
        console.error('Menu load timeout after 20 seconds');
        if (isMounted && isLoadingRef.current) {
          isLoadingRef.current = false;
          setIsLoading(false);
          setHasError(true);
        }
      }, 20000);
      
      try {
        console.log('Fetching menu from API for cafe:', currentCafeId);
        const response = await cafeService.getMenu(currentCafeId);
        clearTimeout(timeoutId);
        
        console.log('API response received:', { 
          success: response?.success, 
          hasProducts: !!response?.products,
          productCount: response?.products?.length || 0
        });
        
        if (!isMounted) {
          console.log('Component unmounted, skipping update');
          return;
        }
        
        if (response && response.success) {
          setMenuData(response);
          setHasError(false);
          hasLoadedRef.current = true;
          console.log('Menu loaded successfully with', response.products?.length || 0, 'products');
        } else {
          console.error('API returned success=false:', response);
          setHasError(true);
        }
      } catch (error) {
        clearTimeout(timeoutId);
        console.error('Menu load error:', error);
        console.error('Error details:', error.message, error.response?.data);
        if (isMounted) {
          setHasError(true);
        }
      } finally {
        if (isMounted) {
          console.log('Setting loading to false');
          isLoadingRef.current = false;
          setIsLoading(false);
        }
      }
      
      // Load cart - use loadCart function to ensure proper filtering by cafe
      if (isMounted) {
        loadCart();
      }
    };
    
    initialize();

    return () => {
      isMounted = false;
    };
  }, [cafe?.cafe_id]); // Only depend on cafe_id

  const saveCart = async (newCart) => {
    // Ensure all items in newCart have cafe_id set to current cafe
    let validatedCart = newCart.map(item => ({
      ...item,
      cafe_id: item.cafe_id || cafe.cafe_id, // Ensure cafe_id is set
      cafe: item.cafe || cafe, // Ensure cafe object is set
    }));
    
    // Validate: All items must belong to the current cafe
    const invalidItems = validatedCart.filter(item => item.cafe_id !== cafe.cafe_id);
    if (invalidItems.length > 0) {
      console.error('[MenuScreen] ERROR: Attempted to save items from different cafe!', {
        currentCafe: cafe.cafe_id,
        invalidItems: invalidItems.map(i => ({ id: i.id, cafe_id: i.cafe_id }))
      });
      // Remove invalid items
      validatedCart = validatedCart.filter(item => item.cafe_id === cafe.cafe_id);
    }
    
    // Get existing cart and merge with new items from current cafe
    try {
      const existingCart = await storage.getItem('cart') || [];
      // Remove items from current cafe (to replace with new cart)
      // This ensures carts are separate - items from other cafes are preserved
      const otherCafeItems = existingCart.filter(item => {
        // Keep items that have a different cafe_id (strict check - no legacy items)
        return item && item.cafe_id && item.cafe_id !== cafe.cafe_id;
      });
      
      console.log(`[MenuScreen] Saving cart for cafe ${cafe.cafe_id}:`, {
        newItems: validatedCart.length,
        otherCafeItems: otherCafeItems.length,
        totalItems: otherCafeItems.length + validatedCart.length
      });
      
      // Combine with new cart items - DO NOT merge quantities across cafes
      const mergedCart = [...otherCafeItems, ...validatedCart];
      await storage.setItem('cart', mergedCart);
      // Update local state with only current cafe's items
      setCart(validatedCart);
    } catch (error) {
      console.error('Error saving cart:', error);
      // On error, save only current cafe's cart
      await storage.setItem('cart', validatedCart);
      setCart(validatedCart);
    }
  };

  const openProductModal = (product) => {
    setSelectedProduct(product);
    setSelectedVariations({});
    setSelectedAddons([]);
    
    // Set default variations
    const variations = menuData?.variations[product.item_id] || [];
    variations.forEach((variation) => {
      if (variation.is_required) {
        const defaultOption = variation.options.find(o => o.is_default) || variation.options[0];
        if (defaultOption) {
          setSelectedVariations(prev => ({
            ...prev,
            [variation.variation_id]: defaultOption,
          }));
        }
      }
    });
    
    setShowModal(true);
  };

  const calculateProductPrice = (product) => {
    let price = parseFloat(product.price);
    
    // Add variation adjustments
    Object.values(selectedVariations).forEach((variation) => {
      price += parseFloat(variation.price_adjustment || 0);
    });
    
    // Add addon prices
    selectedAddons.forEach((addon) => {
      price += parseFloat(addon.price || 0);
    });
    
    return price;
  };

  const addToCart = () => {
    if (!selectedProduct) return;
    
    // Validate required variations
    const variations = menuData?.variations[selectedProduct.item_id] || [];
    const requiredVariations = variations.filter(v => v.is_required);
    
    for (const variation of requiredVariations) {
      if (!selectedVariations[variation.variation_id]) {
        Alert.alert('Error', `Please select ${variation.variation_name}`);
        return;
      }
    }
    
    const finalPrice = calculateProductPrice(selectedProduct);
    
    // Convert variations to array format
    const variationsArray = Object.keys(selectedVariations).map(variationId => ({
      variation_id: parseInt(variationId),
      option_id: selectedVariations[variationId].option_id,
      option_name: selectedVariations[variationId].option_name,
      price_adjustment: selectedVariations[variationId].price_adjustment,
    }));
    
    // Generate cart key
    const varKeys = Object.keys(selectedVariations).sort().map(vid => `${vid}:${selectedVariations[vid].option_id}`).join(',');
    const addonKeys = selectedAddons.sort((a, b) => a.addon_id - b.addon_id).map(a => a.addon_id).join(',');
    const cartKey = `${selectedProduct.item_id}_${varKeys}_${addonKeys}`;
    
    // Check if item already in cart (only check current cafe's cart)
    // Ensure we're only checking items from the current cafe
    const currentCafeCart = cart.filter(item => item.cafe_id === cafe.cafe_id);
    const existingItem = currentCafeCart.find(item => item.cartKey === cartKey);
    
    let newCart;
    if (existingItem) {
      // Update quantity in current cafe's cart only
      newCart = cart.map(item =>
        (item.cartKey === cartKey && item.cafe_id === cafe.cafe_id)
          ? { ...item, quantity: item.quantity + 1 }
          : item
      );
    } else {
      const cartItem = {
        id: selectedProduct.item_id,
        name: selectedProduct.item_name,
        price: finalPrice,
        basePrice: selectedProduct.price,
        quantity: 1,
        variations: variationsArray,
        addons: [...selectedAddons],
        cartKey: cartKey,
        cafe_id: cafe.cafe_id, // Store cafe_id to ensure per-cafe carts
        cafe: cafe, // Store full cafe object for navigation
      };
      // Only add to current cafe's cart, preserve other cafes' carts
      newCart = [...cart, cartItem];
    }
    
    saveCart(newCart);
    setShowModal(false);
    Alert.alert('Success', 'Item added to cart');
  };

  const getCartCount = () => {
    // Only count items from the current cafe
    // Double-check: ensure we're only counting items that match the current cafe_id
    if (!cafe || !cafe.cafe_id) return 0;
    
    // Filter and count only items from current cafe
    const currentCafeItems = cart.filter(item => {
      // Strict check: item must have cafe_id and it must match current cafe
      return item && item.cafe_id && item.cafe_id === cafe.cafe_id;
    });
    
    return currentCafeItems.reduce((sum, item) => sum + (item.quantity || 0), 0);
  };

  const groupProductsByCategory = () => {
    if (!menuData) return [];
    
    const grouped = {};
    menuData.products.forEach((product) => {
      const category = product.category_name || 'General';
      if (!grouped[category]) {
        grouped[category] = [];
      }
      grouped[category].push(product);
    });
    
    return Object.entries(grouped).map(([category, products]) => ({
      category,
      products,
    }));
  };

  // Product Item Component (separate component to use hooks)
  const ProductItem = ({ item, onPress }) => {
    const [imageError, setImageError] = React.useState(false);
    const [imageLoading, setImageLoading] = React.useState(true);
    const imageUrl = item.image_url ? BASE_URL + item.image_url : null;
    
    return (
      <TouchableOpacity
        style={styles.productCard}
        onPress={() => onPress(item)}
      >
        {imageUrl && !imageError ? (
          <View style={styles.imageContainer}>
            {imageLoading && (
              <View style={styles.imageLoadingPlaceholder}>
                <Text style={styles.placeholderText}>Loading...</Text>
              </View>
            )}
            <Image 
              source={{ uri: imageUrl }} 
              style={[styles.productImage, imageLoading && styles.imageHidden]}
              resizeMode="cover"
              onError={(error) => {
                console.log('Product image load error:', error.nativeEvent.error);
                console.log('Image URL attempted:', imageUrl);
                console.log('BASE_URL:', BASE_URL);
                console.log('item.image_url:', item.image_url);
                setImageError(true);
                setImageLoading(false);
              }}
              onLoad={() => {
                console.log('Product image loaded successfully:', imageUrl);
                setImageLoading(false);
              }}
              onLoadStart={() => {
                setImageLoading(true);
              }}
            />
          </View>
        ) : (
          <View style={styles.placeholderImage}>
            <Text style={styles.placeholderText}>No Image</Text>
          </View>
        )}
        <View style={styles.productInfo}>
          <Text style={styles.productName}>{item.item_name}</Text>
          <Text style={styles.productStock}>Stock: {item.stock}</Text>
          <Text style={styles.productPrice}>{formatCurrency(item.price)}</Text>
        </View>
      </TouchableOpacity>
    );
  };

  const renderProduct = ({ item }) => (
    <ProductItem item={item} onPress={openProductModal} />
  );

  // Show error state
  if (hasError && !isLoading) {
    return (
      <View style={styles.loadingContainer}>
        <Text style={styles.errorText}>Unable to load menu</Text>
        <TouchableOpacity
          style={styles.retryButton}
          onPress={() => {
            hasLoadedRef.current = false;
            setHasError(false);
            loadMenu();
          }}
        >
          <Text style={styles.retryButtonText}>Retry</Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.retryButton, { backgroundColor: colors.accentGray, marginTop: spacing.sm }]}
          onPress={() => navigation.goBack()}
        >
          <Text style={[styles.retryButtonText, { color: colors.primaryWhite }]}>Go Back</Text>
        </TouchableOpacity>
      </View>
    );
  }

  if (!cafe || !cafe.cafe_id) {
    return (
      <View style={styles.loadingContainer}>
        <Text style={styles.errorText}>Store information missing</Text>
        <TouchableOpacity
          style={styles.retryButton}
          onPress={() => navigation.goBack()}
        >
          <Text style={styles.retryButtonText}>Go Back to Stores</Text>
        </TouchableOpacity>
      </View>
    );
  }

  if (isLoading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={colors.primaryWhite} />
        <Text style={styles.loadingText}>Loading menu...</Text>
        <Text style={[styles.loadingText, { fontSize: 12, marginTop: spacing.sm }]}>
          If this takes too long, check your connection
        </Text>
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

  if (!menuData || !menuData.products || menuData.products.length === 0) {
    return (
      <View style={styles.loadingContainer}>
        <Text style={styles.errorText}>No menu items available</Text>
        <TouchableOpacity
          style={styles.retryButton}
          onPress={loadMenu}
        >
          <Text style={styles.retryButtonText}>Retry</Text>
        </TouchableOpacity>
      </View>
    );
  }

  const categories = groupProductsByCategory();

  return (
    <View style={styles.container}>
      {/* Cafe Info */}
      <View style={styles.cafeInfo}>
        {cafe.logo_url && (
          <Image source={{ uri: BASE_URL + cafe.logo_url }} style={styles.cafeLogo} />
        )}
        <View style={styles.cafeDetails}>
          <Text style={styles.cafeName}>{cafe.cafe_name}</Text>
          {cafe.address && <Text style={styles.cafeAddress}>📍 {cafe.address}</Text>}
        </View>
      </View>

      {/* Products by Category */}
      <FlatList
        data={categories}
        keyExtractor={(item) => item.category}
        renderItem={({ item: categoryItem }) => (
          <View style={styles.categorySection}>
            <Text style={styles.categoryTitle}>{categoryItem.category}</Text>
            <FlatList
              data={categoryItem.products}
              renderItem={renderProduct}
              keyExtractor={(product) => product.item_id.toString()}
              numColumns={2}
              columnWrapperStyle={styles.productRow}
            />
          </View>
        )}
        contentContainerStyle={styles.listContent}
      />

      {/* Cart Button */}
      {getCartCount() > 0 && (
        <TouchableOpacity
          style={styles.cartButton}
          onPress={() => navigation.navigate('Cart', { cafe })}
        >
          <Text style={styles.cartButtonText}>🛒 Cart ({getCartCount()})</Text>
        </TouchableOpacity>
      )}

      {/* Product Modal */}
      <Modal
        visible={showModal}
        animationType="slide"
        transparent={true}
        onRequestClose={() => setShowModal(false)}
      >
        <View style={styles.modalContainer}>
          <View style={styles.modalContent}>
            <ScrollView>
              {selectedProduct && (
                <>
                  <Text style={styles.modalTitle}>{selectedProduct.item_name}</Text>
                  
                  {/* Variations */}
                  {menuData?.variations[selectedProduct.item_id]?.map((variation) => (
                    <View key={variation.variation_id} style={styles.variationSection}>
                      <Text style={styles.variationLabel}>
                        {variation.variation_name}
                        {variation.is_required && <Text style={styles.required}> *</Text>}
                      </Text>
                      {variation.options.map((option) => (
                        <TouchableOpacity
                          key={option.option_id}
                          style={[
                            styles.optionButton,
                            selectedVariations[variation.variation_id]?.option_id === option.option_id &&
                              styles.optionButtonSelected,
                          ]}
                          onPress={() => {
                            setSelectedVariations(prev => ({
                              ...prev,
                              [variation.variation_id]: option,
                            }));
                          }}
                        >
                          <Text style={styles.optionText}>{option.option_name}</Text>
                          {option.price_adjustment != 0 && (
                            <Text
                              style={[
                                styles.optionPrice,
                                option.price_adjustment > 0 && styles.optionPricePositive,
                              ]}
                            >
                              {option.price_adjustment > 0 ? '+' : ''}
                              {formatCurrency(option.price_adjustment)}
                            </Text>
                          )}
                        </TouchableOpacity>
                      ))}
                    </View>
                  ))}

                  {/* Add-ons */}
                  {menuData?.addons[selectedProduct.item_id]?.length > 0 && (
                    <View style={styles.addonsSection}>
                      <Text style={styles.addonsLabel}>Add-ons (Optional)</Text>
                      {menuData.addons[selectedProduct.item_id].map((addon) => (
                        <TouchableOpacity
                          key={addon.addon_id}
                          style={[
                            styles.addonButton,
                            selectedAddons.some(a => a.addon_id === addon.addon_id) &&
                              styles.addonButtonSelected,
                          ]}
                          onPress={() => {
                            const exists = selectedAddons.some(a => a.addon_id === addon.addon_id);
                            if (exists) {
                              setSelectedAddons(prev => prev.filter(a => a.addon_id !== addon.addon_id));
                            } else {
                              setSelectedAddons(prev => [...prev, addon]);
                            }
                          }}
                        >
                          <Text style={styles.addonText}>{addon.addon_name}</Text>
                          <Text style={styles.addonPrice}>{formatCurrency(addon.price)}</Text>
                        </TouchableOpacity>
                      ))}
                    </View>
                  )}

                  <View style={styles.modalTotal}>
                    <Text style={styles.totalLabel}>Total:</Text>
                    <Text style={styles.totalPrice}>
                      {formatCurrency(calculateProductPrice(selectedProduct))}
                    </Text>
                  </View>

                  <TouchableOpacity style={styles.addButton} onPress={addToCart}>
                    <Text style={styles.addButtonText}>Add to Cart</Text>
                  </TouchableOpacity>
                </>
              )}
            </ScrollView>
            <TouchableOpacity
              style={styles.closeButton}
              onPress={() => setShowModal(false)}
            >
              <Text style={styles.closeButtonText}>Close</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>
      
      {/* Bottom Navigation */}
      <BottomNav />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  cafeInfo: {
    backgroundColor: colors.primaryBrown,
    padding: spacing.lg,
    flexDirection: 'row',
    alignItems: 'center',
    borderBottomWidth: 0,
  },
  cafeLogo: {
    width: 70,
    height: 70,
    borderRadius: 12,
    marginRight: spacing.md,
    backgroundColor: 'rgba(255, 255, 255, 0.2)',
  },
  cafeDetails: {
    flex: 1,
  },
  cafeName: {
    fontSize: 22,
    fontWeight: '700',
    color: colors.primaryWhite,
    marginBottom: spacing.xs,
  },
  cafeAddress: {
    fontSize: 14,
    color: colors.primaryWhite,
    opacity: 0.9,
  },
  listContent: {
    padding: spacing.md,
    paddingBottom: 160, // Extra padding for cart button (80px) + bottom nav (60px) + spacing (20px)
  },
  categorySection: {
    marginBottom: spacing.xl,
  },
  categoryTitle: {
    fontSize: 22,
    fontWeight: '700',
    color: colors.textPrimary,
    marginBottom: spacing.md,
    paddingBottom: spacing.sm,
    borderBottomWidth: 2,
    borderBottomColor: colors.primaryBrown,
  },
  productRow: {
    justifyContent: 'space-between',
  },
  productCard: {
    backgroundColor: colors.cardBackground,
    borderRadius: 12,
    width: '48%',
    marginBottom: spacing.md,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: colors.mediumGray,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  imageContainer: {
    width: '100%',
    height: 160,
    position: 'relative',
    backgroundColor: colors.lightGray,
  },
  productImage: {
    width: '100%',
    height: 160,
    backgroundColor: 'transparent',
  },
  imageHidden: {
    opacity: 0,
  },
  imageLoadingPlaceholder: {
    position: 'absolute',
    width: '100%',
    height: 160,
    backgroundColor: colors.lightGray,
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 1,
  },
  placeholderImage: {
    width: '100%',
    height: 160,
    backgroundColor: colors.lightGray,
    justifyContent: 'center',
    alignItems: 'center',
  },
  placeholderText: {
    color: colors.textSecondary,
    fontSize: 12,
  },
  productInfo: {
    padding: spacing.md,
  },
  productName: {
    fontSize: 16,
    fontWeight: '700',
    color: colors.textPrimary,
    marginBottom: spacing.xs,
  },
  productStock: {
    fontSize: 12,
    color: colors.textSecondary,
    marginBottom: spacing.xs,
  },
  productPrice: {
    fontSize: 18,
    color: colors.primaryBrown,
    fontWeight: '700',
  },
  cartButton: {
    position: 'absolute',
    bottom: 80, // Position above bottom nav (60px height + 20px spacing)
    right: spacing.md,
    backgroundColor: colors.primaryBrown,
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.lg,
    borderRadius: 25,
    minWidth: 120,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.25,
    shadowRadius: 4,
    elevation: 5,
    zIndex: 10,
  },
  cartButtonText: {
    color: colors.primaryWhite,
    fontWeight: '700',
    fontSize: 16,
  },
  modalContainer: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.8)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: spacing.md,
  },
  modalContent: {
    backgroundColor: colors.cardBackground,
    borderRadius: 16,
    padding: spacing.lg,
    maxHeight: '80%',
    width: '90%',
    width: '100%',
  },
  modalTitle: {
    fontSize: 24,
    fontWeight: '700',
    color: colors.textPrimary,
    marginBottom: spacing.lg,
    textAlign: 'center',
  },
  variationSection: {
    marginBottom: spacing.lg,
  },
  variationLabel: {
    fontSize: 16,
    fontWeight: '700',
    color: colors.textPrimary,
    marginBottom: spacing.md,
  },
  required: {
    color: colors.warning,
  },
  optionButton: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: colors.lightGray,
    padding: spacing.md,
    borderRadius: 8,
    marginBottom: spacing.sm,
    borderWidth: 2,
    borderColor: colors.mediumGray,
  },
  optionButtonSelected: {
    borderColor: colors.primaryBrown,
    backgroundColor: '#FFF8F0',
  },
  optionText: {
    fontSize: 16,
    color: colors.textPrimary,
    flex: 1,
    fontWeight: '500',
  },
  optionPrice: {
    fontSize: 14,
    color: colors.error,
    fontWeight: '600',
  },
  optionPricePositive: {
    color: colors.success,
    fontWeight: '600',
  },
  addonsSection: {
    marginBottom: spacing.md,
  },
  addonsLabel: {
    fontSize: 16,
    fontWeight: '700',
    color: colors.textPrimary,
    marginBottom: spacing.md,
  },
  addonButton: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    backgroundColor: colors.lightGray,
    padding: spacing.md,
    borderRadius: 8,
    marginBottom: spacing.sm,
    borderWidth: 2,
    borderColor: colors.mediumGray,
    alignItems: 'center',
  },
  addonButtonSelected: {
    borderColor: colors.primaryBrown,
    backgroundColor: '#FFF8F0',
  },
  addonText: {
    fontSize: 16,
    color: colors.textPrimary,
    flex: 1,
    fontWeight: '500',
  },
  addonPrice: {
    fontSize: 16,
    color: colors.primaryBrown,
    fontWeight: '700',
  },
  modalTotal: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingTop: spacing.lg,
    marginTop: spacing.lg,
    borderTopWidth: 2,
    borderTopColor: colors.mediumGray,
  },
  totalLabel: {
    fontSize: 20,
    fontWeight: '700',
    color: colors.textPrimary,
  },
  totalPrice: {
    fontSize: 24,
    fontWeight: '700',
    color: colors.primaryBrown,
  },
  addButton: {
    backgroundColor: colors.primaryBrown,
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.lg,
    borderRadius: 12,
    alignItems: 'center',
    marginTop: spacing.lg,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.25,
    shadowRadius: 4,
    elevation: 5,
  },
  addButtonText: {
    color: colors.primaryWhite,
    fontWeight: '700',
    fontSize: 18,
  },
  closeButton: {
    backgroundColor: colors.mediumGray,
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.lg,
    borderRadius: 12,
    alignItems: 'center',
    marginTop: spacing.md,
  },
  closeButtonText: {
    fontSize: 16,
    color: colors.primaryWhite,
    fontWeight: '600',
  },
});

