<?php

// AJAX handler to set user location
add_action('wp_ajax_set_user_location', 'set_user_location');
add_action('wp_ajax_nopriv_set_user_location', 'set_user_location');

function set_user_location() {
    // Verify nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'set_user_location_nonce')) {
        wp_send_json_error('Security check failed');
    }

    if (empty($_POST['location'])) {
        wp_send_json_error('No location provided');
    }

    $location = sanitize_text_field($_POST['location']);

    if (!in_array($location, ['bd', 'au'], true)) {
        wp_send_json_error('Invalid location');
    }

    // Initialize WooCommerce if available
    if (function_exists('WC')) {
        if (!WC()->session) {
            return wp_send_json_error('WooCommerce session not available');
        }
        
        // Ensure session is started
        if (!WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
        }

        $current = WC()->session->get('user_location');
        
        // Clear cart only if location actually changes
        if ($current && $current !== $location && WC()->cart) {
            WC()->cart->empty_cart();
        }
        
        WC()->session->set('user_location', $location);
    }

    // Set cookie - force immediate availability
    $_COOKIE['user_location'] = $location;
    setcookie(
        'user_location',
        $location,
        time() + (30 * DAY_IN_SECONDS),
        '/',
        '',
        false,
        true
    );

    wp_send_json_success(['location' => $location]);
}

// Get user location from session or cookie - IMPROVED
function get_user_location() {
    static $location = null;
    
    // Return cached location if already determined
    if ($location !== null) {
        return $location;
    }
    
    // Force BD location for debugging - remove this line after testing
    // return 'bd';
    
    // Try WooCommerce session first
    if (function_exists('WC')) {
        // Initialize WooCommerce session if not already
        if (!did_action('woocommerce_init')) {
            if (function_exists('wc_load_cart')) {
                wc_load_cart();
            }
        }
        
        if (WC()->session) {
            $session_location = WC()->session->get('user_location');
            if ($session_location && in_array($session_location, ['bd', 'au'], true)) {
                $location = $session_location;
                return $location;
            }
        }
    }

    // Try cookie
    if (isset($_COOKIE['user_location']) && in_array($_COOKIE['user_location'], ['bd', 'au'], true)) {
        $location = sanitize_text_field($_COOKIE['user_location']);
        return $location;
    }

    // Default to BD
    $location = 'bd';
    return $location;
}

// Set default user location cookie on init if not set
add_action('init', function() {
    if (!isset($_COOKIE['user_location'])) {
        $_COOKIE['user_location'] = 'bd';
        setcookie(
            'user_location',
            'bd',
            time() + (30 * DAY_IN_SECONDS),
            '/',
            '',
            false,
            true
        );
    }
    
    // Initialize WooCommerce session early
    if (function_exists('WC') && !WC()->session) {
        if (function_exists('wc_load_cart')) {
            wc_load_cart();
        }
    }
});

// Dynamic Product Pricing Based on User Location
add_filter('woocommerce_product_get_price', 'location_based_price', 99, 2);
add_filter('woocommerce_product_get_regular_price', 'location_based_price', 99, 2);
add_filter('woocommerce_product_get_sale_price', 'location_based_price', 99, 2);
add_filter('woocommerce_product_variation_get_price', 'location_based_price', 99, 2);
add_filter('woocommerce_product_variation_get_regular_price', 'location_based_price', 99, 2);
add_filter('woocommerce_product_variation_get_sale_price', 'location_based_price', 99, 2);

function location_based_price($price, $product) {
    // If price is already filtered or empty, return original
    if (doing_filter('woocommerce_product_variation_get_price') && has_filter('woocommerce_product_variation_get_price', 'location_based_price')) {
        return $price;
    }
    
    $location = get_user_location();
    $meta_key = ($location === 'au') ? 'au_price' : 'bd_price';
    
    $product_id = $product->get_id();
    $custom_price = get_post_meta($product_id, $meta_key, true);
    
    // Debug log
    if (current_user_can('administrator') && defined('WP_DEBUG') && WP_DEBUG) {
        error_log("Product ID: {$product_id}, Location: {$location}, Meta Key: {$meta_key}, Custom Price: {$custom_price}, Original Price: {$price}");
    }
    
    // Only return custom price if it's set and valid
    if ($custom_price !== '' && is_numeric($custom_price) && $custom_price > 0) {
        return (float) $custom_price;
    }
    
    return $price;
}

// Handle variation prices
add_filter('woocommerce_variation_prices_price', 'location_based_variation_prices', 99, 3);
add_filter('woocommerce_variation_prices_regular_price', 'location_based_variation_prices', 99, 3);

function location_based_variation_prices($price, $variation, $product) {
    $location = get_user_location();
    $meta_key = ($location === 'au') ? 'au_price' : 'bd_price';
    
    $variation_id = $variation->get_id();
    $custom_price = get_post_meta($variation_id, $meta_key, true);
    
    if ($custom_price !== '' && is_numeric($custom_price) && $custom_price > 0) {
        return (float) $custom_price;
    }
    
    return $price;
}

