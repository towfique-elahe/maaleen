<?php get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">

        <?php
    if (have_posts()) :
        while (have_posts()) : the_post();

            // Custom fields
            $short_description = get_post_meta(get_the_ID(), '_service_short_description', true);
            $price = get_post_meta(get_the_ID(), '_service_price', true);
            ?>

        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

            <header class="entry-header">
                <?php the_title('<h1 class="entry-title">', '</h1>'); ?>

                <?php if (has_post_thumbnail()) : ?>
                <div class="post-thumbnail">
                    <?php the_post_thumbnail('large'); ?>
                </div>
                <?php endif; ?>
            </header>

            <div class="entry-meta">
                <span class="posted-on"><?php echo get_the_date(); ?></span>
                <span class="byline"><?php the_author_posts_link(); ?></span>
            </div>

            <div class="entry-content">
                <?php the_content(); ?>
            </div>

            <?php if ($short_description) : ?>
            <div class="service-short-description">
                <h2>Short Description</h2>
                <p><?php echo esc_html($short_description); ?></p>
            </div>
            <?php endif; ?>

            <?php if ($price) : ?>
            <div class="service-price">
                <h2>Price</h2>
                <p>$<?php echo esc_html($price); ?></p>
            </div>
            <?php endif; ?>

            <footer class="entry-footer">
                <?php the_tags('<span class="tags-links">', ', ', '</span>'); ?>
                <?php edit_post_link(__('Edit', 'maaleen'), '<span class="edit-link">', '</span>'); ?>
            </footer>

        </article>

        <?php
        endwhile;
    else :
        echo '<p>' . __('Sorry, no service found.', 'maaleen') . '</p>';
    endif;
    ?>

    </main>
</div>

<?php get_sidebar(); ?>
<?php get_footer(); ?>