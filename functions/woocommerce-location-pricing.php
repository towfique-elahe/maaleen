<?php
/**
 * WooCommerce Location Based Pricing & Stock
 * Theme Integration Version 2.0
 */

if (!defined('ABSPATH')) exit;

/* -------------------------------------------------
 * LOCATION HANDLING (Enhanced)
 * ------------------------------------------------- */

function wc_get_user_location() {
    // Check multiple sources with priority
    if (isset($_GET['force_location'])) {
        $location = sanitize_text_field($_GET['force_location']);
        if (in_array($location, ['bd', 'au'])) {
            if (WC()->session) {
                WC()->session->set('wc_user_location', $location);
            }
            setcookie('wc_user_location', $location, time() + (86400 * 30), '/');
            return $location;
        }
    }
    
    if (WC()->session && WC()->session->get('wc_user_location')) {
        return WC()->session->get('wc_user_location');
    }
    
    if (isset($_COOKIE['wc_user_location'])) {
        $location = sanitize_text_field($_COOKIE['wc_user_location']);
        if (in_array($location, ['bd', 'au'])) {
            if (WC()->session) {
                WC()->session->set('wc_user_location', $location);
            }
            return $location;
        }
    }
    
    // Default based on IP detection (simplified)
    return wc_detect_location_by_ip() ?: 'bd';
}

function wc_detect_location_by_ip() {
    // You can integrate with an IP geolocation API here
    // For now, returning null will use default
    return null;
}

add_action('init', function () {
    if (!session_id() && !headers_sent()) {
        session_start();
    }
    
    // Handle location switching
    if (isset($_POST['wc_switch_location'])) {
        $location = sanitize_text_field($_POST['wc_location']);
        if (in_array($location, ['bd', 'au'])) {
            if (WC()->session) {
                WC()->session->set('wc_user_location', $location);
            }
            setcookie('wc_user_location', $location, time() + (86400 * 30), '/');
            
            // Clear cart if switching between locations with different pricing
            if (get_theme_mod('wc_location_clear_cart', true)) {
                WC()->cart->empty_cart();
            }
            
            wp_redirect(remove_query_arg(['force_location']));
            exit;
        }
    }
    
    // Sync cookie with session
    if (isset($_COOKIE['wc_user_location']) && WC()->session) {
        WC()->session->set('wc_user_location', sanitize_text_field($_COOKIE['wc_user_location']));
    }
});

/* -------------------------------------------------
 * ADMIN UI – PRODUCT DATA TAB (Improved)
 * ------------------------------------------------- */

add_filter('woocommerce_product_data_tabs', function ($tabs) {
    $tabs['location_pricing'] = [
        'label'    => 'Location Pricing',
        'target'   => 'location_pricing_tab',
        'priority' => 70,
        'class'    => ['show_if_simple', 'show_if_variable']
    ];
    return $tabs;
});

add_action('woocommerce_product_data_panels', function () {
    include get_template_directory() . '/woocommerce/location-pricing/admin-pricing-tab.php';
});

/* -------------------------------------------------
 * SAVE PRODUCT META
 * ------------------------------------------------- */

add_action('woocommerce_process_product_meta', function ($post_id) {
    $fields = [
        '_price_bd',
        '_price_au',
        '_stock_bd',
        '_stock_au',
        '_sale_price_bd',
        '_sale_price_au',
        '_manage_stock_location'
    ];
    
    foreach ($fields as $key) {
        if (isset($_POST[$key])) {
            update_post_meta($post_id, $key, wc_clean($_POST[$key]));
        }
    }
    
    // Sync with WooCommerce stock if location stock is managed
    if (isset($_POST['_manage_stock_location']) && $_POST['_manage_stock_location'] === 'yes') {
        $location = wc_get_user_location();
        $stock_key = $location === 'au' ? '_stock_au' : '_stock_bd';
        $stock = isset($_POST[$stock_key]) ? intval($_POST[$stock_key]) : 0;
        
        update_post_meta($post_id, '_manage_stock', 'yes');
        update_post_meta($post_id, '_stock', $stock);
        wc_update_product_stock_status($post_id, $stock > 0 ? 'instock' : 'outofstock');
    }
});