// Dynamic Product Stock Switching Based on User Location
add_filter('woocommerce_product_get_stock_quantity', 'location_based_stock', 99, 2);
add_filter('woocommerce_product_variation_get_stock_quantity', 'location_based_stock', 99, 2);

function location_based_stock($stock, $product) {
    $location = get_user_location();
    $meta_key = ($location === 'au') ? 'au_stock' : 'bd_stock';
    
    $custom_stock = get_post_meta($product->get_id(), $meta_key, true);
    
    if ($custom_stock !== '' && is_numeric($custom_stock)) {
        return (int) $custom_stock;
    }
    
    return $stock;
}

// Stock status filter
add_filter('woocommerce_product_get_stock_status', 'location_based_stock_status', 99, 2);
add_filter('woocommerce_product_variation_get_stock_status', 'location_based_stock_status', 99, 2);

function location_based_stock_status($status, $product) {
    $location = get_user_location();
    $meta_key = ($location === 'au') ? 'au_stock' : 'bd_stock';
    
    $stock = (int) get_post_meta($product->get_id(), $meta_key, true);
    
    // Debug log
    if (current_user_can('administrator') && defined('WP_DEBUG') && WP_DEBUG) {
        error_log("Stock Status - Product ID: {$product->get_id()}, Location: {$location}, Meta Key: {$meta_key}, Stock: {$stock}, Original Status: {$status}");
    }
    
    if ($stock > 0) {
        return 'instock';
    } elseif ($stock === 0) {
        return 'outofstock';
    }
    
    // If no custom stock is set, return the original status
    return $status;
}

// Add location-based price and stock to product variations
add_filter('woocommerce_available_variation', 'location_based_variation_data', 99, 3);

function location_based_variation_data($variation_data, $product, $variation) {
    $location = get_user_location();
    $variation_id = $variation->get_id();
    
    // Update price
    $price_meta_key = ($location === 'au') ? 'au_price' : 'bd_price';
    $custom_price = get_post_meta($variation_id, $price_meta_key, true);
    
    if ($custom_price !== '' && is_numeric($custom_price) && $custom_price > 0) {
        $variation_data['display_price'] = wc_price($custom_price);
        $variation_data['display_regular_price'] = wc_price($custom_price);
        $variation_data['price'] = $custom_price;
        $variation_data['regular_price'] = $custom_price;
    }
    
    // Update stock
    $stock_meta_key = ($location === 'au') ? 'au_stock' : 'bd_stock';
    $custom_stock = get_post_meta($variation_id, $stock_meta_key, true);
    
    if ($custom_stock !== '' && is_numeric($custom_stock)) {
        $variation_data['max_qty'] = $custom_stock;
        $variation_data['backorders_allowed'] = false;
        $variation_data['is_in_stock'] = $custom_stock > 0;
        $variation_data['variation_is_active'] = true;
    }
    
    return $variation_data;
}

// Clear price cache when location changes
add_action('wp', function() {
    if (function_exists('WC')) {
        global $wpdb;
        
        // Clear WooCommerce product price cache
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%wc_var_prices%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%transient_wc_var_prices%'");
    }
});

// Debug function to check if it's working
add_action('wp_footer', function() {
    if (current_user_can('administrator')) {
        echo '<div style="position:fixed;bottom:10px;left:10px;background:#fff;padding:10px;border:1px solid #000;z-index:99999;max-width:400px;max-height:300px;overflow:auto;">';
        echo '<h4 style="margin:0 0 10px 0;">Location Debug Info:</h4>';
        echo 'Current Location: <strong>' . get_user_location() . '</strong><br>';
        echo 'Cookie: ' . (isset($_COOKIE['user_location']) ? $_COOKIE['user_location'] : 'Not set') . '<br>';
        
        if (function_exists('WC') && WC()->session) {
            echo 'Session: ' . WC()->session->get('user_location') . '<br>';
            echo 'Session ID: ' . WC()->session->get_customer_id() . '<br>';
        } else {
            echo 'Session: WooCommerce session not initialized<br>';
        }
        
        // Test with first few products
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => 3,
            'post_status' => 'publish'
        );
        
        $products = get_posts($args);
        
        if ($products) {
            echo '<hr style="margin:10px 0;">';
            echo '<h5 style="margin:10px 0 5px 0;">Sample Products:</h5>';
            
            foreach ($products as $product_post) {
                $product = wc_get_product($product_post->ID);
                if ($product) {
                    echo '<div style="margin-bottom:10px;padding:5px;background:#f5f5f5;">';
                    echo 'Product: ' . $product->get_name() . ' (ID: ' . $product->get_id() . ')<br>';
                    echo 'BD Price Meta: ' . get_post_meta($product->get_id(), 'bd_price', true) . '<br>';
                    echo 'AU Price Meta: ' . get_post_meta($product->get_id(), 'au_price', true) . '<br>';
                    echo 'Current Display Price: ' . $product->get_price_html() . '<br>';
                    echo 'Raw Price: ' . $product->get_price() . '<br>';
                    echo '</div>';
                }
            }
        }
        
        echo '<hr style="margin:10px 0;">';
        echo '<button onclick="clearLocationCookies()" style="padding:5px 10px;background:#f00;color:white;border:none;cursor:pointer;">Clear Location & Reload</button>';
        echo '</div>';
        
        echo '<script>
        function clearLocationCookies() {
            document.cookie = "user_location=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
            if (typeof WC !== "undefined" && WC.session) {
                fetch("' . admin_url('admin-ajax.php') . '", {
                    method: "POST",
                    headers: {"Content-Type": "application/x-www-form-urlencoded"},
                    body: "action=set_user_location&location=bd&nonce=' . wp_create_nonce('set_user_location_nonce') . '"
                }).then(() => window.location.reload(true));
            } else {
                window.location.reload(true);
            }
        }
        </script>';
    }
});

