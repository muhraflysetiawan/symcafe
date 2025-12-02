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

  // Load cart function
  const loadCart = React.useCallback(async () => {
    try {
      const cartData = await storage.getItem('cart');
      if (cartData && Array.isArray(cartData)) {
        setCart(cartData);
      } else {
        setCart([]);
      }
    } catch (error) {
      console.error('Cart load error:', error);
      setCart([]);
    }
  }, []);

  // Load menu when cafe is available - only once per cafe
  useEffect(() => {
    if (!cafe || !cafe.cafe_id) {
      setIsLoading(false);
      return;
    }
    
    // Reset flags when cafe changes
    const currentCafeId = cafe.cafe_id;
    hasLoadedRef.current = false;
    isLoadingRef.current = false;
    
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
      
      // Load cart
      if (isMounted) {
        try {
          const cartData = await storage.getItem('cart');
          if (cartData && Array.isArray(cartData)) {
            setCart(cartData);
          } else {
            setCart([]);
          }
        } catch (error) {
          console.error('Cart load error:', error);
          setCart([]);
        }
      }
    };
    
    initialize();

    return () => {
      isMounted = false;
    };
  }, [cafe?.cafe_id]); // Only depend on cafe_id

  const saveCart = async (newCart) => {
    await storage.setItem('cart', newCart);
    setCart(newCart);
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
    
    // Check if item already in cart
    const existingItem = cart.find(item => item.cartKey === cartKey);
    
    let newCart;
    if (existingItem) {
      newCart = cart.map(item =>
        item.cartKey === cartKey
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
      };
      newCart = [...cart, cartItem];
    }
    
    saveCart(newCart);
    setShowModal(false);
    Alert.alert('Success', 'Item added to cart');
  };

  const getCartCount = () => {
    return cart.reduce((sum, item) => sum + item.quantity, 0);
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

  const renderProduct = ({ item }) => (
    <TouchableOpacity
      style={styles.productCard}
      onPress={() => openProductModal(item)}
    >
      {item.image_url ? (
        <Image source={{ uri: item.image_url }} style={styles.productImage} />
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
          <Image source={{ uri: cafe.logo_url }} style={styles.cafeLogo} />
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
  },
  cafeInfo: {
    backgroundColor: colors.accentGray,
    padding: spacing.md,
    flexDirection: 'row',
    alignItems: 'center',
    borderBottomWidth: 1,
    borderBottomColor: colors.borderGray,
  },
  cafeLogo: {
    width: 60,
    height: 60,
    borderRadius: 8,
    marginRight: spacing.md,
  },
  cafeDetails: {
    flex: 1,
  },
  cafeName: {
    ...typography.h3,
    marginBottom: spacing.xs,
  },
  cafeAddress: {
    ...typography.caption,
  },
  listContent: {
    padding: spacing.md,
  },
  categorySection: {
    marginBottom: spacing.xl,
  },
  categoryTitle: {
    ...typography.h3,
    marginBottom: spacing.md,
    paddingBottom: spacing.sm,
    borderBottomWidth: 2,
    borderBottomColor: colors.borderGray,
  },
  productRow: {
    justifyContent: 'space-between',
  },
  productCard: {
    backgroundColor: colors.accentGray,
    borderRadius: 8,
    width: '48%',
    marginBottom: spacing.md,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: colors.borderGray,
  },
  productImage: {
    width: '100%',
    height: 150,
    backgroundColor: colors.primaryBlack,
  },
  placeholderImage: {
    width: '100%',
    height: 150,
    backgroundColor: colors.primaryBlack,
    justifyContent: 'center',
    alignItems: 'center',
  },
  placeholderText: {
    color: colors.textGray,
  },
  productInfo: {
    padding: spacing.sm,
  },
  productName: {
    ...typography.body,
    fontWeight: '600',
    marginBottom: spacing.xs,
  },
  productStock: {
    ...typography.small,
    marginBottom: spacing.xs,
  },
  productPrice: {
    ...typography.body,
    color: colors.success,
    fontWeight: 'bold',
  },
  cartButton: {
    position: 'absolute',
    bottom: spacing.md,
    right: spacing.md,
    backgroundColor: colors.primaryWhite,
    padding: spacing.md,
    borderRadius: 25,
    minWidth: 120,
    alignItems: 'center',
  },
  cartButtonText: {
    color: colors.primaryBlack,
    fontWeight: '600',
  },
  modalContainer: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.8)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: spacing.md,
  },
  modalContent: {
    backgroundColor: colors.primaryBlack,
    borderRadius: 10,
    padding: spacing.md,
    maxHeight: '80%',
    width: '100%',
  },
  modalTitle: {
    ...typography.h2,
    marginBottom: spacing.md,
  },
  variationSection: {
    marginBottom: spacing.md,
  },
  variationLabel: {
    ...typography.body,
    fontWeight: '600',
    marginBottom: spacing.sm,
  },
  required: {
    color: colors.warning,
  },
  optionButton: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: colors.accentGray,
    padding: spacing.sm,
    borderRadius: 5,
    marginBottom: spacing.xs,
    borderWidth: 2,
    borderColor: 'transparent',
  },
  optionButtonSelected: {
    borderColor: colors.primaryWhite,
  },
  optionText: {
    ...typography.body,
    flex: 1,
  },
  optionPrice: {
    ...typography.caption,
    color: colors.error,
  },
  optionPricePositive: {
    color: colors.success,
  },
  addonsSection: {
    marginBottom: spacing.md,
  },
  addonsLabel: {
    ...typography.body,
    fontWeight: '600',
    marginBottom: spacing.sm,
  },
  addonButton: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    backgroundColor: colors.accentGray,
    padding: spacing.sm,
    borderRadius: 5,
    marginBottom: spacing.xs,
    borderWidth: 2,
    borderColor: 'transparent',
  },
  addonButtonSelected: {
    borderColor: colors.primaryWhite,
  },
  addonText: {
    ...typography.body,
    flex: 1,
  },
  addonPrice: {
    ...typography.body,
    fontWeight: '600',
  },
  modalTotal: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingTop: spacing.md,
    marginTop: spacing.md,
    borderTopWidth: 1,
    borderTopColor: colors.borderGray,
  },
  totalLabel: {
    ...typography.h3,
  },
  totalPrice: {
    ...typography.h2,
    color: colors.success,
  },
  addButton: {
    backgroundColor: colors.primaryWhite,
    padding: spacing.md,
    borderRadius: 5,
    alignItems: 'center',
    marginTop: spacing.md,
  },
  addButtonText: {
    color: colors.primaryBlack,
    fontWeight: '600',
    fontSize: 16,
  },
  closeButton: {
    backgroundColor: colors.accentGray,
    padding: spacing.md,
    borderRadius: 5,
    alignItems: 'center',
    marginTop: spacing.sm,
  },
  closeButtonText: {
    ...typography.body,
    color: colors.primaryWhite,
  },
});