/* -------------------------------------------------
 * VARIABLE PRODUCT SUPPORT (Enhanced)
 * ------------------------------------------------- */

add_action('woocommerce_variation_options_pricing', function ($loop, $variation_data, $variation) {
    ?>
<div class="location-pricing-variation">
    <h4>Location Pricing</h4>
    <div class="form-row">
        <?php
            woocommerce_wp_text_input([
                'id'            => "_price_bd_{$loop}",
                'name'          => "_price_bd[{$loop}]",
                'label'         => 'BD Price (' . get_woocommerce_currency_symbol('BDT') . ')',
                'value'         => get_post_meta($variation->ID, '_price_bd', true),
                'wrapper_class' => 'form-row form-row-first',
                'data_type'     => 'price',
                'placeholder'   => 'Regular price'
            ]);
            
            woocommerce_wp_text_input([
                'id'            => "_sale_price_bd_{$loop}",
                'name'          => "_sale_price_bd[{$loop}]",
                'label'         => 'BD Sale Price',
                'value'         => get_post_meta($variation->ID, '_sale_price_bd', true),
                'wrapper_class' => 'form-row form-row-last',
                'data_type'     => 'price',
                'placeholder'   => 'Sale price'
            ]);
            ?>
    </div>

    <div class="form-row">
        <?php
            woocommerce_wp_text_input([
                'id'            => "_stock_bd_{$loop}",
                'name'          => "_stock_bd[{$loop}]",
                'label'         => 'BD Stock',
                'value'         => get_post_meta($variation->ID, '_stock_bd', true),
                'wrapper_class' => 'form-row form-row-first',
                'type'          => 'number',
                'custom_attributes' => ['min' => '0', 'step' => '1']
            ]);
            ?>
    </div>

    <hr style="margin: 15px 0;">

    <div class="form-row">
        <?php
            woocommerce_wp_text_input([
                'id'            => "_price_au_{$loop}",
                'name'          => "_price_au[{$loop}]",
                'label'         => 'AU Price (' . get_woocommerce_currency_symbol('AUD') . ')',
                'value'         => get_post_meta($variation->ID, '_price_au', true),
                'wrapper_class' => 'form-row form-row-first',
                'data_type'     => 'price',
                'placeholder'   => 'Regular price'
            ]);
            
            woocommerce_wp_text_input([
                'id'            => "_sale_price_au_{$loop}",
                'name'          => "_sale_price_au[{$loop}]",
                'label'         => 'AU Sale Price',
                'value'         => get_post_meta($variation->ID, '_sale_price_au', true),
                'wrapper_class' => 'form-row form-row-last',
                'data_type'     => 'price',
                'placeholder'   => 'Sale price'
            ]);
            ?>
    </div>

    <div class="form-row">
        <?php
            woocommerce_wp_text_input([
                'id'            => "_stock_au_{$loop}",
                'name'          => "_stock_au[{$loop}]",
                'label'         => 'AU Stock',
                'value'         => get_post_meta($variation->ID, '_stock_au', true),
                'wrapper_class' => 'form-row form-row-first',
                'type'          => 'number',
                'custom_attributes' => ['min' => '0', 'step' => '1']
            ]);
            ?>
    </div>
</div>
<?php
}, 10, 3);

add_action('woocommerce_save_product_variation', function ($variation_id, $i) {
    $fields = [
        '_price_bd',
        '_price_au',
        '_stock_bd',
        '_stock_au',
        '_sale_price_bd',
        '_sale_price_au'
    ];
    
    foreach ($fields as $key) {
        $post_key = "{$key}[{$i}]";
        if (isset($_POST[$post_key])) {
            update_post_meta($variation_id, $key, wc_clean($_POST[$post_key]));
        }
    }
}, 10, 2);

