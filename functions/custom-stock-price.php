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

// ============================================
// SIZE VARIATIONS META FIELDS
// ============================================

// Add size variation meta box
add_action('add_meta_boxes', 'add_product_size_variations_meta_box');
function add_product_size_variations_meta_box() {
    add_meta_box(
        'product_size_variations',
        'Product Size Variations',
        'product_size_variations_meta_box_callback',
        'product',
        'side',
        'default'
    );
}

function product_size_variations_meta_box_callback($post) {
    wp_nonce_field('product_size_variations_nonce', 'size_variations_nonce');
    
    $available_sizes = array('XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL');
    $selected_sizes = get_post_meta($post->ID, '_product_sizes', true);
    $selected_sizes = is_array($selected_sizes) ? $selected_sizes : array();
    ?>
<div class="size-variations-meta-box">
    <p><strong>Select available sizes for this product:</strong></p>
    <?php foreach ($available_sizes as $size): ?>
    <label style="display: block; margin-bottom: 5px;">
        <input type="checkbox" name="product_sizes[]" value="<?php echo esc_attr($size); ?>"
            <?php checked(in_array($size, $selected_sizes)); ?>>
        <?php echo esc_html($size); ?>
    </label>
    <?php endforeach; ?>

    <p style="margin-top: 15px;">
        <strong>Custom Sizes:</strong><br>
        <input type="text" name="product_custom_size" placeholder="e.g., 28, 30, 32 or Free Size, One Size"
            style="width: 100%; margin-top: 5px;"
            value="<?php echo esc_attr(get_post_meta($post->ID, '_product_custom_size', true)); ?>">
        <small>Enter custom sizes separated by commas</small>
    </p>

    <p style="margin-top: 15px; color: #666; font-size: 12px;">
        <strong>Note:</strong> Sizes will be displayed as buttons horizontally.
    </p>
</div>
<?php
}

// Save size variations
add_action('save_post_product', 'save_product_size_variations');
function save_product_size_variations($post_id) {
    // Check nonce
    if (!isset($_POST['size_variations_nonce']) || 
        !wp_verify_nonce($_POST['size_variations_nonce'], 'product_size_variations_nonce')) {
        return;
    }
    
    // Check autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // Check permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Save selected sizes
    if (isset($_POST['product_sizes'])) {
        $sizes = array_map('sanitize_text_field', $_POST['product_sizes']);
        update_post_meta($post_id, '_product_sizes', $sizes);
    } else {
        delete_post_meta($post_id, '_product_sizes');
    }
    
    // Save custom size
    if (isset($_POST['product_custom_size'])) {
        $custom_size = sanitize_text_field($_POST['product_custom_size']);
        update_post_meta($post_id, '_product_custom_size', $custom_size);
    }
}

// ============================================
// GET PRODUCT SIZES FUNCTION
// ============================================

function get_product_sizes($product_id = null) {
    if (!$product_id) {
        global $product;
        $product_id = $product ? $product->get_id() : get_the_ID();
    }
    
    if (!$product_id) return array();
    
    $sizes = get_post_meta($product_id, '_product_sizes', true);
    $custom_size = get_post_meta($product_id, '_product_custom_size', true);
    
    $all_sizes = is_array($sizes) ? $sizes : array();
    
    // Add custom sizes if any
    if ($custom_size) {
        $custom_sizes = array_map('trim', explode(',', $custom_size));
        $all_sizes = array_merge($all_sizes, $custom_sizes);
    }
    
    // Remove duplicates and empty values
    $all_sizes = array_unique(array_filter($all_sizes));
    
    // Sort sizes in logical order
    usort($all_sizes, 'sort_product_sizes');
    
    return $all_sizes;
}

function sort_product_sizes($a, $b) {
    $size_order = array('XS' => 0, 'S' => 1, 'M' => 2, 'L' => 3, 'XL' => 4, 'XXL' => 5, 'XXXL' => 6);
    
    $a_key = isset($size_order[strtoupper($a)]) ? $size_order[strtoupper($a)] : 999;
    $b_key = isset($size_order[strtoupper($b)]) ? $size_order[strtoupper($b)] : 999;
    
    if ($a_key == 999 && $b_key == 999) {
        // Both are custom sizes, sort naturally
        return strnatcasecmp($a, $b);
    }
    
    return $a_key - $b_key;
}

