## MAALEEN

- Theme Name: Maaleen
- Theme URI:
- Author: Towfique Elahe
- Author URI: https://towfiqueelahe.com/
- Description: A custom WordPress theme compatible with Elementor and WooCommerce with dual-country (Bangladesh & Australia) support for pricing, inventory, and size-based stock management.
- Version: 1.7
- License: GNU General Public License v3 or later
- License URI: http://www.gnu.org/licenses/gpl-3.0.html
- Text Domain: maaleen
- Tags: custom, custom-theme, store, cloth-store, woocommerce, multi-currency, inventory-management

## FEATURES

- **Dual Country Support**: Switch between Bangladesh (BDT) and Australia (AUD)
- **Location-Based Pricing**: Set different prices for each country
- **Size Variant Inventory**: Track stock for individual sizes (XS, S, M, L, XL, XXL, XXXL)
- **Automatic Stock Reduction**: Reduces size-specific stock when orders are placed
- **Currency Switching**: Automatically changes currency symbol and format based on location
- **Location Modal**: First-time visitors see a location selection modal
- **Clean Admin Interface**: Removes unnecessary product data, attributes, brands, and custom fields

## SHORTCODES

### Location Dropdown

You can add the location switcher dropdown anywhere using these shortcodes:

**Basic dropdown (without currency symbol):**

```
[location_dropdown]
```

**Dropdown with currency symbol:**

```
[location_dropdown show_currency="true"]
```

**Different styles:**

```
[location_dropdown style="minimal"]
[location_dropdown style="dark"]
[location_dropdown style="default" show_currency="true"]
```

**Style Options:**

- `default` - Standard dropdown with borders
- `minimal` - Clean, minimal design
- `dark` - Dark themed dropdown

### Product Size Selector

Add size selection buttons to product pages:

```
[product_size_selector]
```

**With options:**

```
[product_size_selector product_id="123" label="Choose Size:" required="yes" button_style="rounded" show_out_of_stock="yes"]
```

**Options:**

- `product_id`: (optional) Product ID, defaults to current product
- `label`: Label text above size buttons (default: "Select Size:")
- `required`: Make size selection required (yes/no, default: "yes")
- `show_out_of_stock`: Show out of stock sizes (yes/no, default: "yes")
- `button_style`: Button style - default, rounded, or minimal
- `field_name`: Hidden field name (default: "selected_size")

### Product Card Sizes

Display sizes on product cards/archives:

```
[product_card_sizes]
```

**With options:**

```
[product_card_sizes product_id="123" limit="4" style="badges" show_label="yes" separator=" | "]
```

**Options:**

- `product_id`: (optional) Product ID, defaults to current product
- `limit`: Number of sizes to show (default: 4)
- `style`: Display style - inline (default), badges, or simple
- `show_label`: Show "Sizes:" label (yes/no, default: "no")
- `separator`: Separator between sizes (default: " • ")

**Style Examples:**

Inline (default):

```
Sizes: S • M • L • XL +2 more
```

Badges:

```
Sizes: [S] [M] [L] [XL] [+2]
```

Simple:

```
Sizes: S, M, L, XL +2 more
```

## USAGE EXAMPLES

### In PHP Templates

```php
<?php echo do_shortcode('[location_dropdown show_currency="true"]'); ?>
<?php echo do_shortcode('[product_size_selector]'); ?>
<?php echo do_shortcode('[product_card_sizes limit="3" style="badges"]'); ?>
```

### In Elementor

1. Add a "Shortcode" widget
2. Paste any of the shortcodes above
3. Customize as needed

### In WordPress Editor (Gutenberg)

1. Add a "Shortcode" block
2. Enter your desired shortcode

## ADMIN PRODUCT EDIT PAGE

The theme simplifies the product edit interface by showing only:

1. **Location-Based Prices** - Set Bangladesh and Australia prices
2. **Product Size Variations** - Select available sizes (checkboxes)
3. **Size Variant Inventory** - Set stock quantities for each size per country
4. **Size Stock Change Logs** - View stock adjustment history

All standard WooCommerce product data tabs (Inventory, Shipping, Attributes) and custom fields are hidden for a cleaner experience.

## HOW IT WORKS

1. **Location Selection**: Users select their country via dropdown or modal
2. **Price Display**: Prices automatically convert to the correct currency
3. **Size Selection**: Customers choose a size before adding to cart
4. **Stock Validation**: System checks if the selected size is in stock
5. **Order Processing**: When ordered, stock is reduced for that specific size
6. **Order Cancellation**: Stock is automatically restored if orders are cancelled

## REQUIREMENTS

- WordPress 5.0+
- WooCommerce 5.0+
- PHP 7.4+
- Elementor (optional, for page building)

## CHANGELOG

### Version 1.7

- Added size-based inventory management
- Added location-based pricing (BDT/AUD)
- Added automatic stock reduction on orders
- Added stock restoration on order cancellation
- Added size stock change logs
- Cleaned up admin product interface
- Removed product attributes and brands
- Removed custom fields from product edit page

### Version 1.0

- Initial release with dual-country support
- Location switcher dropdown
- Currency switching functionality
