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
// REMOVE UNWANTED PRODUCT TAXONOMIES AND META BOXES
// ============================================

// Remove product brands taxonomy
add_action('init', 'remove_product_brands_taxonomy', 100);
function remove_product_brands_taxonomy() {
    global $wp_taxonomies;
    
    // Unregister product brands taxonomy if it exists
    if (taxonomy_exists('product_brand')) {
        unset($wp_taxonomies['product_brand']);
    }
    if (taxonomy_exists('product_brands')) {
        unset($wp_taxonomies['product_brands']);
    }
    if (taxonomy_exists('brand')) {
        unset($wp_taxonomies['brand']);
    }
    if (taxonomy_exists('brands')) {
        unset($wp_taxonomies['brands']);
    }
}

// Remove all product attribute taxonomies
add_action('init', 'remove_product_attribute_taxonomies', 100);
function remove_product_attribute_taxonomies() {
    global $wp_taxonomies;
    
    // Get all taxonomy names
    if (!empty($wp_taxonomies)) {
        foreach ($wp_taxonomies as $tax_key => $taxonomy) {
            // Check if it's a product attribute taxonomy (starts with 'pa_')
            if (strpos($tax_key, 'pa_') === 0) {
                unset($wp_taxonomies[$tax_key]);
            }
        }
    }
}

// Remove product brand and attribute meta boxes from product edit page
add_action('add_meta_boxes', 'remove_unwanted_product_meta_boxes', 100);
function remove_unwanted_product_meta_boxes() {
    // Remove brand meta boxes
    remove_meta_box('product_branddiv', 'product', 'side');
    remove_meta_box('product_brandsdiv', 'product', 'side');
    remove_meta_box('branddiv', 'product', 'side');
    remove_meta_box('tagsdiv-product_brand', 'product', 'side');
    remove_meta_box('tagsdiv-product_brands', 'product', 'side');
    
    // Remove all attribute meta boxes
    global $wp_taxonomies;
    if (!empty($wp_taxonomies)) {
        foreach ($wp_taxonomies as $tax_key => $taxonomy) {
            if (strpos($tax_key, 'pa_') === 0) {
                remove_meta_box($tax_key . 'div', 'product', 'side');
            }
        }
    }
}

// Hide the entire Product Data block (WooCommerce product data meta box)
add_action('admin_head', 'hide_product_data_block');
function hide_product_data_block() {
    global $post_type;
    
    if ($post_type === 'product') {
        ?>
<style>
/* Hide the Product Data meta box */
#woocommerce-product-data {
    display: none !important;
}

/* Hide any other product data sections */
.postbox .woocommerce_product_data {
    display: none !important;
}

/* Hide Product Data in block editor if using Gutenberg */
.woocommerce-product-data {
    display: none !important;
}

/* Hide the product data tabs that might appear elsewhere */
.product_data_tabs {
    display: none !important;
}

/* Hide any attribute related sections */
.product_attributes {
    display: none !important;
}
</style>
<?php
    }
}

// Completely disable WooCommerce product data panels via filter
add_filter('woocommerce_product_data_tabs', 'disable_product_data_tabs');
function disable_product_data_tabs($tabs) {
    // Return empty array to hide all tabs
    return array();
}

// Remove all WooCommerce product data panels
add_action('admin_init', 'remove_product_data_panels');
function remove_product_data_panels() {
    // Remove all standard product data panels
    remove_meta_box('woocommerce-product-data', 'product', 'normal');
    remove_meta_box('woocommerce-product-images', 'product', 'side');
    
    // Remove inventory panel
    remove_meta_box('postexcerpt', 'product', 'normal');
    
    // Remove any other WooCommerce specific meta boxes
    remove_meta_box('tagsdiv-product_tag', 'product', 'side');
    remove_meta_box('product_catdiv', 'product', 'side');
}

// Hide Custom Fields meta box
add_action('admin_head', 'hide_custom_fields_metabox');
function hide_custom_fields_metabox() {
    global $post_type;
    
    if ($post_type === 'product') {
        ?>
<style>
/* Hide Custom Fields meta box */
#postcustom,
#postcustom h2,
.postbox#postcustom {
    display: none !important;
}

/* Hide any other meta boxes we don't need */
#tagsdiv-product_tag,
#product_catdiv,
#slugdiv,
#authordiv,
#revisionsdiv,
#commentstatusdiv,
#commentsdiv {
    display: none !important;
}

/* Hide the Screen Options for these boxes */
.metabox-prefs label[for="postcustom-hide"],
.metabox-prefs label[for="tagsdiv-product_tag-hide"],
.metabox-prefs label[for="product_catdiv-hide"] {
    display: none !important;
}
</style>
<?php
    }
}

// Remove custom fields from the post editing screen completely
add_filter('default_hidden_meta_boxes', 'hide_all_meta_boxes', 10, 2);
function hide_all_meta_boxes($hidden, $screen) {
    if ('product' === $screen->id) {
        // Add all meta boxes we want hidden by default
        $hidden[] = 'postcustom'; // Custom Fields
        $hidden[] = 'slugdiv'; // Slug
        $hidden[] = 'authordiv'; // Author
        $hidden[] = 'revisionsdiv'; // Revisions
        $hidden[] = 'commentstatusdiv'; // Discussion
        $hidden[] = 'commentsdiv'; // Comments
        $hidden[] = 'trackbacksdiv'; // Trackbacks
        $hidden[] = 'woocommerce-product-data'; // Product Data
    }
    return $hidden;
}

