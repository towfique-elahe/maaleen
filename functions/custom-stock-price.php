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

// Get user location from session or cookie
function get_user_location() {
    static $location = null;
    
    if ($location !== null) {
        return $location;
    }
    
    // Try WooCommerce session first
    if (function_exists('WC') && WC()->session) {
        $session_location = WC()->session->get('user_location');
        if ($session_location && in_array($session_location, ['bd', 'au'], true)) {
            $location = $session_location;
            return $location;
        }
    }

    // Try cookie
    if (isset($_COOKIE['user_location']) && in_array($_COOKIE['user_location'], ['bd', 'au'], true)) {
        $location = sanitize_text_field($_COOKIE['user_location']);
        return $location;
    }

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
});

// ============================================
// SHORTCODE FOR HEADER DROPDOWN
// ============================================

add_shortcode('location_dropdown', 'location_dropdown_shortcode');
function location_dropdown_shortcode($atts) {
    $atts = shortcode_atts(array(
        'style' => 'default',
        'show_currency' => false,
    ), $atts, 'location_dropdown');
    
    $location = get_user_location();
    $show_currency = filter_var($atts['show_currency'], FILTER_VALIDATE_BOOLEAN);
    
    ob_start();
    ?>
<div class="location-dropdown location-dropdown-<?php echo esc_attr($atts['style']); ?>">
    <button class="current-location" type="button">
        <?php if ($show_currency): ?>
        <?php echo $location === 'au' ? '🇦🇺 AU$' : '🇧🇩 ৳'; ?>
        <?php else: ?>
        <?php echo $location === 'au' ? '🇦🇺 AU' : '🇧🇩 BD'; ?>
        <?php endif; ?>
        <span class="dropdown-arrow">
            <i class="fa fa-chevron-down" aria-hidden="true"></i>
        </span>
    </button>

    <ul class="location-menu">
        <li>
            <button class="dropdown-action" type="button" data-location="bd">
                🇧🇩 Bangladesh <?php echo $show_currency ? '(৳ BDT)' : ''; ?>
            </button>
        </li>
        <li>
            <button class="dropdown-action" type="button" data-location="au">
                🇦🇺 Australia <?php echo $show_currency ? '(AU$ AUD)' : ''; ?>
            </button>
        </li>
    </ul>
</div>

<?php
    return ob_get_clean();
}

// ============================================
// PRICE AND STOCK FILTERS
// ============================================

add_filter('woocommerce_product_get_price', 'location_based_price', 99, 2);
add_filter('woocommerce_product_get_regular_price', 'location_based_price', 99, 2);
add_filter('woocommerce_product_get_sale_price', 'location_based_price', 99, 2);

function location_based_price($price, $product) {
    $location = get_user_location();
    $meta_key = ($location === 'au') ? 'au_price' : 'bd_price';
    
    $product_id = $product->get_id();
    $custom_price = get_post_meta($product_id, $meta_key, true);
    
    if ($custom_price !== '' && is_numeric($custom_price) && $custom_price > 0) {
        return (float) $custom_price;
    }
    
    return $price;
}

// ============================================
// CURRENCY SWITCHING
// ============================================

// Change currency symbol based on location
add_filter('woocommerce_currency_symbol', 'location_based_currency_symbol', 99, 2);
function location_based_currency_symbol($currency_symbol, $currency) {
    $location = get_user_location();
    
    if ($location === 'au') {
        return 'AU$';
    } else {
        return '৳';
    }
    
    return $currency_symbol;
}

// Change currency code
add_filter('woocommerce_currency', 'location_based_currency', 99);
function location_based_currency($currency) {
    $location = get_user_location();
    return $location === 'au' ? 'AUD' : 'BDT';
}

// Change number of decimals for BDT (no decimals)
add_filter('wc_get_price_decimals', 'location_based_price_decimals', 99);
function location_based_price_decimals($decimals) {
    $location = get_user_location();
    return $location === 'au' ? 2 : 0;
}

// Change price format
add_filter('wc_get_price_thousand_separator', 'location_based_thousand_separator', 99);
function location_based_thousand_separator($separator) {
    $location = get_user_location();
    return $location === 'au' ? ',' : ',';
}

add_filter('wc_get_price_decimal_separator', 'location_based_decimal_separator', 99);
function location_based_decimal_separator($separator) {
    $location = get_user_location();
    return $location === 'au' ? '.' : '';
}

// ============================================
// STOCK MANAGEMENT
// ============================================

