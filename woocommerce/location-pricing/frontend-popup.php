<?php if (!isset($_COOKIE['wc_user_location']) && !is_admin()): ?>
<div id="wc-location-modal" class="wc-location-modal">
    <div class="wc-location-modal__overlay"></div>
    <div class="wc-location-modal__content">
        <button class="wc-location-modal__close" aria-label="Close modal">&times;</button>

        <div class="wc-location-modal__header">
            <h2>Select Your Location</h2>
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
                    <p>Local shipping available</p>
                </div>
                <button class="wc-location-option__select" onclick="setWCLocation('au')">
                    Select Australia
                </button>
            </div>
        </div>

        <div class="wc-location-modal__footer">
            <p class="wc-location-modal__note">
                <small>You can change your location anytime from the header.</small>
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Location Indicator (Header) -->
<?php if (get_theme_mod('wc_show_location_indicator', true)): ?>
<div class="wc-location-indicator">
    <?php 
    $current = wc_get_user_location();
    $locations = [
        'bd' => ['flag' => '🇧🇩', 'name' => 'Bangladesh'],
        'au' => ['flag' => '🇦🇺', 'name' => 'Australia']
    ];
    ?>
    <span class="wc-current-location">
        <?php echo $locations[$current]['flag']; ?>
        <span class="wc-location-name"><?php echo $locations[$current]['name']; ?></span>
    </span>
    <div class="wc-location-dropdown">
        <?php foreach ($locations as $code => $loc): ?>
        <?php if ($code !== $current): ?>
        <a href="#" onclick="setWCLocation('<?php echo $code; ?>'); return false;">
            <?php echo $loc['flag']; ?> <?php echo $loc['name']; ?>
        </a>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>