/* -------------------------------------------------
 * DYNAMIC PRICE LOGIC (Enhanced with Sale Support)
 * ------------------------------------------------- */

add_filter('woocommerce_product_get_price', 'wc_location_price', 10, 2);
add_filter('woocommerce_product_get_regular_price', 'wc_location_price', 10, 2);
add_filter('woocommerce_product_variation_get_price', 'wc_location_price', 10, 2);
add_filter('woocommerce_product_variation_get_regular_price', 'wc_location_price', 10, 2);

function wc_location_price($price, $product) {
    $location = wc_get_user_location();
    
    // Check for sale price first
    $sale_key = $location === 'au' ? '_sale_price_au' : '_sale_price_bd';
    $regular_key = $location === 'au' ? '_price_au' : '_price_bd';
    
    $sale_price = get_post_meta($product->get_id(), $sale_key, true);
    $regular_price = get_post_meta($product->get_id(), $regular_key, true);
    
    // Return sale price if set and valid, otherwise regular price
    if ($sale_price !== '' && $sale_price < $regular_price) {
        return $sale_price;
    }
    
    return $regular_price !== '' ? $regular_price : $price;
}

// Display location-based sale price
add_filter('woocommerce_get_price_html', function ($price_html, $product) {
    $location = wc_get_user_location();
    $regular_key = $location === 'au' ? '_price_au' : '_price_bd';
    $sale_key = $location === 'au' ? '_sale_price_au' : '_sale_price_bd';
    
    $regular_price = get_post_meta($product->get_id(), $regular_key, true);
    $sale_price = get_post_meta($product->get_id(), $sale_key, true);
    
    if ($sale_price && $regular_price && $sale_price < $regular_price) {
        $price_html = wc_format_sale_price($regular_price, $sale_price);
    }
    
    return $price_html;
}, 10, 2);

/* -------------------------------------------------
 * LOCATION-AWARE STOCK (Enhanced)
 * ------------------------------------------------- */

add_filter('woocommerce_product_get_stock_quantity', function ($qty, $product) {
    $location = wc_get_user_location();
    $key = $location === 'au' ? '_stock_au' : '_stock_bd';
    
    $stock = get_post_meta($product->get_id(), $key, true);
    return $stock !== '' ? (int) $stock : $qty;
}, 10, 2);

add_filter('woocommerce_product_get_manage_stock', function ($manage_stock, $product) {
    // If location-based stock is set, manage stock
    $location = wc_get_user_location();
    $key = $location === 'au' ? '_stock_au' : '_stock_bd';
    $stock = get_post_meta($product->get_id(), $key, true);
    
    return $stock !== '' ? true : $manage_stock;
}, 10, 2);

/* -------------------------------------------------
 * ADD TO CART VALIDATION (Enhanced)
 * ------------------------------------------------- */

add_filter('woocommerce_add_to_cart_validation', function ($passed, $product_id, $qty, $variation_id = null, $variations = null) {
    $id = $variation_id ?: $product_id;
    $location = wc_get_user_location();
    $key = $location === 'au' ? '_stock_au' : '_stock_bd';
    
    $stock = (int) get_post_meta($id, $key, true);
    $product = wc_get_product($id);
    
    if ($stock !== '' && $qty > $stock) {
        $product_name = $product->get_name();
        wc_add_notice(
            sprintf(__('Sorry, only %d "%s" available for your location.', 'your-theme'), $stock, $product_name),
            'error'
        );
        return false;
    }
    
    return true;
}, 10, 5);

/* -------------------------------------------------
 * LOCATION SWITCHER WIDGET/SHORTCODE
 * ------------------------------------------------- */