// ============================================
// SHORTCODE FOR SIZE SELECTOR (BUTTON STYLE)
// ============================================

add_shortcode('product_size_selector', 'product_size_selector_shortcode');
function product_size_selector_shortcode($atts) {
    $atts = shortcode_atts(array(
        'product_id' => null,
        'label' => 'Select Size:',
        'required' => 'yes',
        'show_out_of_stock' => 'yes',
        'button_style' => 'default', // default, rounded, minimal
        'field_name' => 'selected_size', // Name of the hidden field
    ), $atts, 'product_size_selector');
    
    $product_id = $atts['product_id'];
    if (!$product_id) {
        global $product;
        $product_id = $product ? $product->get_id() : get_the_ID();
    }
    
    if (!$product_id) return '';
    
    $sizes = get_product_sizes($product_id);
    if (empty($sizes)) return '';
    
    $required = filter_var($atts['required'], FILTER_VALIDATE_BOOLEAN);
    $show_out_of_stock = filter_var($atts['show_out_of_stock'], FILTER_VALIDATE_BOOLEAN);
    $button_style = sanitize_text_field($atts['button_style']);
    $label = sanitize_text_field($atts['label']);
    $field_name = sanitize_text_field($atts['field_name']);
    
    // Check overall product stock
    $product = wc_get_product($product_id);
    $is_in_stock = $product ? $product->is_in_stock() : true;
    
    ob_start();
    ?>
<div class="product-size-selector size-buttons-style style-<?php echo esc_attr($button_style); ?>"
    data-product-id="<?php echo esc_attr($product_id); ?>">

    <?php if ($label): ?>
    <div class="size-selector-label">
        <strong><?php echo esc_html($label); ?></strong>
        <?php if ($required): ?>
        <span class="required-asterisk">*</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="size-buttons-container">
        <?php foreach ($sizes as $size):
                $disabled = !$is_in_stock ? 'disabled' : '';
                $availability_class = $is_in_stock ? 'in-stock' : 'out-of-stock';
                
                if (!$is_in_stock && !$show_out_of_stock) continue;
                ?>
        <button type="button" class="size-button <?php echo esc_attr($availability_class); ?>"
            data-size="<?php echo esc_attr($size); ?>" <?php echo $disabled; ?>
            title="<?php echo $is_in_stock ? 'Available' : 'Out of stock'; ?>">
            <?php echo esc_html($size); ?>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- CRITICAL: Make sure this field is INSIDE the form -->
    <input type="hidden" name="<?php echo esc_attr($field_name); ?>" class="selected-size-input" value=""
        <?php echo $required ? 'required' : ''; ?> data-product-id="<?php echo esc_attr($product_id); ?>">

    <?php if ($required): ?>
    <div class="size-error-message" style="display: none; color: #d63638; font-size: 12px; margin-top: 5px;">
        Please select a size before adding to cart
    </div>
    <?php endif; ?>
</div>
<?php
    return ob_get_clean();
}

// ============================================
// ELEMENTOR PRODUCT CARD SIZE DISPLAY
// ============================================