add_filter('woocommerce_product_get_stock_quantity', 'location_based_stock', 99, 2);
function location_based_stock($stock, $product) {
    $location = get_user_location();
    $meta_key = ($location === 'au') ? 'au_stock' : 'bd_stock';
    
    $custom_stock = get_post_meta($product->get_id(), $meta_key, true);
    
    if ($custom_stock !== '' && is_numeric($custom_stock)) {
        return (int) $custom_stock;
    }
    
    return $stock;
}

add_filter('woocommerce_product_get_stock_status', 'location_based_stock_status', 99, 2);
function location_based_stock_status($status, $product) {
    $location = get_user_location();
    $meta_key = ($location === 'au') ? 'au_stock' : 'bd_stock';
    
    $stock = (int) get_post_meta($product->get_id(), $meta_key, true);
    
    if ($stock > 0) {
        return 'instock';
    } elseif ($stock === 0) {
        return 'outofstock';
    }
    
    return $status;
}

// ============================================
// CLEAR CACHE
// ============================================

add_action('wp', function() {
    if (function_exists('WC')) {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%wc_var_prices%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%transient_wc_var_prices%'");
    }
});

// ============================================
// LOCATION MODAL
// ============================================

add_action('wp_footer', function () {
    if (current_user_can('administrator') || isset($_COOKIE['user_location'])) {
        return;
    }
?>
<div id="location-modal" class="location-modal">
    <div class="location-overlay"></div>
    <div class="location-box">
        <h3>Select Your Location</h3>
        <button class="location-action" data-location="bd">
            🇧🇩 Bangladesh (৳ BDT)
        </button>
        <button class="location-action" data-location="au">
            🇦🇺 Australia (AU$ AUD)
        </button>
    </div>
</div>
<?php
});

// ============================================
// JAVASCRIPT FOR DROPDOWN AND MODAL
// ============================================

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

    // Handle modal location selection
    document.querySelectorAll('.location-action').forEach(btn => {
        btn.addEventListener('click', function() {
            const loc = this.dataset.location;
            updateLocation(loc, this);
        });
    });

    // Handle dropdown location selection
    document.querySelectorAll('.dropdown-action').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const loc = this.dataset.location;
            const dropdown = this.closest('.location-dropdown');
            const currentBtn = dropdown.querySelector('.current-location');
            const menu = dropdown.querySelector('.location-menu');

            // Close menu
            if (menu) menu.style.display = 'none';

            // Update button text immediately
            const showCurrency = currentBtn.textContent.includes('$') || currentBtn.textContent
                .includes('৳');
            currentBtn.innerHTML = loc === 'au' ?
                '🇦🇺 ' + (showCurrency ? 'AU$' : 'AU') +
                ' <span class="dropdown-arrow">▼</span>' :
                '🇧🇩 ' + (showCurrency ? '৳' : 'BD') +
                ' <span class="dropdown-arrow">▼</span>';

            updateLocation(loc, this);
        });
    });

    // Dropdown toggle functionality
    document.querySelectorAll('.current-location').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = this.closest('.location-dropdown');
            const menu = dropdown.querySelector('.location-menu');
            if (menu) {
                menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
                dropdown.classList.toggle('active', menu.style.display === 'block');
            }
        });
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function() {
        document.querySelectorAll('.location-menu').forEach(menu => {
            menu.style.display = 'none';
        });
        document.querySelectorAll('.location-dropdown').forEach(dropdown => {
            dropdown.classList.remove('active');
        });
    });

    // Prevent dropdown from closing when clicking inside it
    document.querySelectorAll('.location-menu').forEach(menu => {
        menu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });

    // Function to update location
    function updateLocation(loc, button) {
        const originalText = button.textContent;
        button.textContent = 'Switching...';
        button.disabled = true;

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
            }).then(response => response.json())
            .then(data => {
                if (data.success) {
                    const currency = loc === 'au' ? 'AU$ (AUD)' : '৳ (BDT)';
                    showMessage('Location updated to ' + currency + '! Reloading...', 'success');
                    setTimeout(() => window.location.reload(true), 1000);
                } else {
                    showMessage('Error: ' + (data.data || 'Unknown error'), 'error');
                    button.textContent = originalText;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Network error occurred', 'error');
                button.textContent = originalText;
                button.disabled = false;
            });
    }

    // Function to show messages
    function showMessage(text, type) {
        // Remove existing messages
        document.querySelectorAll('.location-message').forEach(msg => msg.remove());

        const message = document.createElement('div');
        message.className = 'location-message';
        message.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#4CAF50' : '#f44336'};
            color: white;
            padding: 12px 20px;
            border-radius: 4px;
            z-index: 10000;
            font-size: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        `;
        message.textContent = text;
        document.body.appendChild(message);

        setTimeout(() => {
            if (message.parentNode) {
                message.parentNode.removeChild(message);
            }
        }, 3000);
    }
});
</script>
<?php
});