function wc_location_switcher_shortcode($atts) {
    $atts = shortcode_atts([
        'show_labels' => true,
        'style' => 'dropdown' // or 'buttons'
    ], $atts);
    
    $current_location = wc_get_user_location();
    $locations = [
        'bd' => [
            'name' => 'Bangladesh',
            'flag' => '🇧🇩',
            'currency' => 'BDT'
        ],
        'au' => [
            'name' => 'Australia',
            'flag' => '🇦🇺',
            'currency' => 'AUD'
        ]
    ];
    
    ob_start();
    ?>
<div class="wc-location-switcher style-<?php echo esc_attr($atts['style']); ?>">
    <form method="post" class="wc-location-form">
        <?php if ($atts['style'] === 'dropdown'): ?>
        <select name="wc_location" class="wc-location-select" onchange="this.form.submit()">
            <?php foreach ($locations as $code => $loc): ?>
            <option value="<?php echo esc_attr($code); ?>" <?php selected($current_location, $code); ?>>
                <?php echo $loc['flag'] . ' ' . $loc['name']; ?>
            </option>
            <?php endforeach; ?>
        </select>
        <?php else: ?>
        <div class="wc-location-buttons">
            <?php foreach ($locations as $code => $loc): ?>
            <button type="submit" name="wc_location" value="<?php echo esc_attr($code); ?>"
                class="wc-location-btn <?php echo $current_location === $code ? 'active' : ''; ?>">
                <span class="flag"><?php echo $loc['flag']; ?></span>
                <?php if ($atts['show_labels']): ?>
                <span class="label"><?php echo $loc['name']; ?></span>
                <?php endif; ?>
            </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <input type="hidden" name="wc_switch_location" value="1">
    </form>
</div>
<?php
    return ob_get_clean();
}
add_shortcode('wc_location_switcher', 'wc_location_switcher_shortcode');

/* -------------------------------------------------
 * SAVE LOCATION INTO ORDER META
 * ------------------------------------------------- */

add_action('woocommerce_checkout_create_order', function ($order) {
    $order->update_meta_data('_wc_user_location', wc_get_user_location());
    $order->update_meta_data('_wc_user_location_currency', wc_get_user_location() === 'au' ? 'AUD' : 'BDT');
});

/* -------------------------------------------------
 * REDUCE STOCK AFTER ORDER (Enhanced)
 * ------------------------------------------------- */

add_action('woocommerce_order_status_processing', 'wc_reduce_location_stock', 10, 1);
add_action('woocommerce_order_status_completed', 'wc_reduce_location_stock', 10, 1);

function wc_reduce_location_stock($order_id) {
    $order = wc_get_order($order_id);
    $location = $order->get_meta('_wc_user_location') ?: 'bd';
    $key = $location === 'au' ? '_stock_au' : '_stock_bd';
    
    foreach ($order->get_items() as $item) {
        $id  = $item->get_variation_id() ?: $item->get_product_id();
        $qty = $item->get_quantity();
        
        $stock = (int) get_post_meta($id, $key, true);
        if ($stock !== '') {
            $new_stock = max(0, $stock - $qty);
            update_post_meta($id, $key, $new_stock);
            
            // Sync with WooCommerce stock if needed
            if (get_post_meta($id, '_manage_stock_location', true) === 'yes') {
                wc_update_product_stock($id, $new_stock);
            }
        }
    }
}

/* -------------------------------------------------
 * CURRENCY SWITCH (Enhanced)
 * ------------------------------------------------- */

add_filter('woocommerce_currency', function ($currency) {
    return wc_get_user_location() === 'au' ? 'AUD' : 'BDT';
});

add_filter('woocommerce_currency_symbol', function ($symbol, $currency) {
    $location = wc_get_user_location();
    if ($location === 'au' && $currency === 'AUD') {
        return 'A$';
    } elseif ($location === 'bd' && $currency === 'BDT') {
        return '৳';
    }
    return $symbol;
}, 10, 2);

/* -------------------------------------------------
 * FRONTEND POPUP MODAL
 * ------------------------------------------------- */

/* -------------------------------------------------
 * LOCATION SELECTOR MODAL (Always available, hidden by default)
 * ------------------------------------------------- */