// Location modal HTML
add_action('wp_footer', function () {
    // Don't show modal for admin users or if already set
    if (current_user_can('administrator') || isset($_COOKIE['user_location'])) {
        return;
    }
?>
<div id="location-modal" class="location-modal">
    <div class="location-overlay"></div>
    <div class="location-box">
        <h3>Select Your Location</h3>
        <button class="location-action" data-location="bd">
            🇧🇩 Bangladesh
        </button>
        <button class="location-action" data-location="au">
            🇦🇺 Australia
        </button>
    </div>
</div>
<?php
});

// Location modal and dropdown script
add_action('wp_footer', function () {
    $nonce = wp_create_nonce('set_user_location_nonce');
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('location-modal');

    // Show modal only if cookie not set
    if (!document.cookie.includes('user_location=') && modal) {
        modal.classList.add('active');
    }

    // Handle location selection from modal
    document.querySelectorAll('.location-action').forEach(btn => {
        btn.addEventListener('click', function() {
            const loc = this.dataset.location;

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        action: 'set_user_location',
                        location: loc,
                        nonce: '<?php echo $nonce; ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload(true);
                    } else {
                        alert('Error: ' + (data.data || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    window.location.reload(true);
                });
        });
    });

    // Dropdown functionality
    const dropdown = document.querySelector('.location-dropdown');
    const currentLocationBtn = document.querySelector('.current-location');
    const locationMenu = document.querySelector('.location-menu');
    const dropdownActions = document.querySelectorAll('.dropdown-action');

    if (dropdown && currentLocationBtn && locationMenu) {
        // Toggle dropdown menu
        currentLocationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isVisible = locationMenu.style.display === 'block';
            locationMenu.style.display = isVisible ? 'none' : 'block';
            this.classList.toggle('active', !isVisible);
        });

        // Handle dropdown location selection
        dropdownActions.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const loc = this.dataset.location;

                // Close dropdown
                locationMenu.style.display = 'none';
                currentLocationBtn.classList.remove('active');

                // Update button text immediately
                currentLocationBtn.innerHTML = loc === 'au' ?
                    '🇦🇺 AU <span class="dropdown-arrow">▼</span>' :
                    '🇧🇩 BD <span class="dropdown-arrow">▼</span>';

                // Send AJAX request
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            action: 'set_user_location',
                            location: loc,
                            nonce: '<?php echo $nonce; ?>'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message
                            const message = document.createElement('div');
                            message.style.cssText =
                                'position:fixed;top:20px;right:20px;background:#4CAF50;color:white;padding:12px 20px;border-radius:4px;z-index:10000;font-size:14px;box-shadow:0 2px 10px rgba(0,0,0,0.1);';
                            message.textContent = 'Location updated! Reloading...';
                            document.body.appendChild(message);

                            setTimeout(() => {
                                window.location.reload(true);
                            }, 500);
                        } else {
                            alert('Error: ' + (data.data || 'Unknown error'));
                            // Revert button text
                            currentLocationBtn.innerHTML =
                                '<?php echo get_user_location() === "au" ? "🇦🇺 AU" : "🇧🇩 BD"; ?> <span class="dropdown-arrow">▼</span>';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Network error occurred');
                        // Revert button text
                        currentLocationBtn.innerHTML =
                            '<?php echo get_user_location() === "au" ? "🇦🇺 AU" : "🇧🇩 BD"; ?> <span class="dropdown-arrow">▼</span>';
                    });
            });
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            locationMenu.style.display = 'none';
            currentLocationBtn.classList.remove('active');
        });

        // Prevent dropdown from closing when clicking inside it
        locationMenu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
});
</script>
<?php
});