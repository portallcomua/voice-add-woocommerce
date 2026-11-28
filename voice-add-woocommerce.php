<?php
/**
 * Plugin Name: Voice ADD for WooCommerce
 * Plugin URI:  https://github.com/portallcomua/voice-add-woocommerce
 * Description: Create WooCommerce products by voice, photos, attributes, variations and CSV import/export.
 * Version:     4.0.0
 * Author:      UAServer
 * Author URI:  https://uaserver.pp.ua/
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: voice-add-woocommerce
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 11.3.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VAWC_VERSION', '4.0.0' );
define( 'VAWC_FILE', __FILE__ );
define( 'VAWC_DIR', plugin_dir_path( __FILE__ ) );
define( 'VAWC_URL', plugin_dir_url( __FILE__ ) );

// WooCommerce: плагін працює лише через CRUD товарів і не чіпає замовлення, кошик, листи чи аналітику —
// тому оголошуємо сумісність з УСІМА функціями WooCommerce (HPOS, блоки, email, аналітика, POS тощо).
add_action( 'before_woocommerce_init', function () {
	if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		return;
	}
	$features = array( 'custom_order_tables', 'cart_checkout_blocks', 'product_block_editor', 'analytics', 'marketplace', 'order_attribution', 'remote_logging', 'email_improvements', 'block_email_editor', 'blueprint', 'point_of_sale', 'site_visibility_badge', 'rate_limit_checkout', 'fulfillments', 'cost_of_goods_sold' );
	if ( method_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class, 'get_features' ) ) {
		$features = array_merge( $features, array_keys( (array) \Automattic\WooCommerce\Utilities\FeaturesUtil::get_features( true ) ) );
	}
	foreach ( array_unique( $features ) as $feature_id ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( $feature_id, VAWC_FILE, true );
	}
} );

require_once VAWC_DIR . 'includes/product.php';
require_once VAWC_DIR . 'includes/common.php';

// Create the plugin role and defaults on activation.
register_activation_hook( __FILE__, 'vawc_setup' );
