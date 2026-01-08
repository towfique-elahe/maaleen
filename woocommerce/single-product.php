<?php
defined('ABSPATH') || exit;

get_header('shop');

global $post, $product;

// ENSURE product object is set correctly
if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
    $product = wc_get_product( $post->ID );
}

if ( ! $product ) {
    return;
}

$location = wc_get_user_location();
$currency_symbol = $location === 'au' ? 'A$' : '৳';
$currency_code = $location === 'au' ? 'AUD' : 'BDT';
$country_name = $location === 'au' ? 'Australia' : 'Bangladesh';

// Get location-specific stock
$stock_key = $location === 'au' ? '_stock_au' : '_stock_bd';
$location_stock = get_post_meta($product->get_id(), $stock_key, true);
$is_in_stock_location = $location_stock !== '' ? (int)$location_stock > 0 : $product->is_in_stock();

// Get location-specific prices
$price_key = $location === 'au' ? '_price_au' : '_price_bd';
$sale_price_key = $location === 'au' ? '_sale_price_au' : '_sale_price_bd';
$location_price = get_post_meta($product->get_id(), $price_key, true);
$location_sale_price = get_post_meta($product->get_id(), $sale_price_key, true);
?>

<div class="wc-location-product">

    <!-- Location Indicator with Change Button -->
    <div class="wc-product-location-header">
        <div class="wc-current-location-badge">
            <span class="wc-location-flag">
                <?php echo $location === 'au' ? '🇦🇺' : '🇧🇩'; ?>
            </span>
            <span class="wc-location-info">
                <strong><?php echo esc_html($country_name); ?></strong>
                <span
                    class="wc-currency-info"><?php echo esc_html($currency_code) . ' (' . esc_html($currency_symbol) . ')'; ?></span>
            </span>
        </div>
        <button type="button" id="wc-change-location-trigger" class="wc-change-location-btn">
            <span class="dashicons dashicons-location"></span>
            <?php _e('Change Location', 'your-theme'); ?>
        </button>
    </div>

    <div class="product-main">

        <!-- Product Images -->
        <div class="product-gallery">
            <?php do_action('woocommerce_before_single_product_summary'); ?>
        </div>

        <!-- Product Summary -->
        <div class="product-summary">

            <h1 class="product-title"><?php the_title(); ?></h1>

            <!-- Product Rating -->
            <?php if (wc_review_ratings_enabled()) : ?>
            <div class="product-rating">
                <?php echo wc_get_rating_html($product->get_average_rating()); ?>
                <?php if ($product->get_review_count()) : ?>
                <span class="review-count">
                    (<?php echo esc_html($product->get_review_count()); ?>)
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Price (LOCATION-AWARE) -->
            <div class="product-price" data-product-id="<?php echo esc_attr($product->get_id()); ?>">
                <?php if ($location_sale_price && $location_sale_price < $location_price) : ?>
                <div class="price-wrapper">
                    <del class="regular-price">
                        <span class="woocommerce-Price-amount amount">
                            <span class="woocommerce-Price-currencySymbol"><?php echo $currency_symbol; ?></span>
                            <?php echo number_format(floatval($location_price), 2); ?>
                        </span>
                    </del>
                    <ins class="sale-price">
                        <span class="woocommerce-Price-amount amount">
                            <span class="woocommerce-Price-currencySymbol"><?php echo $currency_symbol; ?></span>
                            <?php echo number_format(floatval($location_sale_price), 2); ?>
                        </span>
                    </ins>
                </div>
                <?php elseif ($location_price !== '') : ?>
                <div class="price-wrapper">
                    <span class="regular-price">
                        <span class="woocommerce-Price-amount amount">
                            <span class="woocommerce-Price-currencySymbol"><?php echo $currency_symbol; ?></span>
                            <?php echo number_format(floatval($location_price), 2); ?>
                        </span>
                    </span>
                </div>
                <?php else : ?>
                <?php echo $product->get_price_html(); ?>
                <?php endif; ?>

                <div class="price-note">
                    <small>
                        <?php printf(__('Price for %s customers', 'your-theme'), $country_name); ?>
                    </small>
                </div>
            </div>

            <!-- Stock Status -->
            <div class="product-stock">
                <?php if ($is_in_stock_location): ?>
                <div class="stock-status in-stock">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php if ($location_stock !== '' && is_numeric($location_stock)): ?>
                    <span class="stock-text">
                        <?php printf(__('%d available in %s', 'your-theme'), intval($location_stock), $country_name); ?>
                    </span>
                    <?php if ($location_stock < 10): ?>
                    <span class="low-stock-warning">
                        <?php _e('Low stock!', 'your-theme'); ?>
                    </span>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="stock-text">
                        <?php printf(__('In stock for %s', 'your-theme'), $country_name); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="stock-status out-of-stock">
                    <span class="dashicons dashicons-dismiss"></span>
                    <span class="stock-text">
                        <?php printf(__('Out of stock in %s', 'your-theme'), $country_name); ?>
                    </span>
                </div>
                <?php if ($location === 'au'): ?>
                <div class="alternative-location-notice">
                    <p>
                        <strong><?php _e('Available in Bangladesh:', 'your-theme'); ?></strong>
                        <?php
                                $bd_stock = get_post_meta($product->get_id(), '_stock_bd', true);
                                if ($bd_stock !== '' && intval($bd_stock) > 0) {
                                    printf(__('This product is available in Bangladesh with %d units in stock.', 'your-theme'), intval($bd_stock));
                                }
                                ?>
                    </p>
                </div>
                <?php else: ?>
                <div class="alternative-location-notice">
                    <p>
                        <strong><?php _e('Available in Australia:', 'your-theme'); ?></strong>
                        <?php
                                $au_stock = get_post_meta($product->get_id(), '_stock_au', true);
                                if ($au_stock !== '' && intval($au_stock) > 0) {
                                    printf(__('This product is available in Australia with %d units in stock.', 'your-theme'), intval($au_stock));
                                }
                                ?>
                    </p>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Short Description -->
            <?php if ($post->post_excerpt): ?>
            <div class="product-excerpt">
                <?php echo apply_filters('woocommerce_short_description', $post->post_excerpt); ?>
            </div>
            <?php endif; ?>

            <!-- Add to Cart -->
            <div class="product-cart">
                <?php
                // Show custom message if out of stock for this location
                if (!$is_in_stock_location) {
                    echo '<div class="location-out-of-stock-message">';
                    printf(
                        __('This product is currently not available in %s. Please check other locations or contact us.', 'your-theme'),
                        $country_name
                    );
                    echo '</div>';
                }
                
                // Only show add to cart if in stock
                if ($is_in_stock_location) {
                    do_action('woocommerce_single_product_summary');
                }
                ?>
            </div>

            <!-- Product Meta -->
            <div class="product-meta">
                <?php do_action('woocommerce_product_meta_start'); ?>

                <?php if (wc_product_sku_enabled() && ($sku = $product->get_sku())): ?>
                <div class="sku-wrapper">
                    <span class="sku-label"><?php esc_html_e('SKU:', 'your-theme'); ?></span>
                    <span class="sku"><?php echo esc_html($sku); ?></span>
                </div>
                <?php endif; ?>

                <?php echo wc_get_product_category_list($product->get_id(), ', ', '<div class="posted-in"><span class="category-label">' . _n('Category:', 'Categories:', count($product->get_category_ids()), 'your-theme') . '</span> ', '</div>'); ?>
                <?php echo wc_get_product_tag_list($product->get_id(), ', ', '<div class="tagged-as"><span class="tag-label">' . _n('Tag:', 'Tags:', count($product->get_tag_ids()), 'your-theme') . '</span> ', '</div>'); ?>

                <?php do_action('woocommerce_product_meta_end'); ?>
            </div>

        </div>
    </div>

    <!-- Product Tabs -->
    <div class="product-tabs">
        <?php do_action('woocommerce_after_single_product_summary'); ?>
    </div>

    <!-- Related Products -->
    <?php
    $related_products = wc_get_related_products($product->get_id());
    if ($related_products): ?>
    <div class="related-products-section">
        <h3><?php _e('Related Products', 'your-theme'); ?></h3>
        <div class="related-products">
            <?php
                $args = array(
                    'post_type' => 'product',
                    'ignore_sticky_posts' => 1,
                    'no_found_rows' => 1,
                    'posts_per_page' => 4,
                    'post__in' => $related_products,
                    'post__not_in' => array($product->get_id()),
                );
                $products = new WP_Query($args);
                if ($products->have_posts()):
                    while ($products->have_posts()): $products->the_post();
                        global $product;
                        ?>
            <div class="related-product">
                <a href="<?php the_permalink(); ?>">
                    <?php the_post_thumbnail('medium'); ?>
                    <h4><?php the_title(); ?></h4>
                    <div class="price">
                        <?php echo $product->get_price_html(); ?>
                    </div>
                </a>
            </div>
            <?php
                    endwhile;
                endif;
                wp_reset_postdata();
                ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php get_footer('shop'); ?>