add_action('wp_footer', function() {
    // Always output the modal HTML, but hide it initially with CSS
    ?>
<div id="wc-location-modal" class="wc-location-modal" style="display: none;">
    <div class="wc-location-modal__overlay"></div>
    <div class="wc-location-modal__content">
        <button class="wc-location-modal__close" aria-label="Close modal">&times;</button>

        <div class="wc-location-modal__header">
            <h2>🌍 Change Your Location</h2>
            <p>Choose your location to see accurate pricing and stock availability</p>
        </div>

        <div class="wc-location-modal__options">
            <div class="wc-location-option" data-location="bd">
                <div class="wc-location-option__flag">🇧🇩</div>
                <div class="wc-location-option__details">
                    <h3>Bangladesh</h3>
                    <p>Prices in BDT (৳)</p>
                    <p>Local shipping available</p>
                </div>
                <button class="wc-location-option__select" onclick="setWCLocation('bd')">
                    Select Bangladesh
                </button>
            </div>

            <div class="wc-location-option" data-location="au">
                <div class="wc-location-option__flag">🇦🇺</div>
                <div class="wc-location-option__details">
                    <h3>Australia</h3>
                    <p>Prices in AUD (A$)</p>
                    <p>International shipping</p>
                </div>
                <button class="wc-location-option__select" onclick="setWCLocation('au')">
                    Select Australia
                </button>
            </div>
        </div>

        <div class="wc-location-modal__footer">
            <p class="wc-location-modal__note">
                <small>Your selection will update prices and stock availability immediately.</small>
            </p>
        </div>
    </div>
</div>

<?php if (!isset($_COOKIE['wc_user_location']) && !is_admin()): ?>
<script type="text/javascript">
// Show modal on first visit after page load
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        if (!document.cookie.match(/wc_user_location/)) {
            document.getElementById('wc-location-modal').style.display = 'flex';
            document.body.classList.add('wc-location-modal-open');
        }
    }, 1000);
});
</script>
<?php endif;
});

/* -------------------------------------------------
 * ENQUEUE STYLES AND SCRIPTS
 * ------------------------------------------------- */

add_action('wp_enqueue_scripts', function () {
    // Frontend styles
    wp_enqueue_style(
        'wc-location-pricing',
        get_template_directory_uri() . '/woocommerce/location-pricing/assets/location-pricing.css',
        [],
        '2.0'
    );
    
    // Frontend scripts
    wp_enqueue_script(
        'wc-location-pricing',
        get_template_directory_uri() . '/woocommerce/location-pricing/assets/location-pricing.js',
        ['jquery'],
        '2.0',
        true
    );
    
    // Localize script for AJAX
    wp_localize_script('wc-location-pricing', 'wc_location_data', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wc_location_nonce'),
        'current_location' => wc_get_user_location(),
        'strings' => [
            'switching_location' => __('Switching location...', 'your-theme'),
            'location_updated' => __('Location updated!', 'your-theme')
        ]
    ]);
});

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook === 'post.php' || $hook === 'post-new.php') {
        wp_enqueue_style(
            'wc-location-admin',
            get_template_directory_uri() . '/woocommerce/location-pricing/assets/location-admin.css',
            [],
            '2.0'
        );
    }
});

/* -------------------------------------------------
 * AJAX HANDLERS FOR DYNAMIC UPDATES
 * ------------------------------------------------- */

add_action('wp_ajax_wc_switch_location', 'wc_ajax_switch_location');
add_action('wp_ajax_nopriv_wc_switch_location', 'wc_ajax_switch_location');

function wc_ajax_switch_location() {
    check_ajax_referer('wc_location_nonce', 'nonce');
    
    $location = sanitize_text_field($_POST['location']);
    if (!in_array($location, ['bd', 'au'])) {
        wp_die('Invalid location');
    }
    
    // Set session and cookie
    if (WC()->session) {
        WC()->session->set('wc_user_location', $location);
    }
    setcookie('wc_user_location', $location, time() + (86400 * 30), '/');
    
    // Clear cart if enabled
    $clear_cart = get_theme_mod('wc_location_clear_cart', true);
    if ($clear_cart && WC()->cart && !WC()->cart->is_empty()) {
        WC()->cart->empty_cart();
        $cart_cleared = true;
    }
    
    wp_send_json_success([
        'location' => $location,
        'message' => __('Location updated successfully!', 'your-theme'),
        'clear_cart' => $cart_cleared ?? false,
        'redirect' => remove_query_arg(['force_location'])
    ]);
}

