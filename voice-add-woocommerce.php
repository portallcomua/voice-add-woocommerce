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
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VAWC_VERSION', '4.0.0' );
define( 'VAWC_FILE', __FILE__ );
define( 'VAWC_DIR', plugin_dir_path( __FILE__ ) );
define( 'VAWC_URL', plugin_dir_url( __FILE__ ) );

// The plugin creates and edits products through WooCommerce CRUD objects and does not access orders.
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\\FeaturesUtil::class ) ) {
		\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil::declare_compatibility( 'custom_order_tables', VAWC_FILE, true );
	}
} );

require_once VAWC_DIR . 'includes/product.php';
require_once VAWC_DIR . 'includes/common.php';

// Create the plugin role and defaults on activation.
register_activation_hook( __FILE__, 'vawc_setup' );