add_shortcode('product_card_sizes', 'product_card_sizes_shortcode');
function product_card_sizes_shortcode($atts) {
    $atts = shortcode_atts(array(
        'product_id' => null,
        'limit' => 4,
        'show_label' => 'no',
        'style' => 'inline', // inline, badges, simple
        'separator' => ' • ',
    ), $atts, 'product_card_sizes');
    
    $product_id = $atts['product_id'];
    if (!$product_id) {
        global $post;
        $product_id = $post ? $post->ID : 0;
    }
    
    if (!$product_id) return '';
    
    $sizes = get_product_sizes($product_id);
    if (empty($sizes)) return '';
    
    $limit = absint($atts['limit']);
    $sizes_to_display = array_slice($sizes, 0, $limit);
    $show_label = filter_var($atts['show_label'], FILTER_VALIDATE_BOOLEAN);
    $style = sanitize_text_field($atts['style']);
    $separator = sanitize_text_field($atts['separator']);
    
    ob_start();
    
    if ($style === 'badges'): ?>
<div class="product-sizes-badges">
    <?php if ($show_label): ?>
    <span class="sizes-label">Sizes:</span>
    <?php endif; ?>
    <div class="size-badges">
        <?php foreach ($sizes_to_display as $size): ?>
        <span class="size-badge"><?php echo esc_html($size); ?></span>
        <?php endforeach; ?>
        <?php if (count($sizes) > $limit): ?>
        <span class="size-more">+<?php echo count($sizes) - $limit; ?></span>
        <?php endif; ?>
    </div>
</div>
<?php elseif ($style === 'simple'): ?>
<div class="product-sizes-simple">
    <?php if ($show_label): ?>
    <span class="sizes-label">Sizes: </span>
    <?php endif; ?>
    <span class="sizes-list">
        <?php echo implode($separator, array_map('esc_html', $sizes_to_display)); ?>
        <?php if (count($sizes) > $limit): ?>
        <?php echo $separator; ?><span class="size-more">+<?php echo count($sizes) - $limit; ?> more</span>
        <?php endif; ?>
    </span>
</div>
<?php else: // inline style (default) ?>
<div class="product-sizes-inline">
    <?php if ($show_label): ?>
    <span class="sizes-label">Sizes: </span>
    <?php endif; ?>
    <?php foreach ($sizes_to_display as $index => $size): ?>
    <span class="size-item"><?php echo esc_html($size); ?></span>
    <?php if ($index < count($sizes_to_display) - 1): ?>
    <span class="size-separator"><?php echo $separator; ?></span>
    <?php endif; ?>
    <?php endforeach; ?>
    <?php if (count($sizes) > $limit): ?>
    <span class="size-separator"><?php echo $separator; ?></span>
    <span class="size-more">+<?php echo count($sizes) - $limit; ?> more</span>
    <?php endif; ?>
</div>
<?php endif;
    
    return ob_get_clean();
}

// ============================================
// ADD SIZE FIELD TO CART ITEM - FIXED
// ============================================

// Add size selection to cart item data
add_filter('woocommerce_add_cart_item_data', 'add_size_to_cart_item_data', 20, 3);
function add_size_to_cart_item_data($cart_item_data, $product_id, $variation_id) {
    // Check if size was submitted
    if (isset($_REQUEST['selected_size']) && !empty($_REQUEST['selected_size'])) {
        $cart_item_data['selected_size'] = sanitize_text_field($_REQUEST['selected_size']);
    }
    
    return $cart_item_data;
}

// Display size in cart item
add_filter('woocommerce_get_item_data', 'display_size_in_cart', 20, 2);
function display_size_in_cart($item_data, $cart_item) {
    if (!empty($cart_item['selected_size'])) {
        $item_data[] = array(
            'name' => 'Size',
            'display' => sanitize_text_field($cart_item['selected_size']),
            'value' => sanitize_text_field($cart_item['selected_size'])
        );
    }
    return $item_data;
}

// Save size to order item meta when checkout is processed
add_action('woocommerce_checkout_create_order_line_item', 'save_size_to_order_item_meta', 20, 4);
function save_size_to_order_item_meta($item, $cart_item_key, $values, $order) {
    if (!empty($values['selected_size'])) {
        $item->add_meta_data('Size', sanitize_text_field($values['selected_size']), true);
    }
}

// Display size in admin order details
add_action('woocommerce_before_order_itemmeta', 'display_size_in_admin_order', 20, 3);
function display_size_in_admin_order($item_id, $item, $product) {
    if ($item->get_meta('Size')) {
        echo '<div><strong>Size:</strong> ' . esc_html($item->get_meta('Size')) . '</div>';
    }
}

// Display size in customer order view and emails
add_filter('woocommerce_order_item_name', 'display_size_in_customer_order', 20, 2);
function display_size_in_customer_order($item_name, $item) {
    if ($item->get_meta('Size')) {
        $item_name .= '<br><small><strong>Size:</strong> ' . esc_html($item->get_meta('Size')) . '</small>';
    }
    return $item_name;
}

// Display size in order item meta in emails
add_action('woocommerce_order_item_meta_end', 'display_size_in_order_emails', 20, 4);
function display_size_in_order_emails($item_id, $item, $order, $plain_text) {
    if ($size = $item->get_meta('Size')) {
        if ($plain_text) {
            echo "\nSize: " . esc_html($size);
        } else {
            echo '<br><small><strong>Size:</strong> ' . esc_html($size) . '</small>';
        }
    }
}