// Add endpoint for price updates
add_action('wp_ajax_wc_get_updated_prices', 'wc_ajax_get_updated_prices');
add_action('wp_ajax_nopriv_wc_get_updated_prices', 'wc_ajax_get_updated_prices');

function wc_ajax_get_updated_prices() {
    $prices = [];
    $product_ids = isset($_GET['product_ids']) ? array_map('intval', explode(',', $_GET['product_ids'])) : [];
    
    if (!empty($product_ids)) {
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $prices[$product_id] = $product->get_price_html();
            }
        }
    }
    
    wp_send_json_success(['prices' => $prices]);
}

// Add price update data to localized script
add_filter('wp_enqueue_scripts', function () {
    wp_localize_script('wc-location-pricing', 'wc_location_data', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'update_prices_url' => add_query_arg([
            'action' => 'wc_get_updated_prices',
            'nonce' => wp_create_nonce('wc_prices_nonce')
        ], admin_url('admin-ajax.php')),
        'nonce' => wp_create_nonce('wc_location_nonce'),
        'current_location' => wc_get_user_location(),
        'strings' => [
            'switching_location' => __('Switching location...', 'your-theme'),
            'location_updated' => __('Location updated!', 'your-theme')
        ]
    ]);
});

/* -------------------------------------------------
 * ADMIN SETTINGS PAGE
 * ------------------------------------------------- */

add_action('admin_menu', function () {
    add_submenu_page(
        'woocommerce',
        'Location Pricing Settings',
        'Location Pricing',
        'manage_options',
        'wc-location-settings',
        'wc_location_settings_page'
    );
});

function wc_location_settings_page() {
    ?>
<div class="wrap">
    <h1>Location Pricing Settings</h1>
    <form method="post" action="options.php">
        <?php settings_fields('wc_location_settings'); ?>
        <?php do_settings_sections('wc_location_settings'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">Clear Cart on Location Change</th>
                <td>
                    <label>
                        <input type="checkbox" name="wc_location_clear_cart" value="1"
                            <?php checked(get_theme_mod('wc_location_clear_cart', true)); ?>>
                        Clear shopping cart when user changes location
                    </label>
                    <p class="description">Prevents pricing conflicts when switching between locations.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Default Location</th>
                <td>
                    <select name="wc_default_location">
                        <option value="bd" <?php selected(get_theme_mod('wc_default_location', 'bd'), 'bd'); ?>>
                            Bangladesh
                        </option>
                        <option value="au" <?php selected(get_theme_mod('wc_default_location', 'bd'), 'au'); ?>>
                            Australia
                        </option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">Show Location Indicator</th>
                <td>
                    <label>
                        <input type="checkbox" name="wc_show_location_indicator" value="1"
                            <?php checked(get_theme_mod('wc_show_location_indicator', true)); ?>>
                        Show location flag/name in header
                    </label>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>
<?php
}

add_action('admin_init', function () {
    register_setting('wc_location_settings', 'wc_location_clear_cart');
    register_setting('wc_location_settings', 'wc_default_location');
    register_setting('wc_location_settings', 'wc_show_location_indicator');
});

/* -------------------------------------------------
 * WOOCOMMERCE EMAIL CUSTOMIZATION
 * ------------------------------------------------- */

add_action('woocommerce_email_order_details', function ($order, $sent_to_admin, $plain_text, $email) {
    $location = $order->get_meta('_wc_user_location');
    if ($location) {
        $location_name = $location === 'au' ? 'Australia' : 'Bangladesh';
        echo '<p><strong>Order Location:</strong> ' . esc_html($location_name) . '</p>';
    }
}, 10, 4);