// Remove product attributes from frontend queries
add_filter('woocommerce_attribute', 'remove_product_attributes', 10, 3);
function remove_product_attributes($output, $attribute, $values) {
    // Return empty string to hide all attributes on frontend
    return '';
}

// Remove product attributes from structured data
add_filter('woocommerce_structured_data_product', 'remove_attributes_from_structured_data', 10, 2);
function remove_attributes_from_structured_data($markup, $product) {
    // Remove attributes from structured data
    if (isset($markup['brand'])) {
        unset($markup['brand']);
    }
    return $markup;
}

// Remove brand from WooCommerce product blocks
add_filter('woocommerce_product_get_brand', '__return_empty_string');
add_filter('woocommerce_product_get_brands', '__return_empty_array');

// ============================================
// LOCATION-BASED PRICE META FIELDS
// ============================================

// Add price meta boxes for Bangladesh and Australia
add_action('add_meta_boxes', 'add_location_price_meta_boxes');
function add_location_price_meta_boxes() {
    add_meta_box(
        'location_prices',
        'Location-Based Prices',
        'location_price_meta_box_callback',
        'product',
        'normal',
        'high'
    );
}

function location_price_meta_box_callback($post) {
    wp_nonce_field('location_prices_nonce', 'location_prices_nonce');
    
    $bd_price = get_post_meta($post->ID, 'bd_price', true);
    $au_price = get_post_meta($post->ID, 'au_price', true);
    $regular_price = get_post_meta($post->ID, '_regular_price', true);
    ?>
<div style="display: flex; flex-wrap: wrap; gap: 30px; padding: 15px 0;">
    <!-- Bangladesh Price Field -->
    <div
        style="flex: 1; min-width: 250px; background: #f9f9f9; padding: 20px; border-radius: 8px; border-left: 4px solid #006a4e;">
        <h3 style="margin-top: 0; color: #006a4e;">🇧🇩 Bangladesh (BDT)</h3>
        <div style="margin-bottom: 15px;">
            <label style="font-weight: bold; display: block; margin-bottom: 5px;">Price in BDT (৳)</label>
            <input type="number" name="bd_price" value="<?php echo esc_attr($bd_price); ?>" step="0.01" min="0"
                style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <p class="description" style="margin-top: 5px; color: #666;">
                Enter price in Bangladeshi Taka (৳). Leave empty to use regular price.
            </p>
        </div>
        <?php if ($regular_price): ?>
        <div style="background: #fff; padding: 10px; border-radius: 4px; border: 1px solid #e0e0e0;">
            <strong>Default Regular Price:</strong> ৳<?php echo esc_html($regular_price); ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Australia Price Field -->
    <div
        style="flex: 1; min-width: 250px; background: #f9f9f9; padding: 20px; border-radius: 8px; border-left: 4px solid #00008B;">
        <h3 style="margin-top: 0; color: #00008B;">🇦🇺 Australia (AUD)</h3>
        <div style="margin-bottom: 15px;">
            <label style="font-weight: bold; display: block; margin-bottom: 5px;">Price in AUD (AU$)</label>
            <input type="number" name="au_price" value="<?php echo esc_attr($au_price); ?>" step="0.01" min="0"
                style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <p class="description" style="margin-top: 5px; color: #666;">
                Enter price in Australian Dollars (AU$). Leave empty to use regular price.
            </p>
        </div>
        <?php if ($regular_price): ?>
        <div style="background: #fff; padding: 10px; border-radius: 4px; border: 1px solid #e0e0e0;">
            <strong>Default Regular Price:</strong> AU$<?php echo esc_html($regular_price); ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Price Comparison Table -->
<?php if ($bd_price || $au_price || $regular_price): ?>
<div style="margin-top: 20px; padding: 15px; background: #f0f0f0; border-radius: 8px;">
    <h4 style="margin-top: 0;">Price Comparison</h4>
    <table style="width: 100%; border-collapse: collapse;">
        <tr style="background: #e0e0e0;">
            <th style="padding: 10px; text-align: left;">Location</th>
            <th style="padding: 10px; text-align: left;">Currency</th>
            <th style="padding: 10px; text-align: left;">Price</th>
        </tr>
        <tr style="background: #fff;">
            <td style="padding: 10px; border-bottom: 1px solid #ddd;">🇧🇩 Bangladesh</td>
            <td style="padding: 10px; border-bottom: 1px solid #ddd;">BDT (৳)</td>
            <td style="padding: 10px; border-bottom: 1px solid #ddd;">
                <strong><?php echo $bd_price ? '৳' . esc_html($bd_price) : 'Using regular price (৳' . esc_html($regular_price) . ')'; ?></strong>
            </td>
        </tr>
        <tr style="background: #f9f9f9;">
            <td style="padding: 10px; border-bottom: 1px solid #ddd;">🇦🇺 Australia</td>
            <td style="padding: 10px; border-bottom: 1px solid #ddd;">AUD (AU$)</td>
            <td style="padding: 10px; border-bottom: 1px solid #ddd;">
                <strong><?php echo $au_price ? 'AU$' . esc_html($au_price) : 'Using regular price (AU$' . esc_html($regular_price) . ')'; ?></strong>
            </td>
        </tr>
    </table>
    <p class="description" style="margin-top: 10px; margin-bottom: 0;">
        <small>* Prices shown are what customers will see based on their selected location.</small>
    </p>
</div>
<?php endif; ?>
<?php
}

