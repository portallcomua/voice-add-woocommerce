<?php
/**
 * Plugin Name: Voice ADD for WooCommerce PRO
 * Plugin URI:  https://uaserver.pp.ua/voice-add-woocommerce-pro/
 * Description: Голосове додавання товарів у WooCommerce з фото, прибиранням фону, атрибутами та варіаціями.
 * Version:     3.3.0
 * Author:      UAServer
 * Author URI:  https://uaserver.pp.ua/
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: voice-add-woocommerce
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VAWC_PRO_LOADED', true );
define( 'VAWC_VERSION', '3.3.0' );
define( 'VAWC_FILE', __FILE__ );
define( 'VAWC_DIR', plugin_dir_path( __FILE__ ) );
define( 'VAWC_URL', plugin_dir_url( __FILE__ ) );
define( 'VAWC_GITHUB', 'portallcomua/voice-add-woocommerce-pro' );
define( 'VAWC_FREE_LIMIT', 25 );
define( 'VAWC_HUB', 'https://uaserver.pp.ua' ); // сервер Universal Pay Box

// WooCommerce HPOS / блоки
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', VAWC_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', VAWC_FILE, true );
	}
} );

require_once VAWC_DIR . 'includes/product.php';
require_once VAWC_DIR . 'includes/common.php';

// PRO замінює безкоштовну версію: при активації вимикаємо Lite (налаштування та роль спільні).
register_activation_hook( __FILE__, function () {
	vawc_setup();
	if ( function_exists( 'deactivate_plugins' ) ) {
		deactivate_plugins( 'voice-add-for-woocommerce/voice-add-for-woocommerce.php' );
	}
} );
