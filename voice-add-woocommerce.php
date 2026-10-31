<?php
/**
 * Plugin Name: Voice ADD for WooCommerce PRO
 * Plugin URI:  https://uaserver.pp.ua/voice-add-woocommerce-pro/
 * Description: Голосове додавання товарів у WooCommerce з фото, прибиранням фону, атрибутами та варіаціями.
 * Version:     3.3.2
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
define( 'VAWC_VERSION', '3.3.2' );
define( 'VAWC_FILE', __FILE__ );
define( 'VAWC_DIR', plugin_dir_path( __FILE__ ) );
define( 'VAWC_URL', plugin_dir_url( __FILE__ ) );
define( 'VAWC_GITHUB', 'portallcomua/voice-add-woocommerce-pro' );
define( 'VAWC_FREE_LIMIT', 25 );
define( 'VAWC_HUB', 'https://uaserver.pp.ua' ); // сервер Universal Pay Box

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

// PRO замінює безкоштовну та старі версії (налаштування та роль спільні).
// Старі версії (папка «voice-add-woocommerce») оголошували ті самі константи й давали Warning — вимикаємо їх автоматично.
function vawc_pro_deactivate_others() {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$others = array( 'voice-add-for-woocommerce/voice-add-for-woocommerce.php', 'voice-add-woocommerce/voice-add-woocommerce.php' );
	$hit    = array();
	foreach ( array_unique( $others ) as $other ) {
		if ( plugin_basename( __FILE__ ) !== $other && is_plugin_active( $other ) ) {
			deactivate_plugins( $other );
			$hit[] = $other;
		}
	}
	return $hit;
}
register_activation_hook( __FILE__, function () {
	vawc_setup();
	vawc_pro_deactivate_others();
} );
add_action( 'admin_init', function () {
	$hit = vawc_pro_deactivate_others();
	if ( $hit ) {
		set_transient( 'vawc_deactivated_notice', implode( ', ', $hit ), 60 );
	}
} );
add_action( 'admin_notices', function () {
	$t = get_transient( 'vawc_deactivated_notice' );
	if ( $t && current_user_can( 'activate_plugins' ) ) {
		delete_transient( 'vawc_deactivated_notice' );
		echo '<div class="notice notice-success is-dismissible"><p><strong>Voice ADD for WooCommerce PRO:</strong> ' . esc_html( 'Старі/безкоштовні версії вимкнено: ' . $t . '. Тепер їх можна видалити через FTP або файловий менеджер хостингу (не кнопкою «Видалити» у WordPress — вона стирає спільні налаштування).' ) . '</p></div>';
	}
} );