// ============================================
// ADD SIZE FIELD TO ADD TO CART FORM
// ============================================

// Add size selector to single product page automatically
add_action('woocommerce_before_add_to_cart_button', 'add_size_selector_to_product_page');
function add_size_selector_to_product_page() {
    global $product;
    
    // Don't show for variable products
    if ($product->is_type('variable')) {
        return;
    }
    
    $sizes = get_product_sizes($product->get_id());
    if (empty($sizes)) {
        return;
    }
    
    echo do_shortcode('[product_size_selector]');
}

// ============================================
// ENQUEUE JAVASCRIPT FOR AJAX ADD TO CART
// ============================================

add_action('wp_footer', 'add_size_ajax_scripts');
function add_size_ajax_scripts() {
    if (!is_product() && !is_cart() && !is_checkout()) return;
    ?>
<script type="text/javascript">
jQuery(function($) {
    // Ensure size data is sent with AJAX add to cart
    $(document).on('click', '.ajax_add_to_cart', function(e) {
        var $button = $(this);
        var $form = $button.closest('form.cart');

        if ($form.length) {
            var selectedSize = $form.find('.selected-size-input').val();
            if (selectedSize) {
                // Add size to data attributes for AJAX request
                $button.data('selected_size', selectedSize);

                // Also add as query parameter for non-AJAX fallback
                var href = $button.attr('href');
                if (href) {
                    $button.attr('href', href + '&selected_size=' + encodeURIComponent(selectedSize));
                }
            }
        }
    });

    // Handle form submission for non-AJAX add to cart
    $(document).on('submit', 'form.cart', function(e) {
        var $form = $(this);
        var selectedSize = $form.find('.selected-size-input').val();

        // Validate if size is required
        if ($form.find('.selected-size-input').is('[required]') && !selectedSize) {
            e.preventDefault();
            $form.find('.size-error-message').show();
            $form.find('.size-buttons-container').addClass('size-error');
            $('html, body').animate({
                scrollTop: $form.find('.size-buttons-container').offset().top - 100
            }, 500);
            return false;
        }

        // Add hidden field with selected size
        if (selectedSize) {
            // Remove any existing size field
            $form.find('input[name="selected_size"]').remove();

            // Add new hidden field
            $form.append('<input type="hidden" name="selected_size" value="' + selectedSize + '">');
        }
    });

    // Initialize size buttons
    $(document).on('click', '.size-button.in-stock', function(e) {
        e.preventDefault();

        var $button = $(this);
        var $container = $button.closest('.size-buttons-container');
        var $selector = $button.closest('.product-size-selector');
        var size = $button.data('size');

        // Remove selected class from all buttons in this container
        $container.find('.size-button').removeClass('selected');

        // Add selected class to clicked button
        $button.addClass('selected');

        // Update hidden input value
        $selector.find('.selected-size-input').val(size);

        // Hide error message
        $selector.find('.size-error-message').hide();
        $container.removeClass('size-error');
    });

    // Auto-select first available size
    function autoSelectSize() {
        $('.product-size-selector').each(function() {
            var $selector = $(this);
            var $availableSizes = $selector.find('.size-button.in-stock:not(.out-of-stock)');
            var $selectedInput = $selector.find('.selected-size-input');

            // Only auto-select if no size is already selected
            if (!$selectedInput.val() && $availableSizes.length === 1) {
                $availableSizes.first().click();
            }
        });
    }

    // Run on page load and when AJAX content loads
    autoSelectSize();
    $(document).ajaxComplete(function() {
        setTimeout(autoSelectSize, 100);
    });
});
</script>
<?php
}

// ============================================
// CSS STYLES FOR SIZE BUTTONS
// ============================================