// Save location-based prices
add_action('save_post_product', 'save_location_prices');
function save_location_prices($post_id) {
    // Check nonce
    if (!isset($_POST['location_prices_nonce']) || 
        !wp_verify_nonce($_POST['location_prices_nonce'], 'location_prices_nonce')) {
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
    
    // Save Bangladesh price
    if (isset($_POST['bd_price'])) {
        $bd_price = floatval($_POST['bd_price']);
        if ($bd_price > 0) {
            update_post_meta($post_id, 'bd_price', $bd_price);
        } else {
            delete_post_meta($post_id, 'bd_price');
        }
    }
    
    // Save Australia price
    if (isset($_POST['au_price'])) {
        $au_price = floatval($_POST['au_price']);
        if ($au_price > 0) {
            update_post_meta($post_id, 'au_price', $au_price);
        } else {
            delete_post_meta($post_id, 'au_price');
        }
    }
}

// ============================================
// PRICE FILTERS
// ============================================

add_filter('woocommerce_product_get_price', 'location_based_price', 99, 2);
add_filter('woocommerce_product_get_regular_price', 'location_based_price', 99, 2);
add_filter('woocommerce_product_get_sale_price', 'location_based_price', 99, 2);
add_filter('woocommerce_product_variation_get_price', 'location_based_price', 99, 2);
add_filter('woocommerce_product_variation_get_regular_price', 'location_based_price', 99, 2);
add_filter('woocommerce_product_variation_get_sale_price', 'location_based_price', 99, 2);

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

// Display location-based price in admin column (optional)
add_filter('manage_product_posts_columns', 'add_location_price_columns');
function add_location_price_columns($columns) {
    $columns['bd_price'] = '🇧🇩 BDT Price';
    $columns['au_price'] = '🇦🇺 AUD Price';
    return $columns;
}

add_action('manage_product_posts_custom_column', 'display_location_price_columns', 10, 2);
function display_location_price_columns($column, $post_id) {
    if ($column === 'bd_price') {
        $price = get_post_meta($post_id, 'bd_price', true);
        echo $price ? '৳' . number_format($price, 2) : '—';
    }
    if ($column === 'au_price') {
        $price = get_post_meta($post_id, 'au_price', true);
        echo $price ? 'AU$' . number_format($price, 2) : '—';
    }
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
// SIZE VARIATIONS META FIELDS
// ============================================

// Add size variation meta box (without custom size option)
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

    <p style="margin-top: 15px; color: #666; font-size: 12px;">
        <strong>Note:</strong> After saving sizes, you can set inventory for each size in the "Size Variant Inventory"
        meta box below.
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
}

// ============================================
// SIZE VARIANT INVENTORY MANAGEMENT
// ============================================

// Add size inventory meta box
add_action('add_meta_boxes', 'add_size_inventory_meta_box');
function add_size_inventory_meta_box() {
    add_meta_box(
        'size_inventory',
        'Size Variant Inventory',
        'size_inventory_meta_box_callback',
        'product',
        'normal',
        'high'
    );
}

function size_inventory_meta_box_callback($post) {
    wp_nonce_field('size_inventory_nonce', 'size_inventory_nonce');
    
    $sizes = get_product_sizes($post->ID);
    $locations = ['bd' => 'Bangladesh', 'au' => 'Australia'];
    
    if (empty($sizes)) {
        echo '<p style="color: #999;">No sizes defined for this product. Please add sizes in the "Product Size Variations" meta box first.</p>';
        return;
    }
    
    foreach ($locations as $location_code => $location_name):
        $inventory = get_post_meta($post->ID, "_size_inventory_{$location_code}", true);
        $inventory = is_array($inventory) ? $inventory : array();
        $manage_stock = get_post_meta($post->ID, "_manage_size_stock_{$location_code}", true);
        ?>
<div style="margin-bottom: 30px; border-bottom: 1px solid #ddd; padding-bottom: 20px;">
    <h3><?php echo esc_html($location_name); ?> Stock</h3>

    <table class="widefat" style="width: auto; min-width: 400px; margin-bottom: 15px;">
        <thead>
            <tr>
                <th style="padding: 10px;">Size</th>
                <th style="padding: 10px;">Stock Quantity</th>
                <th style="padding: 10px;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sizes as $size): 
                        $stock_value = isset($inventory[$size]) ? intval($inventory[$size]) : 0;
                        $status = $stock_value > 0 ? 'In Stock' : 'Out of Stock';
                        $status_color = $stock_value > 0 ? '#46b450' : '#cc0000';
                        ?>
            <tr>
                <td style="padding: 10px;">
                    <strong><?php echo esc_html($size); ?></strong>
                </td>
                <td style="padding: 10px;">
                    <input type="number"
                        name="size_inventory[<?php echo esc_attr($location_code); ?>][<?php echo esc_attr($size); ?>]"
                        value="<?php echo esc_attr($stock_value); ?>" min="0" step="1" style="width: 100px;">
                </td>
                <td style="padding: 10px; color: <?php echo $status_color; ?>;">
                    <?php echo $status; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Manage stock setting -->
    <div style="margin-top: 15px; padding: 10px; background: #f9f9f9; border-left: 4px solid #007cba;">
        <label style="font-weight: bold;">
            <input type="checkbox" name="manage_size_stock[<?php echo esc_attr($location_code); ?>]" value="yes"
                <?php checked($manage_stock, 'yes'); ?>>
            Enable size-based inventory for <?php echo esc_html($location_name); ?>
        </label>
        <p class="description" style="margin-top: 5px; color: #666;">
            When checked, stock will be managed per size. When unchecked, simple product stock management will be used.
        </p>
    </div>

    <!-- Total stock summary -->
    <div style="margin-top: 15px; padding: 10px; background: #f0f0f0; border-radius: 4px;">
        <strong>Total Stock (<?php echo esc_html($location_name); ?>):</strong>
        <?php echo array_sum($inventory); ?> items
    </div>
</div>
<?php
    endforeach;
}

// Save size inventory
add_action('save_post_product', 'save_size_inventory');
function save_size_inventory($post_id) {
    // Check nonce
    if (!isset($_POST['size_inventory_nonce']) || 
        !wp_verify_nonce($_POST['size_inventory_nonce'], 'size_inventory_nonce')) {
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
    
    // Save size inventory for each location
    if (isset($_POST['size_inventory'])) {
        foreach ($_POST['size_inventory'] as $location => $sizes) {
            $sanitized_inventory = array();
            foreach ($sizes as $size => $stock) {
                $sanitized_size = sanitize_text_field($size);
                $sanitized_stock = intval($stock);
                if ($sanitized_stock >= 0) {
                    $sanitized_inventory[$sanitized_size] = $sanitized_stock;
                }
            }
            update_post_meta($post_id, "_size_inventory_{$location}", $sanitized_inventory);
        }
    }
    
    // Save manage stock settings
    $locations = ['bd', 'au'];
    foreach ($locations as $location) {
        if (isset($_POST['manage_size_stock'][$location]) && $_POST['manage_size_stock'][$location] === 'yes') {
            update_post_meta($post_id, "_manage_size_stock_{$location}", 'yes');
        } else {
            delete_post_meta($post_id, "_manage_size_stock_{$location}");
        }
    }
}

// Add size stock logs meta box
add_action('add_meta_boxes', 'add_size_stock_logs_meta_box');
function add_size_stock_logs_meta_box() {
    add_meta_box(
        'size_stock_logs',
        'Size Stock Change Logs',
        'size_stock_logs_meta_box_callback',
        'product',
        'normal',
        'low'
    );
}

function size_stock_logs_meta_box_callback($post) {
    $logs = get_post_meta($post->ID, '_size_stock_logs', true);
    
    if (empty($logs)) {
        echo '<p>No stock changes logged yet.</p>';
        return;
    }
    
    echo '<div style="max-height: 300px; overflow-y: auto; background: #f9f9f9; padding: 10px; border: 1px solid #ddd;">';
    echo '<ul style="margin: 0; padding: 0; list-style: none;">';
    foreach ($logs as $log) {
        echo '<li style="padding: 8px; border-bottom: 1px solid #eee; font-family: monospace;">' . esc_html($log) . '</li>';
    }
    echo '</ul>';
    echo '</div>';
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
    $all_sizes = is_array($sizes) ? $sizes : array();
    
    // Remove empty values
    $all_sizes = array_filter($all_sizes);
    
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
// SIZE-BASED STOCK MANAGEMENT FUNCTIONS
// ============================================

// Get stock for a specific size
function get_size_stock($product_id, $size, $location = null) {
    if (!$location) {
        $location = get_user_location();
    }
    
    $inventory = get_post_meta($product_id, "_size_inventory_{$location}", true);
    
    if (is_array($inventory) && isset($inventory[$size])) {
        return intval($inventory[$size]);
    }
    
    return null; // Return null if size stock not managed
}

// Check if product manages stock by size
function manages_size_stock($product_id, $location = null) {
    if (!$location) {
        $location = get_user_location();
    }
    
    $manage_stock = get_post_meta($product_id, "_manage_size_stock_{$location}", true);
    return $manage_stock === 'yes';
}

// Override stock status based on size availability
add_filter('woocommerce_product_get_stock_status', 'size_based_stock_status', 99, 2);
function size_based_stock_status($status, $product) {
    $location = get_user_location();
    $product_id = $product->get_id();
    
    // Check if this product manages size stock
    if (!manages_size_stock($product_id, $location)) {
        return $status;
    }
    
    $inventory = get_post_meta($product_id, "_size_inventory_{$location}", true);
    
    if (is_array($inventory) && !empty($inventory)) {
        // Check if any size has stock
        foreach ($inventory as $stock) {
            if ($stock > 0) {
                return 'instock';
            }
        }
        return 'outofstock';
    }
    
    return $status;
}

// Validate cart item stock before adding to cart
add_filter('woocommerce_add_to_cart_validation', 'validate_size_stock_before_add_to_cart', 20, 3);
function validate_size_stock_before_add_to_cart($passed, $product_id, $quantity) {
    $location = get_user_location();
    
    // Check if size was selected
    if (!isset($_REQUEST['selected_size']) || empty($_REQUEST['selected_size'])) {
        wc_add_notice(__('Please select a size.', 'woocommerce'), 'error');
        return false;
    }
    
    $size = sanitize_text_field($_REQUEST['selected_size']);
    
    // Check if product manages size stock
    if (!manages_size_stock($product_id, $location)) {
        return $passed;
    }
    
    // Get stock for this size
    $size_stock = get_size_stock($product_id, $size, $location);
    
    if ($size_stock !== null) {
        if ($size_stock <= 0) {
            wc_add_notice(sprintf(__('Sorry, size %s is out of stock.', 'woocommerce'), $size), 'error');
            return false;
        }
        
        if ($size_stock < $quantity) {
            wc_add_notice(sprintf(__('Sorry, we only have %d item(s) in size %s available.', 'woocommerce'), $size_stock, $size), 'error');
            return false;
        }
    }
    
    return $passed;
}

// Reduce size stock when order is placed
add_action('woocommerce_checkout_create_order_line_item', 'reduce_size_stock_on_order', 20, 4);
function reduce_size_stock_on_order($item, $cart_item_key, $values, $order) {
    if (!empty($values['selected_size'])) {
        $size = $values['selected_size'];
        $product_id = $values['product_id'];
        $quantity = $values['quantity'];
        
        // Get order location
        $location = get_post_meta($order->get_id(), '_user_location', true);
        if (!$location) {
            $location = isset($values['user_location']) ? $values['user_location'] : get_user_location();
        }
        
        // Reduce stock for this size
        reduce_size_stock($product_id, $size, $quantity, $location);
    }
}

// Reduce size stock function
function reduce_size_stock($product_id, $size, $quantity, $location) {
    $inventory = get_post_meta($product_id, "_size_inventory_{$location}", true);
    
    if (is_array($inventory) && isset($inventory[$size])) {
        $current_stock = intval($inventory[$size]);
        $new_stock = max(0, $current_stock - $quantity);
        
        $inventory[$size] = $new_stock;
        update_post_meta($product_id, "_size_inventory_{$location}", $inventory);
        
        // Log stock reduction
        $log_entry = sprintf(
            '[%s] Size stock reduced: %s - Size %s: %d → %d (Order quantity: %d)',
            strtoupper($location),
            date('Y-m-d H:i:s'),
            $size,
            $current_stock,
            $new_stock,
            $quantity
        );
        
        $logs = get_post_meta($product_id, '_size_stock_logs', true);
        if (!is_array($logs)) {
            $logs = array();
        }
        array_unshift($logs, $log_entry);
        $logs = array_slice($logs, 0, 50); // Keep only last 50 logs
        update_post_meta($product_id, '_size_stock_logs', $logs);
    }
}

// Restore size stock when order is cancelled/refunded
add_action('woocommerce_order_status_cancelled', 'restore_size_stock_on_order_cancelled');
add_action('woocommerce_order_status_refunded', 'restore_size_stock_on_order_cancelled');
function restore_size_stock_on_order_cancelled($order_id) {
    $order = wc_get_order($order_id);
    $location = get_post_meta($order_id, '_user_location', true);
    
    if (!$location) {
        return;
    }
    
    foreach ($order->get_items() as $item) {
        $size = $item->get_meta('Size');
        $product_id = $item->get_product_id();
        $quantity = $item->get_quantity();
        
        if ($size && $product_id) {
            $inventory = get_post_meta($product_id, "_size_inventory_{$location}", true);
            
            if (is_array($inventory) && isset($inventory[$size])) {
                $current_stock = intval($inventory[$size]);
                $inventory[$size] = $current_stock + $quantity;
                update_post_meta($product_id, "_size_inventory_{$location}", $inventory);
                
                // Log stock restoration
                $log_entry = sprintf(
                    '[%s] Size stock restored: %s - Size %s: %d → %d (Order cancelled: #%d)',
                    strtoupper($location),
                    date('Y-m-d H:i:s'),
                    $size,
                    $current_stock,
                    $current_stock + $quantity,
                    $order_id
                );
                
                $logs = get_post_meta($product_id, '_size_stock_logs', true);
                if (!is_array($logs)) {
                    $logs = array();
                }
                array_unshift($logs, $log_entry);
                $logs = array_slice($logs, 0, 50);
                update_post_meta($product_id, '_size_stock_logs', $logs);
            }
        }
    }
}

// Store location with order
add_action('woocommerce_checkout_update_order_meta', 'store_location_with_order');
function store_location_with_order($order_id) {
    $location = get_user_location();
    update_post_meta($order_id, '_user_location', $location);
}

// ============================================
// AJAX HANDLER FOR SIZE AVAILABILITY
// ============================================

add_action('wp_ajax_check_size_availability', 'check_size_availability');
add_action('wp_ajax_nopriv_check_size_availability', 'check_size_availability');
function check_size_availability() {
    if (!isset($_POST['product_id']) || !isset($_POST['size'])) {
        wp_send_json_error('Missing parameters');
    }
    
    $product_id = intval($_POST['product_id']);
    $size = sanitize_text_field($_POST['size']);
    $location = get_user_location();
    
    $stock = get_size_stock($product_id, $size, $location);
    
    if ($stock !== null) {
        wp_send_json_success(array(
            'available' => $stock > 0,
            'stock' => $stock,
            'in_stock' => $stock > 0
        ));
    } else {
        // Size stock not managed, fall back to product stock
        $product = wc_get_product($product_id);
        wp_send_json_success(array(
            'available' => $product->is_in_stock(),
            'stock' => $product->get_stock_quantity(),
            'in_stock' => $product->is_in_stock()
        ));
    }
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
        'button_style' => 'default',
        'field_name' => 'selected_size',
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
    
    $location = get_user_location();
    $manages_size_stock = manages_size_stock($product_id, $location);
    
    ob_start();
    ?>
<div class="product-size-selector size-buttons-style style-<?php echo esc_attr($button_style); ?>"
    data-product-id="<?php echo esc_attr($product_id); ?>"
    data-manages-stock="<?php echo $manages_size_stock ? 'yes' : 'no'; ?>">

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
                $stock = $manages_size_stock ? get_size_stock($product_id, $size, $location) : null;
                $is_in_stock = $stock === null ? true : ($stock > 0);
                $stock_text = $stock !== null ? " ({$stock} available)" : '';
                $disabled = !$is_in_stock ? 'disabled' : '';
                $availability_class = $is_in_stock ? 'in-stock' : 'out-of-stock';
                
                if (!$is_in_stock && !$show_out_of_stock) continue;
                ?>
        <button type="button" class="size-button <?php echo esc_attr($availability_class); ?>"
            data-size="<?php echo esc_attr($size); ?>" <?php echo $disabled; ?>
            title="<?php echo $is_in_stock ? 'Available' . $stock_text : 'Out of stock'; ?>">
            <?php echo esc_html($size); ?>
            <?php if ($stock !== null && $stock > 0 && $show_out_of_stock): ?>
            <span class="stock-badge"><?php echo $stock; ?></span>
            <?php endif; ?>
        </button>
        <?php endforeach; ?>
    </div>

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
// SHORTCODE FOR PRODUCT CARD SIZES
// ============================================

add_shortcode('product_card_sizes', 'product_card_sizes_shortcode');
function product_card_sizes_shortcode($atts) {
    $atts = shortcode_atts(array(
        'product_id' => null,
        'limit' => 4,
        'show_label' => 'no',
        'style' => 'inline',
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
// ADD SIZE FIELD TO CART ITEM
// ============================================

add_filter('woocommerce_add_cart_item_data', 'add_size_to_cart_item_data', 20, 3);
function add_size_to_cart_item_data($cart_item_data, $product_id, $variation_id) {
    if (isset($_REQUEST['selected_size']) && !empty($_REQUEST['selected_size'])) {
        $cart_item_data['selected_size'] = sanitize_text_field($_REQUEST['selected_size']);
        $cart_item_data['user_location'] = get_user_location();
    }
    
    return $cart_item_data;
}

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

add_action('woocommerce_checkout_create_order_line_item', 'save_size_to_order_item_meta', 20, 4);
function save_size_to_order_item_meta($item, $cart_item_key, $values, $order) {
    if (!empty($values['selected_size'])) {
        $item->add_meta_data('Size', sanitize_text_field($values['selected_size']), true);
    }
}

add_action('woocommerce_before_order_itemmeta', 'display_size_in_admin_order', 20, 3);
function display_size_in_admin_order($item_id, $item, $product) {
    if ($item->get_meta('Size')) {
        echo '<div><strong>Size:</strong> ' . esc_html($item->get_meta('Size')) . '</div>';
    }
}

add_filter('woocommerce_order_item_name', 'display_size_in_customer_order', 20, 2);
function display_size_in_customer_order($item_name, $item) {
    if ($item->get_meta('Size')) {
        $item_name .= '<br><small><strong>Size:</strong> ' . esc_html($item->get_meta('Size')) . '</small>';
    }
    return $item_name;
}

// ============================================
// ADD SIZE FIELD TO ADD TO CART FORM
// ============================================

add_action('woocommerce_before_add_to_cart_button', 'add_size_selector_to_product_page');
function add_size_selector_to_product_page() {
    global $product;
    
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
// DISPLAY SIZE STOCK STATUS
// ============================================

add_action('woocommerce_before_add_to_cart_button', 'display_size_stock_status');
function display_size_stock_status() {
    global $product;
    
    if (!$product || $product->is_type('variable')) {
        return;
    }
    
    $sizes = get_product_sizes($product->get_id());
    if (empty($sizes)) {
        return;
    }
    
    $location = get_user_location();
    $manages_size_stock = manages_size_stock($product->get_id(), $location);
    
    if (!$manages_size_stock) {
        return;
    }
    
    ?>
<div class="size-stock-info"
    style="margin: 15px 0; padding: 10px; background: #f9f9f9; border-radius: 4px; border-left: 3px solid #007cba;">
    <strong style="display: block; margin-bottom: 10px;">📦 Size Availability
        (<?php echo strtoupper($location); ?>):</strong>
    <div style="display: flex; flex-wrap: wrap; gap: 10px;">
        <?php foreach ($sizes as $size): 
                $stock = get_size_stock($product->get_id(), $size, $location);
                $stock_status = $stock > 0 ? 'In stock: ' . $stock : 'Out of stock';
                $status_class = $stock > 0 ? 'in-stock' : 'out-of-stock';
                $bg_color = $stock > 0 ? '#e8f5e9' : '#ffebee';
                $border_color = $stock > 0 ? '#4caf50' : '#f44336';
                ?>
        <div class="size-stock-item <?php echo esc_attr($status_class); ?>"
            style="padding: 8px 15px; background: <?php echo $bg_color; ?>; border-left: 4px solid <?php echo $border_color; ?>; border-radius: 3px; min-width: 80px;">
            <span style="font-weight: bold; font-size: 16px;"><?php echo esc_html($size); ?></span><br>
            <span style="font-size: 12px; color: #666;"><?php echo esc_html($stock_status); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php
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
// JAVASCRIPT FOR DROPDOWN, MODAL AND SIZE SELECTION
// ============================================

add_action('wp_footer', function () {
    $nonce = wp_create_nonce('set_user_location_nonce');
    $size_nonce = wp_create_nonce('size_availability_nonce');
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

            if (menu) menu.style.display = 'none';

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

    // Size selection functionality
    initializeSizeButtons();

    function initializeSizeButtons() {
        // Check size availability when size is selected
        document.querySelectorAll('.size-button.in-stock').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();

                const $button = this;
                const $selector = $button.closest('.product-size-selector');
                const $container = $button.closest('.size-buttons-container');
                const productId = $selector.dataset.productId;
                const size = $button.dataset.size;
                const managesStock = $selector.dataset.managesStock === 'yes';

                // Remove selected class from all buttons
                $container.querySelectorAll('.size-button').forEach(btn => {
                    btn.classList.remove('selected');
                });

                // Add selected class to clicked button
                $button.classList.add('selected');

                // Update hidden input
                $selector.querySelector('.selected-size-input').value = size;

                // Hide error message
                const errorMsg = $selector.querySelector('.size-error-message');
                if (errorMsg) errorMsg.style.display = 'none';
                $container.classList.remove('size-error');

                // Check availability via AJAX if managing stock
                if (managesStock) {
                    $button.classList.add('checking-availability');

                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({
                                action: 'check_size_availability',
                                product_id: productId,
                                size: size,
                                nonce: '<?php echo $size_nonce; ?>'
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                if (!data.data.available) {
                                    $button.classList.remove('in-stock');
                                    $button.classList.add('out-of-stock');
                                    $button.disabled = true;
                                    $button.title = 'Out of stock';

                                    // Clear selection if out of stock
                                    $selector.querySelector('.selected-size-input').value =
                                        '';
                                    $button.classList.remove('selected');
                                }
                            }
                        })
                        .finally(() => {
                            $button.classList.remove('checking-availability');
                        });
                }
            });
        });

        // Auto-select first available size if only one
        document.querySelectorAll('.product-size-selector').forEach(selector => {
            const availableSizes = selector.querySelectorAll(
            '.size-button.in-stock:not(.out-of-stock)');
            const selectedInput = selector.querySelector('.selected-size-input');

            if (!selectedInput.value && availableSizes.length === 1) {
                availableSizes[0].click();
            }
        });
    }

    // Form validation
    document.querySelectorAll('form.cart').forEach(form => {
        form.addEventListener('submit', function(e) {
            const selectedSize = this.querySelector('.selected-size-input')?.value;
            const sizeRequired = this.querySelector('.selected-size-input')?.hasAttribute(
                'required');

            if (sizeRequired && !selectedSize) {
                e.preventDefault();

                const selector = this.querySelector('.product-size-selector');
                const errorMsg = selector.querySelector('.size-error-message');
                const container = selector.querySelector('.size-buttons-container');

                if (errorMsg) {
                    errorMsg.style.display = 'block';
                }
                container.classList.add('size-error');

                // Scroll to error
                container.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });

                return false;
            }

            // Add hidden field with selected size
            if (selectedSize) {
                const existingField = this.querySelector('input[name="selected_size"]');
                if (existingField) {
                    existingField.remove();
                }

                const sizeField = document.createElement('input');
                sizeField.type = 'hidden';
                sizeField.name = 'selected_size';
                sizeField.value = selectedSize;
                this.appendChild(sizeField);
            }
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
// CSS STYLES FOR SIZE BUTTONS AND ADMIN HIDING
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
    font-family: inherit;
    font-size: 14px;
    font-weight: 500;
    color: #333;
}

.required-asterisk {
    color: #d63638;
    margin-left: 3px;
}

/* Size Buttons Container */
.size-buttons-container {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 5px;
}

/* Default Button Style */
.size-buttons-style.style-default .size-button {
    padding: 8px 20px !important;
    border: 2px solid #ddd !important;
    background: transparent !important;
    color: #333 !important;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.2s ease;
    min-width: 50px;
    text-align: center;
    position: relative;
}

.size-buttons-style.style-default .size-button:hover:not(.out-of-stock) {
    border-color: #007cba !important;
    background: #f0f8ff !important;
}

.size-buttons-style.style-default .size-button.in-stock.selected {
    border-color: #007cba !important;
    background: #007cba !important;
    color: white !important;
}

.size-buttons-style.style-default .size-button.out-of-stock {
    opacity: 0.5;
    cursor: not-allowed;
    text-decoration: line-through;
    border-color: #ccc !important;
    background: #f5f5f5 !important;
}

/* Stock Badge */
.size-button .stock-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #4caf50;
    color: white;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 10px;
    min-width: 20px;
    text-align: center;
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
    position: relative;
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
    position: relative;
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
    font-family: inherit;
    font-size: 13px;
    color: #666;
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
    flex-wrap: wrap;
    gap: 5px;
}

.size-badge {
    background: #f0f0f0;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
}

.size-more {
    color: #999;
    font-style: italic;
    font-size: 11px;
}

/* Error State */
.size-buttons-container.size-error {
    border: 2px solid #d63638;
    padding: 10px;
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

.size-error-message {
    color: #d63638;
    font-size: 13px;
    margin-top: 5px;
    padding: 5px 10px;
    background: #ffebee;
    border-radius: 3px;
}

/* Size Stock Info */
.size-stock-info {
    margin: 15px 0;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 4px;
    border-left: 3px solid #007cba;
}

.size-stock-item {
    padding: 8px 15px;
    border-radius: 3px;
    min-width: 80px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

/* Loading State */
.size-button.checking-availability {
    opacity: 0.7;
    cursor: wait;
    position: relative;
}

.size-button.checking-availability::after {
    content: '';
    position: absolute;
    width: 12px;
    height: 12px;
    top: 50%;
    left: 50%;
    margin-top: -6px;
    margin-left: -6px;
    border: 2px solid #007cba;
    border-top-color: transparent;
    border-radius: 50%;
    animation: button-loading-spinner 0.6s linear infinite;
}

@keyframes button-loading-spinner {
    from {
        transform: rotate(0turn);
    }

    to {
        transform: rotate(1turn);
    }
}

/* Location Modal Styles */
.location-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 99999;
}

.location-modal.active {
    display: block;
}

.location-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
}

.location-box {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 40px;
    border-radius: 10px;
    text-align: center;
    min-width: 300px;
    box-shadow: 0 5px 30px rgba(0, 0, 0, 0.3);
}

.location-box h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #333;
    font-size: 24px;
}

.location-action {
    display: block;
    width: 100%;
    padding: 15px 20px;
    margin: 10px 0;
    border: 2px solid #ddd;
    background: white;
    border-radius: 5px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
}

.location-action:hover {
    border-color: #007cba;
    background: #f0f8ff;
}

.location-action[data-location="bd"]:hover {
    border-color: #006a4e;
}

.location-action[data-location="au"]:hover {
    border-color: #00008B;
}

/* Location Dropdown Styles */
.location-dropdown {
    position: relative;
    display: inline-block;
}

.current-location {
    background: transparent;
    border: none;
    padding: 8px 15px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    color: #333;
    display: flex;
    align-items: center;
    gap: 5px;
}

.dropdown-arrow {
    font-size: 10px;
    transition: transform 0.3s ease;
}

.location-dropdown.active .dropdown-arrow {
    transform: rotate(180deg);
}

.location-menu {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    list-style: none;
    margin: 0;
    padding: 5px 0;
    min-width: 150px;
    z-index: 1000;
}

.location-menu li {
    margin: 0;
    padding: 0;
}

.location-menu .dropdown-action {
    display: block;
    width: 100%;
    padding: 8px 15px;
    border: none;
    background: transparent;
    text-align: left;
    cursor: pointer;
    font-size: 13px;
    transition: background 0.2s ease;
}

.location-menu .dropdown-action:hover {
    background: #f5f5f5;
}
</style>
<?php
}

// ============================================
// STORE SELECTED SIZE IN SESSION
// ============================================

add_action('woocommerce_add_to_cart', 'store_selected_size_in_session', 20, 6);
function store_selected_size_in_session($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    if (isset($cart_item_data['selected_size']) && WC()->session) {
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
        if ($sizeButton.length && $sizeButton.hasClass('in-stock') && !$sizeButton.hasClass(
                'out-of-stock')) {
            $sizeButton.click();
        }
    }, 500);
});
</script>
<?php
    }
}

// Disable WooCommerce add to cart notice
add_filter('wc_add_to_cart_message_html', '__return_false');

?>