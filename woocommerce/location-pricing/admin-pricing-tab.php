<div id="location_pricing_tab" class="panel woocommerce_options_panel">
    <div class="options_group">
        <p class="form-field">
            <label for="_manage_stock_location">
                <input type="checkbox" id="_manage_stock_location" name="_manage_stock_location" value="yes"
                    <?php checked(get_post_meta(get_the_ID(), '_manage_stock_location', true), 'yes'); ?>>
                Manage stock separately for each location
            </label>
        </p>
    </div>

    <div class="location-pricing-section">
        <h3><span class="flag">🇧🇩</span> Bangladesh Pricing</h3>
        <div class="options_group">
            <?php
            woocommerce_wp_text_input([
                'id' => '_price_bd',
                'label' => 'Regular Price (BDT)',
                'type' => 'number',
                'custom_attributes' => ['step' => '0.01', 'min' => '0'],
                'data_type' => 'price'
            ]);
            
            woocommerce_wp_text_input([
                'id' => '_sale_price_bd',
                'label' => 'Sale Price (BDT)',
                'type' => 'number',
                'custom_attributes' => ['step' => '0.01', 'min' => '0'],
                'data_type' => 'price'
            ]);
            
            woocommerce_wp_text_input([
                'id' => '_stock_bd',
                'label' => 'Stock Quantity',
                'type' => 'number',
                'custom_attributes' => ['min' => '0', 'step' => '1']
            ]);
            ?>
        </div>
    </div>

    <div class="location-pricing-section">
        <h3><span class="flag">🇦🇺</span> Australia Pricing</h3>
        <div class="options_group">
            <?php
            woocommerce_wp_text_input([
                'id' => '_price_au',
                'label' => 'Regular Price (AUD)',
                'type' => 'number',
                'custom_attributes' => ['step' => '0.01', 'min' => '0'],
                'data_type' => 'price'
            ]);
            
            woocommerce_wp_text_input([
                'id' => '_sale_price_au',
                'label' => 'Sale Price (AUD)',
                'type' => 'number',
                'custom_attributes' => ['step' => '0.01', 'min' => '0'],
                'data_type' => 'price'
            ]);
            
            woocommerce_wp_text_input([
                'id' => '_stock_au',
                'label' => 'Stock Quantity',
                'type' => 'number',
                'custom_attributes' => ['min' => '0', 'step' => '1']
            ]);
            ?>
        </div>
    </div>

    <style>
    .location-pricing-section {
        background: #f8f9fa;
        padding: 15px;
        margin: 15px 0;
        border-radius: 4px;
        border-left: 4px solid #2271b1;
    }

    .location-pricing-section h3 {
        margin-top: 0;
        color: #2271b1;
    }

    .location-pricing-section .flag {
        font-size: 1.2em;
        margin-right: 10px;
    }
    </style>
</div>