add_action('wp_head', 'add_size_selector_styles');
function add_size_selector_styles() {
    ?>
<style>
/* Size Selector Container */
.product-size-selector {
    margin: 15px 0;
}

.size-selector-label {
    margin-bottom: 10px;
    font-family: var(--accent-font-family);
    font-size: 14px;
    font-weight: 500;
    color: var(--dark-text-color);
}

.required-asterisk {
    color: #d63638;
    margin-left: 3px;
}

/* Size Buttons Container */
.size-buttons-container {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 5px;
}

/* Default Button Style */
.size-buttons-style.style-default .size-button {
    padding: 5px 25px !important;
    border: solid 1px var(--accent-color) !important;
    background: transparent !important;
    color: var(--accent-color) !important;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.2s ease;
    min-width: 50px;
    text-align: center;
}

.size-buttons-style.style-default .size-button:hover:not(.out-of-stock) {
    background: var(--primary-color) !important;
}

.size-buttons-style.style-default .size-button.in-stock.selected {
    background: var(--accent-color) !important;
    color: var(--primary-color) !important;
}

.size-buttons-style.style-default .size-button.out-of-stock {
    opacity: 0.5;
    cursor: not-allowed;
    text-decoration: line-through;
}

/* Rounded Button Style */
.size-buttons-style.style-rounded .size-button {
    padding: 8px 16px;
    border: 1px solid #ddd;
    background: white;
    border-radius: 20px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s ease;
}

.size-buttons-style.style-rounded .size-button:hover:not(.out-of-stock) {
    border-color: #666;
    background: #f5f5f5;
}

.size-buttons-style.style-rounded .size-button.in-stock.selected {
    border-color: #007cba;
    background: #007cba;
    color: white;
}

/* Minimal Button Style */
.size-buttons-style.style-minimal .size-button {
    padding: 8px 15px;
    border: 1px solid #e0e0e0;
    background: transparent;
    border-radius: 3px;
    cursor: pointer;
    font-size: 13px;
    color: #666;
    transition: all 0.2s ease;
}

.size-buttons-style.style-minimal .size-button:hover:not(.out-of-stock) {
    border-color: #333;
    color: #333;
}

.size-buttons-style.style-minimal .size-button.in-stock.selected {
    border-color: #333;
    background: #333;
    color: white;
}

/* Product Card Size Styles */
.product-sizes-inline,
.product-sizes-simple,
.product-sizes-badges {
    font-family: var(--accent-font-family);
    font-size: var(--accent-font-size);
    font-weight: var(--accent-font-weight);
    color: var(--accent-color);
    margin-top: 5px;
    line-height: 1.4;
}

.sizes-label {
    font-weight: 500;
    margin-right: 5px;
}

.size-item,
.size-badge {
    display: inline-block;
}

.product-sizes-inline .size-separator {
    margin: 0 3px;
    color: #999;
}

.size-badges {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px
}

.size-badge {
    background: var(--primary-color);
    padding: 7px 10px;
    border-radius: 3px;
    margin: 0 3px 3px 0;
    font-size: var(--accent-font-size);
}

.size-more {
    color: #999;
    font-style: italic;
    font-size: 11px;
}

/* Error State */
.size-buttons-container.size-error {
    border: 2px solid #d63638;
    padding: 5px;
    border-radius: 4px;
    animation: sizeErrorPulse 0.5s ease-in-out;
}

@keyframes sizeErrorPulse {

    0%,
    100% {
        border-color: #d63638;
    }

    50% {
        border-color: #ff6b6b;
    }
}
</style>
<?php
}

// ============================================
// RESTORE SIZE AFTER PAGE RELOAD/NAVIGATION
// ============================================

// Store selected size in session
add_action('woocommerce_add_to_cart', 'store_selected_size_in_session', 20, 6);
function store_selected_size_in_session($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    if (isset($cart_item_data['selected_size'])) {
        WC()->session->set('last_selected_size_' . $product_id, $cart_item_data['selected_size']);
    }
}

// Pre-select size based on session when page loads
add_action('wp_footer', 'preselect_size_from_session');
function preselect_size_from_session() {
    if (!is_product()) return;
    
    global $product;
    if (!$product) return;
    
    $product_id = $product->get_id();
    $last_size = WC()->session ? WC()->session->get('last_selected_size_' . $product_id) : null;
    
    if ($last_size) {
        ?>
<script>
jQuery(function($) {
    setTimeout(function() {
        var $sizeButton = $('.size-button[data-size="<?php echo esc_js($last_size); ?>"]');
        if ($sizeButton.length && $sizeButton.hasClass('in-stock')) {
            $sizeButton.click();
        }
    }, 500);
});
</script>
<?php
    }
}

// Disable WooCommerce add to cart notice
add_filter( 'wc_add_to_cart_message_html', '__return_false' );
