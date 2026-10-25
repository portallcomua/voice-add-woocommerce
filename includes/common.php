<?php
/**
 * Спільне ядро Voice ADD (Voice ADD for WooCommerce PRO).
 * Мова, роль, права, інтеграція з Pay Box, оновлення з GitHub, налаштування,
 * головна сторінка, інструкції, remove.bg, CSV, збереження.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ====================================================================== */
/* 1. МОВА                                                                 */
/* ====================================================================== */
function vawc_lang() {
	$o = get_option( 'vawc_lang', 'auto' );
	if ( 'uk' === $o || 'en' === $o ) {
		return $o;
	}
	$loc = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
	return ( 0 === strpos( $loc, 'en' ) ) ? 'en' : 'uk';
}
function vawc_en() { return 'en' === vawc_lang(); }
function vawc_t( $ua, $en ) { return vawc_en() ? $en : $ua; }

function vawc_norm( $s ) {
	$s = mb_strtolower( (string) $s, 'UTF-8' );
	$s = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $s );
	return trim( $s );
}

/* ====================================================================== */
/* 2. АВТООНОВЛЕННЯ З GITHUB                                               */
/* ====================================================================== */
class VAWC_Updater {
	private $basename, $slug, $repo, $version;

	public function __construct( $file, $repo, $version ) {
		$this->basename = plugin_basename( $file );
		$this->slug     = dirname( $this->basename );
		$this->repo     = $repo;
		$this->version  = $version;
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check' ) );
		add_filter( 'plugins_api', array( $this, 'info' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_dir' ), 10, 4 );
	}

	private function key() { return 'vawc_gh_' . md5( $this->repo ); }

	public function flush() { delete_transient( $this->key() ); }

	public function remote() {
		$cached = get_transient( $this->key() );
		if ( false !== $cached ) {
			return $cached ? $cached : null;
		}
		$resp = wp_remote_get(
			'https://api.github.com/repos/' . $this->repo . '/releases/latest',
			array( 'timeout' => 8, 'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'WordPress/' . $this->slug ) )
		);
		$out = null;
		if ( ! is_wp_error( $resp ) && 200 === (int) wp_remote_retrieve_response_code( $resp ) ) {
			$d = json_decode( wp_remote_retrieve_body( $resp ) );
			if ( $d && isset( $d->tag_name ) ) {
				$zip = isset( $d->zipball_url ) ? $d->zipball_url : '';
				if ( ! empty( $d->assets ) ) {
					foreach ( $d->assets as $a ) {
						if ( isset( $a->name, $a->browser_download_url ) && preg_match( '/\.zip$/i', $a->name ) ) {
							$zip = $a->browser_download_url;
							if ( $a->name === $this->slug . '.zip' ) {
								break;
							}
						}
					}
				}
				$out = (object) array( 'version' => ltrim( $d->tag_name, 'vV' ), 'zip' => $zip, 'url' => $d->html_url );
			}
		}
		set_transient( $this->key(), $out ? $out : 0, 6 * HOUR_IN_SECONDS );
		return $out;
	}

	public function check( $t ) {
		if ( ! is_object( $t ) ) {
			return $t;
		}
		$r = $this->remote();
		if ( $r && $r->zip && version_compare( $this->version, $r->version, '<' ) ) {
			$t->response[ $this->basename ] = (object) array(
				'slug' => $this->slug, 'plugin' => $this->basename, 'new_version' => $r->version,
				'package' => $r->zip, 'url' => $r->url,
			);
		}
		return $t;
	}

	public function info( $res, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->slug ) {
			return $res;
		}
		$r = $this->remote();
		if ( ! $r ) {
			return $res;
		}
		return (object) array(
			'name' => 'Voice ADD for WooCommerce PRO', 'slug' => $this->slug, 'version' => $r->version,
			'download_link' => $r->zip, 'requires_php' => '7.4',
			'sections' => array( 'description' => 'Голосове додавання товарів у WooCommerce з фото, прибиранням фону, атрибутами та варіаціями.' ),
		);
	}

	public function fix_dir( $source, $remote_source, $upgrader, $hook_extra ) {
		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
			return $source;
		}
		global $wp_filesystem;
		$new = trailingslashit( $remote_source ) . $this->slug . '/';
		if ( untrailingslashit( $source ) === untrailingslashit( $new ) ) {
			return $source;
		}
		if ( $wp_filesystem && $wp_filesystem->move( $source, $new, true ) ) {
			return $new;
		}
		return $source;
	}
}

function vawc_updater() {
	static $u = null;
	if ( null === $u ) {
		$u = new VAWC_Updater( VAWC_FILE, VAWC_GITHUB, VAWC_VERSION );
	}
	return $u;
}
vawc_updater();

/* ====================================================================== */
/* 3. PRO ЧЕРЕЗ Universal Pay Box (сервер оплат). PRO прив'язано до ДОМЕНУ */
/* ====================================================================== */
function vawc_hub_url() {
	$u = trim( (string) get_option( 'vawc_hub', '' ) );
	if ( '' === $u ) {
		$u = VAWC_HUB;
	}
	return untrailingslashit( esc_url_raw( $u ) );
}
function vawc_domain() {
	$h = wp_parse_url( home_url(), PHP_URL_HOST );
	return strtolower( preg_replace( '/^www\./', '', (string) $h ) );
}
/** Сервер оплат — це цей самий сайт (Pay Box встановлено поруч)? Тоді викликаємо REST без HTTP. */
function vawc_hub_is_local() {
	$h = strtolower( preg_replace( '/^www\./', '', (string) wp_parse_url( vawc_hub_url(), PHP_URL_HOST ) ) );
	return '' !== $h && vawc_domain() === $h && class_exists( 'UPB_Rest' );
}
function vawc_hub_call( $method, $route, array $params = array() ) {
	if ( vawc_hub_is_local() ) {
		$req = new WP_REST_Request( $method, '/upb/v1/' . $route );
		foreach ( $params as $k => $v ) {
			$req->set_param( $k, $v );
		}
		$res  = rest_do_request( $req );
		$data = $res->get_data();
		$code = $res->get_status();
	} else {
		$url = vawc_hub_url() . '/wp-json/upb/v1/' . $route;
		$r   = ( 'GET' === $method )
			? wp_remote_get( add_query_arg( $params, $url ), array( 'timeout' => 10 ) )
			: wp_remote_post( $url, array( 'timeout' => 15, 'body' => $params ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$code = (int) wp_remote_retrieve_response_code( $r );
		$data = json_decode( wp_remote_retrieve_body( $r ), true );
	}
	if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
		return new WP_Error( 'hub', ( is_array( $data ) && isset( $data['message'] ) ) ? $data['message'] : vawc_t( 'Сервер оплати недоступний', 'Payment server unavailable' ) );
	}
	return $data;
}
/** Стан PRO цього сайту. Кеш 6 год.; якщо сервер недоступний — діє останній відомий стан до 7 діб. */
function vawc_pro_state( $force = false ) {
	$c = get_option( 'vawc_pro' );
	$c = is_array( $c ) ? $c : array( 'state' => 'free', 'expires' => 0, 'checked' => 0 );
	if ( $force || ( time() - (int) $c['checked'] > 6 * HOUR_IN_SECONDS && ! get_transient( 'vawc_hub_down' ) ) ) {
		$r = vawc_hub_call( 'GET', 'status', array( 'service' => 'voice-add-woocommerce', 'domain' => vawc_domain() ) );
		if ( ! is_wp_error( $r ) ) {
			$c = array( 'state' => $r['state'], 'expires' => (int) $r['expires'], 'checked' => time() );
			update_option( 'vawc_pro', $c, false );
			delete_transient( 'vawc_hub_down' );
		} else {
			set_transient( 'vawc_hub_down', 1, 5 * MINUTE_IN_SECONDS );
			if ( time() - (int) $c['checked'] > 7 * DAY_IN_SECONDS ) {
				$c['state'] = 'free';
			}
		}
	}
	return $c;
}
function vawc_is_pro() {
	$c = vawc_pro_state();
	return in_array( $c['state'], array( 'active', 'expiring', 'unlimited' ), true ) && ( ! $c['expires'] || $c['expires'] > time() );
}
function vawc_buy_button( $label = '' ) {
	return '<button type="button" class="button button-primary va-buy" data-va-buy="1">💰 ' . esc_html( $label ? $label : vawc_t( 'Купити PRO', 'Buy PRO' ) ) . '</button>';
}
// Якщо Pay Box стоїть на цьому ж сайті — проект створюється автоматично (Конверт і вартість вказує адмін).
add_action( 'admin_init', function () {
	if ( vawc_hub_is_local() && function_exists( 'upb_register_service' ) && ! upb_service_id( 'voice-add-woocommerce' ) ) {
		upb_register_service( 'voice-add-woocommerce', 'Voice ADD for WooCommerce PRO', array( 'id_mode' => 'domain' ) );
	}
} );

/* Безкоштовний ліміт — на користувача */
function vawc_limit() { return (int) apply_filters( 'vawc_free_limit', VAWC_FREE_LIMIT ); }
function vawc_count() { return (int) get_user_meta( get_current_user_id(), 'vawc_ops', true ); }
function vawc_inc( $n = 1 ) { update_user_meta( get_current_user_id(), 'vawc_ops', vawc_count() + (int) $n ); }
function vawc_remaining() { return max( 0, vawc_limit() - vawc_count() ); }
function vawc_can_create( $n = 1 ) { return vawc_is_pro() || vawc_count() + (int) $n <= vawc_limit(); }

/* ====================================================================== */
/* 4. РОЛЬ, ПРАВА, АКТИВАЦІЯ                                               */
/* ====================================================================== */
function vawc_setup() {
	if ( get_role( 'vawc_manager' ) ) {
		remove_role( 'vawc_manager' );
	}
	add_role( 'vawc_manager', 'Менеджер плагіна voice-add-woocommerce', vawc_role_caps() );
	add_option( 'vawc_draft', '1' );
	add_option( 'vawc_rbg_api', '' );
	add_option( 'vawc_lang', 'auto' );
	add_option( 'vawc_allowed_roles', array( 'administrator', 'vawc_manager' ) );
	update_option( 'vawc_ver', VAWC_VERSION );
}
add_action( 'admin_init', function () {
	if ( get_option( 'vawc_ver' ) !== VAWC_VERSION ) {
		vawc_setup();
	}
} );

/** Віртуальне право vawc_use: адміністратори + вибрані в налаштуваннях ролі. */
add_filter( 'user_has_cap', function ( $allcaps, $caps, $args, $user ) {
	if ( in_array( 'vawc_use', (array) $caps, true ) ) {
		$allowed = (array) get_option( 'vawc_allowed_roles', array( 'administrator', 'vawc_manager' ) );
		if ( ! empty( $allcaps['manage_options'] ) || array_intersect( (array) $user->roles, $allowed ) ) {
			$allcaps['vawc_use'] = true;
		}
	}
	return $allcaps;
}, 10, 4 );

/* ====================================================================== */
/* 5. ПОЛЯ (маяки)                                                         */
/* ====================================================================== */
function vawc_fields() {
	static $f = null;
	if ( null === $f ) {
		$f = array_merge( vawc_base_fields(), vawc_dynamic_fields() );
	}
	return $f;
}
function vawc_label( $def ) { return vawc_en() ? $def['en'] : $def['ua']; }

function vawc_field_map() {
	static $m = null;
	if ( null === $m ) {
		$m = array();
		foreach ( vawc_fields() as $d ) {
			$names = array_merge( array( $d['ua'], $d['en'] ), isset( $d['aliases'] ) ? $d['aliases'] : array() );
			foreach ( $names as $n ) {
				$k = vawc_norm( $n );
				if ( '' !== $k && ! isset( $m[ $k ] ) ) {
					$m[ $k ] = $d['key'];
				}
			}
		}
	}
	return $m;
}
function vawc_field_def( $key ) {
	foreach ( vawc_fields() as $d ) {
		if ( $d['key'] === $key ) {
			return $d;
		}
	}
	return null;
}

function vawc_template() {
	$out = '';
	foreach ( vawc_fields() as $d ) {
		if ( ! empty( $d['tpl'] ) ) {
			$out .= vawc_label( $d ) . ": \n";
		}
	}
	return $out;
}

/** Розбір тексту форми: «Маяк: значення» → масив. */
function vawc_parse_text( $raw ) {
	$map  = vawc_field_map();
	$data = array();
	$rep  = array();
	$cust = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
		$line = trim( $line );
		$pos  = strpos( $line, ':' );
		if ( '' === $line || false === $pos ) {
			continue;
		}
		$label = trim( substr( $line, 0, $pos ) );
		$val   = trim( substr( $line, $pos + 1 ) );
		if ( '' === $val ) {
			continue;
		}
		$n = vawc_norm( $label );
		if ( isset( $map[ $n ] ) ) {
			$def = vawc_field_def( $map[ $n ] );
			if ( $def && 'attr' === $def['kind'] ) {
				continue; // рядок-маяк «Атрибут:» без назви — пропускаємо
			}
			if ( $def && 'repeat' === $def['kind'] ) {
				$rep[ $def['key'] ][] = $val;
			} else {
				$data[ $map[ $n ] ] = $val;
			}
		} elseif ( preg_match( '/^(атрибут|attribute)\s+(.+)$/u', $n ) ) {
			$cname          = trim( preg_replace( '/^(атрибут|attribute)\s+/iu', '', $label ) );
			$cust[ $cname ] = isset( $cust[ $cname ] ) ? $cust[ $cname ] . ', ' . $val : $val;
		}
	}
	return array( 'data' => $data, 'rep' => $rep, 'custom' => $cust );
}

/** Назва атрибута зі слів диктовки → ключ 'attr:slug' існуючого атрибута (або ''). */
function vawc_resolve_attr( $name ) {
	$n = vawc_norm( $name );
	foreach ( vawc_attr_defs() as $a ) {
		if ( $n === vawc_norm( $a['label'] ) || $n === vawc_norm( $a['slug'] ) ) {
			return $a['key'];
		}
	}
	return '';
}

function vawc_num( $s ) {
	$s = str_replace( array( ' ', "\xC2\xA0" ), '', (string) $s );
	$s = str_replace( ',', '.', $s );
	if ( preg_match( '/-?\d+(?:\.\d+)?/', $s, $m ) ) {
		return $m[0];
	}
	return '';
}

function vawc_term_ids( $names, $taxonomy ) {
	$ids = array();
	foreach ( (array) $names as $name ) {
		$name = trim( $name );
		if ( '' === $name ) {
			continue;
		}
		$t = term_exists( $name, $taxonomy );
		if ( ! $t ) {
			$t = wp_insert_term( $name, $taxonomy );
		}
		if ( is_wp_error( $t ) ) {
			continue;
		}
		$ids[] = (int) ( is_array( $t ) ? $t['term_id'] : $t );
	}
	return $ids;
}

/** Ієрархія «Батько > Дитина»: повертає ID останнього терму. */
function vawc_term_path( $path, $taxonomy ) {
	$parent = 0;
	$last   = 0;
	foreach ( array_filter( array_map( 'trim', explode( '>', $path ) ) ) as $name ) {
		$t = term_exists( $name, $taxonomy, $parent );
		if ( ! $t ) {
			$t = wp_insert_term( $name, $taxonomy, array( 'parent' => $parent ) );
		}
		if ( is_wp_error( $t ) ) {
			break;
		}
		$last   = (int) ( is_array( $t ) ? $t['term_id'] : $t );
		$parent = $last;
	}
	return $last;
}

/* ====================================================================== */
/* 6. ЗОБРАЖЕННЯ                                                           */
/* ====================================================================== */
function vawc_save_images( $pid, $imgs ) {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$ids = array();
	$map = array(
		IMAGETYPE_JPEG => array( 'jpg', 'image/jpeg' ),
		IMAGETYPE_PNG  => array( 'png', 'image/png' ),
		IMAGETYPE_WEBP => array( 'webp', 'image/webp' ),
	);
	foreach ( array_slice( (array) $imgs, 0, 10 ) as $i => $b64 ) {
		if ( ! is_string( $b64 ) || false === ( $p = strpos( $b64, 'base64,' ) ) ) {
			continue;
		}
		$bin = base64_decode( substr( $b64, $p + 7 ), true );
		if ( ! $bin ) {
			continue;
		}
		$info = @getimagesizefromstring( $bin );
		if ( ! $info || ! isset( $map[ $info[2] ] ) ) {
			continue;
		}
		$up = wp_upload_bits( 'vawc_' . (int) $pid . '_' . time() . '_' . (int) $i . '.' . $map[ $info[2] ][0], null, $bin );
		if ( ! empty( $up['error'] ) ) {
			continue;
		}
		$aid = wp_insert_attachment(
			array( 'post_mime_type' => $map[ $info[2] ][1], 'post_title' => get_the_title( $pid ) ? get_the_title( $pid ) : 'image', 'post_status' => 'inherit' ),
			$up['file'],
			$pid
		);
		if ( ! $aid || is_wp_error( $aid ) ) {
			continue;
		}
		wp_update_attachment_metadata( $aid, wp_generate_attachment_metadata( $aid, $up['file'] ) );
		$ids[] = (int) $aid;
	}
	return $ids;
}

function vawc_sideload_images( $pid, $urls ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$ids = array();
	foreach ( array_slice( (array) $urls, 0, 10 ) as $url ) {
		$url = esc_url_raw( trim( $url ) );
		if ( ! $url ) {
			continue;
		}
		$id = media_sideload_image( $url, $pid, null, 'id' );
		if ( ! is_wp_error( $id ) ) {
			$ids[] = (int) $id;
		}
	}
	return $ids;
}

/* ====================================================================== */
/* 7. МЕНЮ                                                                 */
/* ====================================================================== */
add_action( 'admin_menu', function () {
	add_menu_page( 'Voice ADD for WooCommerce PRO', 'Voice ADD WC', 'vawc_use', 'vawc_main', 'vawc_page_main', 'dashicons-microphone', 30 );
	add_submenu_page( 'vawc_main', vawc_t( 'Додавання голосом', 'Voice add' ), '🎤 ' . vawc_t( 'Додавання голосом', 'Voice add' ), 'vawc_use', 'vawc_main', 'vawc_page_main' );
	add_submenu_page( 'vawc_main', vawc_t( 'Налаштування', 'Settings' ), '⚙️ ' . vawc_t( 'Налаштування', 'Settings' ), 'manage_options', 'vawc_settings', 'vawc_page_settings' );
}, 20 );

add_filter( 'plugin_action_links_' . plugin_basename( VAWC_FILE ), function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=vawc_settings' ) ) . '">' . esc_html( vawc_t( 'Налаштування', 'Settings' ) ) . '</a>' );
	return $links;
} );

/* ====================================================================== */
/* 8. НАЛАШТУВАННЯ                                                         */
/* ====================================================================== */
function vawc_page_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Forbidden' );
	}
	$msg = '';
	if ( isset( $_POST['vawc_save'] ) && check_admin_referer( 'vawc_cfg' ) ) {
		update_option( 'vawc_rbg_api', sanitize_text_field( wp_unslash( isset( $_POST['rbg_api'] ) ? $_POST['rbg_api'] : '' ) ) );
		update_option( 'vawc_draft', isset( $_POST['draft'] ) ? '1' : '0' );
		update_option( 'vawc_hub', esc_url_raw( trim( wp_unslash( isset( $_POST['hub'] ) ? $_POST['hub'] : '' ) ) ) );
		vawc_pro_state( true );
		$lang = isset( $_POST['lang'] ) ? sanitize_key( $_POST['lang'] ) : 'auto';
		update_option( 'vawc_lang', in_array( $lang, array( 'auto', 'uk', 'en' ), true ) ? $lang : 'auto' );
		$valid = array_keys( get_editable_roles() );
		$roles = isset( $_POST['roles'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['roles'] ) ) : array();
		$roles = array_values( array_intersect( $roles, $valid ) );
		if ( ! in_array( 'administrator', $roles, true ) ) {
			$roles[] = 'administrator';
		}
		update_option( 'vawc_allowed_roles', $roles );
		$msg = '<div class="notice notice-success is-dismissible"><p>✅ ' . esc_html( vawc_t( 'Збережено!', 'Saved!' ) ) . '</p></div>';
	}
	if ( isset( $_POST['vawc_pro_refresh'] ) && check_admin_referer( 'vawc_cfg' ) ) {
		vawc_pro_state( true );
		$msg = '<div class="notice notice-success is-dismissible"><p>✅ ' . esc_html( vawc_t( 'Статус PRO оновлено.', 'PRO status refreshed.' ) ) . '</p></div>';
	}
	if ( isset( $_POST['vawc_check_update'] ) && check_admin_referer( 'vawc_cfg' ) ) {
		vawc_updater()->flush();
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
		$r = vawc_updater()->remote();
		if ( $r && version_compare( VAWC_VERSION, $r->version, '<' ) ) {
			$msg = '<div class="notice notice-info"><p>' . sprintf( esc_html( vawc_t( 'Доступна нова версія %s. Оновіть на сторінці «Плагіни».', 'New version %s is available. Update it on the Plugins page.' ) ), '<strong>' . esc_html( $r->version ) . '</strong>' ) . '</p></div>';
		} else {
			$msg = '<div class="notice notice-success"><p>' . esc_html( vawc_t( 'У вас остання версія.', 'You have the latest version.' ) ) . '</p></div>';
		}
	}

	$rbg   = get_option( 'vawc_rbg_api', '' );
	$draft = get_option( 'vawc_draft', '1' );
	$roles = (array) get_option( 'vawc_allowed_roles', array( 'administrator', 'vawc_manager' ) );
	$lang  = get_option( 'vawc_lang', 'auto' );
	$hub   = get_option( 'vawc_hub', '' );
	$svc   = vawc_hub_call( 'GET', 'service', array( 'service' => 'voice-add-woocommerce' ) );
	$pst   = vawc_pro_state();
	?>
	<div class="wrap">
		<h1>
			⚙️ Voice ADD for WooCommerce PRO
			<span style="font-size:14px;color:#666;font-weight:400">v<?php echo esc_html( VAWC_VERSION ); ?></span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=vawc_main' ) ); ?>" class="page-title-action">🎤 <?php echo esc_html( vawc_t( 'Перейти на плагін', 'Go to plugin' ) ); ?></a>
		</h1>
		<?php echo $msg; // phpcs:ignore ?>

		<form method="post"><?php wp_nonce_field( 'vawc_cfg' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><label>Remove.bg API Key</label></th>
					<td><input type="text" name="rbg_api" value="<?php echo esc_attr( $rbg ); ?>" class="regular-text code" autocomplete="off">
					<p class="description"><?php echo esc_html( vawc_t( 'Для прибирання фону на фото. Ключ: remove.bg/api. Без ключа вибір фону вимкнено.', 'For background removal on photos. Get a key at remove.bg/api. Without a key the background picker is disabled.' ) ); ?></p></td>
				</tr>
				<tr>
					<th><label><?php echo esc_html( vawc_t( 'Сервер оплат (Pay Box)', 'Payment server (Pay Box)' ) ); ?></label></th>
					<td><input type="url" name="hub" value="<?php echo esc_attr( $hub ); ?>" class="regular-text code" placeholder="<?php echo esc_attr( VAWC_HUB ); ?>">
					<p class="description"><?php echo esc_html( vawc_t( 'Сайт із плагіном Universal Pay Box PRO, де обліковуються активації PRO. Порожньо = стандартний сервер.', 'Site running Universal Pay Box PRO that records PRO activations. Empty = default server.' ) ); ?></p></td>
				</tr>
				<tr>
					<th><?php echo esc_html( vawc_t( 'Статус нового запису', 'New item status' ) ); ?></th>
					<td><label><input type="checkbox" name="draft" value="1" <?php checked( $draft, '1' ); ?>> <?php echo esc_html( vawc_t( 'Зберігати як чернетку', 'Save as draft' ) ); ?></label></td>
				</tr>
				<tr>
					<th><?php echo esc_html( vawc_t( 'Мова плагіна', 'Plugin language' ) ); ?></th>
					<td><select name="lang">
						<option value="auto" <?php selected( $lang, 'auto' ); ?>><?php echo esc_html( vawc_t( 'Автоматично (мова сайту)', 'Auto (site language)' ) ); ?></option>
						<option value="uk" <?php selected( $lang, 'uk' ); ?>>Українська</option>
						<option value="en" <?php selected( $lang, 'en' ); ?>>English</option>
					</select>
					<p class="description"><?php echo esc_html( vawc_t( 'Мова інтерфейсу, маяків та розпізнавання голосу.', 'Interface, beacon words and speech recognition language.' ) ); ?></p></td>
				</tr>
				<tr>
					<th><?php echo esc_html( vawc_t( 'Тип користувачів з доступом', 'User roles with access' ) ); ?></th>
					<td>
						<?php foreach ( get_editable_roles() as $r_name => $r_info ) : ?>
							<label style="display:block;margin-bottom:3px">
								<input type="checkbox" name="roles[]" value="<?php echo esc_attr( $r_name ); ?>" <?php checked( in_array( $r_name, $roles, true ) ); ?> <?php disabled( 'administrator' === $r_name ); ?>>
								<?php echo esc_html( translate_user_role( $r_info['name'] ) ); ?>
							</label>
						<?php endforeach; ?>
						<p class="description"><?php echo esc_html( vawc_t( 'Ці ролі бачать плагін і можуть ним працювати. Адміністратор має доступ завжди. Роль «Менеджер плагіна» створюється автоматично при встановленні.', 'These roles can see and use the plugin. Administrators always have access. The “Plugin manager” role is created automatically on install.' ) ); ?></p>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" name="vawc_save" value="1" class="button button-primary"><?php echo esc_html( vawc_t( 'Зберегти', 'Save' ) ); ?></button>
				<button type="submit" name="vawc_check_update" value="1" class="button"><?php echo esc_html( vawc_t( 'Перевірити оновлення (GitHub)', 'Check for updates (GitHub)' ) ); ?></button>
			</p>
		</form>

		<hr>
		<h2>💰 PRO</h2>
		<table class="widefat striped" style="max-width:680px"><tbody>
			<tr><td><?php echo esc_html( vawc_t( 'Домен вашого сайту (вказується в платежі)', 'Your site domain (put it in the payment comment)' ) ); ?></td><td><strong><?php echo esc_html( vawc_domain() ); ?></strong></td></tr>
			<tr><td><?php echo esc_html( vawc_t( 'Сервер оплат', 'Payment server' ) ); ?></td><td><?php echo esc_html( vawc_hub_url() ); ?> <?php echo vawc_hub_is_local() ? '(' . esc_html( vawc_t( 'цей сайт', 'this site' ) ) . ')' : ''; ?></td></tr>
			<tr><td><?php echo esc_html( vawc_t( 'Проект на сервері', 'Project on server' ) ); ?></td><td><?php
				if ( is_wp_error( $svc ) ) {
					echo '🔴 ' . esc_html( $svc->get_error_message() );
				} else {
					echo '🟢 ' . esc_html( $svc['name'] ) . ' · ' . esc_html( $svc['price'] ) . ( empty( $svc['url'] ) ? ' · ⚠️ ' . esc_html( vawc_t( 'немає посилання на Конверт', 'no envelope link' ) ) : '' );
				}
			?></td></tr>
			<tr><td><?php echo esc_html( vawc_t( 'Статус PRO', 'PRO status' ) ); ?></td><td><?php
				$labels = array( 'free' => 'FREE', 'active' => '✅ PRO', 'expiring' => '✅ PRO (' . vawc_t( 'скоро закінчиться', 'expiring soon' ) . ')', 'unlimited' => '✅ PRO (∞)', 'blocked' => '⛔ ' . vawc_t( 'заблоковано', 'blocked' ) );
				echo esc_html( isset( $labels[ $pst['state'] ] ) ? $labels[ $pst['state'] ] : $pst['state'] );
				if ( ! empty( $pst['expires'] ) ) {
					echo ' · ' . esc_html( vawc_t( 'до ', 'until ' ) . wp_date( 'd.m.Y', (int) $pst['expires'] ) );
				}
			?> · FREE <?php echo (int) vawc_count(); ?>/<?php echo (int) vawc_limit(); ?></td></tr>
		</tbody></table>
		<form method="post" style="margin-top:10px"><?php wp_nonce_field( 'vawc_cfg' ); ?>
			<button type="submit" name="vawc_pro_refresh" value="1" class="button">🔄 <?php echo esc_html( vawc_t( 'Оновити статус PRO', 'Refresh PRO status' ) ); ?></button>
			<?php if ( vawc_hub_is_local() ) : ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=upb-services' ) ); ?>">UA Pay Box → <?php echo esc_html( vawc_t( 'Послуги', 'Services' ) ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=upb-premiums' ) ); ?>"><?php echo esc_html( vawc_t( 'Контроль Преміумів', 'Premium control' ) ); ?></a>
			<?php endif; ?>
		</form>
	</div>
	<?php
}

/* ====================================================================== */
/* 9. ГОЛОВНА СТОРІНКА                                                     */
/* ====================================================================== */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( false === strpos( $hook, 'vawc_main' ) ) {
		return;
	}
	wp_enqueue_style( 'vawc-app', VAWC_URL . 'assets/app.css', array(), VAWC_VERSION );
	wp_enqueue_script( 'vawc-app', VAWC_URL . 'assets/app.js', array(), VAWC_VERSION, true );

	$fields = array();
	foreach ( vawc_fields() as $d ) {
		$al = array( $d['ua'], $d['en'] );
		if ( ! empty( $d['aliases'] ) ) {
			$al = array_merge( $al, $d['aliases'] );
		}
		$fields[] = array(
			'key'     => $d['key'],
			'label'   => vawc_label( $d ),
			'aliases' => array_values( array_unique( array_filter( array_map( 'vawc_norm', $al ) ) ) ),
			'kind'    => $d['kind'],
			'tpl'     => ! empty( $d['tpl'] ),
			'join'    => isset( $d['join'] ) ? $d['join'] : ' ',
		);
	}
	$S = array(
		'mic'        => '🎤 ' . vawc_t( 'ДИКТУВАТИ', 'DICTATE' ),
		'micStop'    => '🛑 ' . vawc_t( 'ЗУПИНИТИ', 'STOP' ),
		'save'       => '➕ ' . ( '1' === get_option( 'vawc_draft', '1' ) ? vawc_t( 'ЗБЕРЕГТИ ЯК ЧЕРНЕТКУ', 'SAVE AS DRAFT' ) : vawc_t( 'ОПУБЛІКУВАТИ', 'PUBLISH' ) ),
		'saving'     => '⏳ ' . vawc_t( 'Зберігаю...', 'Saving...' ),
		'noSpeech'   => vawc_t( 'Ваш браузер не підтримує розпізнавання голосу. Використовуйте Chrome (Android/ПК) через HTTPS.', 'Your browser does not support speech recognition. Use Chrome (Android/PC) over HTTPS.' ),
		'noCam'      => vawc_t( 'Камера недоступна. Можна додати фото з галереї.', 'Camera unavailable. You can add photos from the gallery.' ),
		'listening'  => vawc_t( 'Слухаю…', 'Listening…' ),
		'target'     => vawc_t( 'Диктуєте в поле', 'Dictating into' ),
		'created'    => vawc_t( 'Створено!', 'Created!' ),
		'edit'       => vawc_t( 'Відкрити', 'Open' ),
		'limit'      => vawc_t( 'Безкоштовний ліміт вичерпано. Придбайте PRO.', 'Free limit reached. Please buy PRO.' ),
		'bgWork'     => vawc_t( 'Прибираю фон', 'Removing background' ),
		'bgFail'     => vawc_t( 'Не вдалося прибрати фон — збережено оригінал', 'Background removal failed — original saved' ),
		'importing'  => vawc_t( 'Імпортую…', 'Importing…' ),
		'importDone' => vawc_t( 'Імпорт завершено', 'Import finished' ),
		'created_n'  => vawc_t( 'створено', 'created' ),
		'failed_n'   => vawc_t( 'помилок', 'failed' ),
		'pickCsv'    => vawc_t( 'Виберіть CSV-файл', 'Choose a CSV file' ),
		'netErr'     => vawc_t( 'Помилка зʼєднання', 'Connection error' ),
		'remove'     => vawc_t( 'Натисніть, щоб видалити фото', 'Tap to remove photo' ),
		'printTitle' => vawc_t( 'Інструкція', 'Instruction' ),
		'copyDomain' => '📋 ' . vawc_t( 'Скопіювати домен', 'Copy domain' ),
		'copied'     => vawc_t( 'Скопійовано! 👍', 'Copied! 👍' ),
		'copyManual' => vawc_t( 'Скопіюйте виділене вручну', 'Copy the selected text manually' ),
		'payGo'      => vawc_t( 'Перейти до оплати →', 'Go to payment →' ),
		'activating' => vawc_t( 'Активація…', 'Activating…' ),
		'alreadyPro' => vawc_t( 'PRO вже активний.', 'PRO is already active.' ),
		'blockedMsg' => vawc_t( 'PRO заблоковано: оплата не надійшла. Сплатіть за посиланням, вказавши домен у коментарі, — після підтвердження адмін відновить доступ.', 'PRO is blocked: payment was not received. Pay via the link with the domain in the comment — the admin will restore access after confirming.' ),
		'noPayUrl'   => vawc_t( 'Адміністратор ще не вказав посилання на оплату.', 'The administrator has not set the payment link yet.' ),
	);
	wp_localize_script(
		'vawc-app',
		'VA_CFG',
		array(
			'ajax'    => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'vawc_nonce' ),
			'actions' => array( 'save' => 'vawc_save', 'bg' => 'vawc_remove_bg', 'import' => 'vawc_import', 'payInfo' => 'vawc_pay_info', 'payGo' => 'vawc_pay_activate' ),
			'attrNames' => array_map( function ( $a ) { return $a['label']; }, vawc_attr_defs() ),
			'lang'    => vawc_en() ? 'en-US' : 'uk-UA',
			'hasRbg'  => '' !== (string) get_option( 'vawc_rbg_api', '' ),
			'fields'  => $fields,
			'tpl'     => vawc_template(),
			'S'       => $S,
		)
	);
} );

function vawc_page_main() {
	if ( ! current_user_can( 'vawc_use' ) ) {
		wp_die( esc_html( vawc_t( 'Доступ заборонено', 'Access denied' ) ) );
	}
	$pro     = vawc_is_pro();
	$draft   = '1' === get_option( 'vawc_draft', '1' );
	$has_rbg = '' !== (string) get_option( 'vawc_rbg_api', '' );
	$exp_url = add_query_arg( array( 'action' => 'vawc_export', 'nonce' => wp_create_nonce( 'vawc_nonce' ) ), admin_url( 'admin-ajax.php' ) );
	?>
	<div class="wrap va-wrap" id="va_app">
		<div class="va-head">
			<h1>🎤 Voice ADD for WooCommerce PRO <sup>v<?php echo esc_html( VAWC_VERSION ); ?></sup></h1>
			<div class="va-head-btns">
				<button type="button" class="button" id="va_go_instr">📖 <?php echo esc_html( vawc_t( 'Інструкція', 'Instructions' ) ); ?></button>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=vawc_settings' ) ); ?>">⚙️ <?php echo esc_html( vawc_t( 'Налаштування', 'Settings' ) ); ?></a>
				<?php endif; ?>
			</div>
		</div>

		<?php $pst = vawc_pro_state(); ?>
		<?php if ( $pro ) : ?>
			<div class="va-pro-bar va-pro-ok">✅ PRO — <?php echo esc_html( vawc_t( 'без обмежень', 'unlimited' ) ); ?><?php echo ! empty( $pst['expires'] ) ? ' · ' . esc_html( vawc_t( 'до ', 'until ' ) . wp_date( 'd.m.Y', (int) $pst['expires'] ) ) : ''; ?></div>
		<?php else : ?>
			<div class="va-pro-bar<?php echo 'blocked' === $pst['state'] ? ' va-pro-blocked' : ''; ?>">
				<?php if ( 'blocked' === $pst['state'] ) : ?>
					⛔ <?php echo esc_html( vawc_t( 'PRO заблоковано адміністратором — оплата не надійшла.', 'PRO was blocked by the administrator — payment not received.' ) ); ?>
				<?php endif; ?>
				📊 <?php echo esc_html( vawc_t( 'Залишилось безкоштовно:', 'Free left:' ) ); ?>
				<strong id="va_left"><?php echo (int) vawc_remaining(); ?></strong>/<?php echo (int) vawc_limit(); ?>
				<?php echo vawc_buy_button(); // phpcs:ignore ?>
			</div>
		<?php endif; ?>

		<video id="va_cam" autoplay playsinline muted></video>
		<div id="va_gal" class="va-gal"></div>

		<div id="va_bg_wrap" class="va-bg" style="display:none">
			<strong><?php echo esc_html( vawc_t( 'Фон:', 'Background:' ) ); ?></strong>
			<?php if ( $has_rbg ) : ?>
				<label><input type="radio" name="va_bg" value="original" checked> <?php echo esc_html( vawc_t( 'Оригінал', 'Original' ) ); ?></label>
				<label><input type="radio" name="va_bg" value="transparent"> <?php echo esc_html( vawc_t( 'Прозорий', 'Transparent' ) ); ?></label>
				<label><input type="radio" name="va_bg" value="ffffff"> <?php echo esc_html( vawc_t( 'Білий', 'White' ) ); ?></label>
			<?php else : ?>
				<span class="va-muted"><?php echo esc_html( vawc_t( 'вкажіть Remove.bg API Key в налаштуваннях', 'set the Remove.bg API key in settings' ) ); ?></span>
			<?php endif; ?>
		</div>

		<div class="va-row">
			<button type="button" id="va_photo" class="va-btn va-blue" style="flex:3">📷 <?php echo esc_html( vawc_t( 'ФОТО', 'PHOTO' ) ); ?></button>
			<button type="button" id="va_file" class="va-btn va-gray" style="flex:2">🖼️ <?php echo esc_html( vawc_t( 'З ГАЛЕРЕЇ', 'GALLERY' ) ); ?></button>
			<button type="button" id="va_clr" class="va-btn va-gray" style="flex:1">❌</button>
			<input type="file" id="va_files" accept="image/*" multiple hidden>
		</div>

		<div id="va_target" class="va-target" hidden></div>
		<textarea id="va_ta" rows="<?php echo (int) min( 22, max( 10, count( vawc_fields() ) + 2 ) ); ?>" spellcheck="false"><?php echo esc_textarea( vawc_template() ); ?></textarea>

		<button type="button" id="va_mic" class="va-btn va-mic">🎤 <?php echo esc_html( vawc_t( 'ДИКТУВАТИ', 'DICTATE' ) ); ?></button>
		<button type="button" id="va_save" class="va-btn va-save">➕ <?php echo esc_html( $draft ? vawc_t( 'ЗБЕРЕГТИ ЯК ЧЕРНЕТКУ', 'SAVE AS DRAFT' ) : vawc_t( 'ОПУБЛІКУВАТИ', 'PUBLISH' ) ); ?></button>
		<div id="va_msg" class="va-msg"></div>

		<!-- CSV -->
		<div class="va-card" id="va_csv">
			<h2>📥 <?php echo esc_html( vawc_t( 'Імпорт / експорт CSV', 'CSV import / export' ) ); ?></h2>
			<p class="va-muted"><?php echo esc_html( vawc_t( 'Колонки: див. інструкцію нижче. Роздільник «;» або «,» визначається автоматично.', 'Columns: see the instructions below. Delimiter “;” or “,” is detected automatically.' ) ); ?></p>
			<div class="va-row">
				<input type="file" id="va_csv_file" accept=".csv,text/csv">
				<button type="button" id="va_import" class="va-btn va-blue">📥 <?php echo esc_html( vawc_t( 'Імпортувати CSV', 'Import CSV' ) ); ?></button>
			</div>
			<p>
				<a class="button" href="<?php echo esc_url( $exp_url . '&tpl=1' ); ?>">⬇️ <?php echo esc_html( vawc_t( 'Шаблон CSV', 'CSV template' ) ); ?></a>
				<a class="button" href="<?php echo esc_url( $exp_url . '&sep=semi' ); ?>">📤 <?php echo esc_html( vawc_t( 'Експорт (Excel «;»)', 'Export (Excel “;”)' ) ); ?></a>
				<a class="button" href="<?php echo esc_url( $exp_url . '&sep=comma' ); ?>">📤 <?php echo esc_html( vawc_t( 'Експорт («,»)', 'Export (“,”)' ) ); ?></a>
			</p>
			<div id="va_csv_msg" class="va-msg"></div>
		</div>

		<!-- ОПЛАТА PRO -->
		<div class="va-modal" id="va_pay" hidden>
			<div class="va-modal__bg" data-va-close></div>
			<div class="va-modal__box" role="dialog" aria-modal="true">
				<button type="button" class="va-modal__x" data-va-close aria-label="×">&times;</button>
				<h3>💰 <span data-va-pname></span></h3>
				<div class="va-modal__price" data-va-pprice></div>
				<div data-va-pmsg class="va-modal__msg" hidden></div>
				<div data-va-pbody>
					<p class="va-muted"><?php echo esc_html( vawc_t( 'Домен вашого сайту:', 'Your site domain:' ) ); ?></p>
					<div class="va-idrow"><strong data-va-domain></strong><button type="button" id="va_pay_copy" class="va-copy">📋 <?php echo esc_html( vawc_t( 'Скопіювати домен', 'Copy domain' ) ); ?></button></div>
					<p class="va-note"><strong><?php echo esc_html( vawc_t( 'Обов\'язково', 'Required:' ) ); ?></strong> <?php echo esc_html( vawc_t( 'вставте скопійований домен у поле «Коментар» або «Призначення платежу» на сторінці банку. Це необхідно для верифікації вашого платежу адміном. Платежі без вказаного домену будуть заблоковані.', 'paste the copied domain into the “Comment” or “Payment purpose” field on the bank page. It is needed to verify your payment. Payments without the domain will be blocked.' ) ); ?></p>
					<p class="va-muted"><?php echo esc_html( vawc_t( 'PRO активується одразу. Якщо платіж не надійде — адмін вимкне доступ.', 'PRO is activated immediately. If the payment does not arrive, the admin will disable it.' ) ); ?></p>
					<button type="button" id="va_pay_go" class="va-btn va-blue" disabled><?php echo esc_html( vawc_t( 'Перейти до оплати →', 'Go to payment →' ) ); ?></button>
				</div>
			</div>
		</div>

		<!-- ІНСТРУКЦІЇ -->
		<div id="va_instr" class="va-instr-wrap">
			<h2>📖 <?php echo esc_html( vawc_t( 'Інструкції', 'Instructions' ) ); ?></h2>
			<?php vawc_render_instructions(); ?>
		</div>
	</div>
	<?php
}

/* ====================================================================== */
/* 10. ІНСТРУКЦІЇ (поля підтягуються з магазину / HivePress)               */
/* ====================================================================== */
function vawc_instr_open( $id, $title ) {
	echo '<section class="va-instr" id="' . esc_attr( $id ) . '"><div class="va-instr-head"><h3>' . esc_html( $title ) . '</h3>';
	echo '<button type="button" class="button va-print" data-target="' . esc_attr( $id ) . '">🖨️ ' . esc_html( vawc_t( 'Роздрукувати', 'Print' ) ) . '</button></div><div class="va-instr-body">';
}
function vawc_instr_close() { echo '</div></section>'; }

function vawc_render_instructions() {
	// 1. Як диктувати
	vawc_instr_open( 'va_i_how', vawc_t( '1. Як диктувати', '1. How to dictate' ) );
	echo '<ol>';
	echo '<li>' . esc_html( vawc_t( 'Натисніть «ДИКТУВАТИ» і дозвольте мікрофон (потрібен HTTPS і браузер Chrome).', 'Press “DICTATE” and allow the microphone (HTTPS and Chrome are required).' ) ) . '</li>';
	echo '<li>' . esc_html( vawc_t( 'Скажіть слово-маяк, наприклад «Назва». Курсор одразу з’явиться в цьому рядку після двокрапки.', 'Say a beacon word, e.g. “Name”. The cursor jumps to that line right after the colon.' ) ) . '</li>';
	echo '<li>' . esc_html( vawc_t( 'Скажіть значення — воно запишеться саме в цей рядок (напр. «холодильник Samsung»).', 'Say the value — it is written into that line (e.g. “Samsung refrigerator”).' ) ) . '</li>';
	echo '<li>' . esc_html( vawc_t( 'Скажіть наступний маяк — курсор перейде далі. Якщо повторити маяк, старе значення замінюється новим.', 'Say the next beacon — the cursor moves on. Repeating a beacon replaces the old value.' ) ) . '</li>';
	echo '<li>' . esc_html( vawc_t( 'Для списків (мітки) говоріть значення з паузами або кажіть «кома».', 'For lists (tags) speak values with pauses or say “comma”.' ) ) . '</li>';
	echo '<li><b>' . esc_html( vawc_t( 'Атрибути:', 'Attributes:' ) ) . '</b> ' . esc_html( vawc_t( 'у вікні лише маяк «Атрибут». Кажіть: «Атрибут колір значення червоний значення жовтий Атрибут стан значення новий» — плагін сам створить рядки «Атрибут Колір: червоний, жовтий» та «Атрибут Стан: новий».', 'the window holds only the “Attribute” beacon. Say: “Attribute color value red value yellow Attribute condition value new” — the plugin creates the lines “Attribute Color: red, yellow” and “Attribute Condition: new”.' ) ) . '</li>';
	echo '<li>' . esc_html( vawc_t( 'Текст у полі можна виправляти вручну в будь-який момент.', 'You can edit the text by hand at any moment.' ) ) . '</li>';
	echo '</ol>';
	echo '<p><strong>' . esc_html( vawc_t( 'Приклад:', 'Example:' ) ) . '</strong> ' . esc_html( vawc_t( '«Назва» → «холодильник Samsung» → «Ціна» → «15000» → «Категорія» → «Побутова техніка»', '“Name” → “Samsung refrigerator” → “Price” → “15000” → “Category” → “Appliances”' ) ) . '</p>';
	vawc_instr_close();

	// 2. Таблиця маяків
	vawc_instr_open( 'va_i_fields', vawc_t( '2. Маяки (поля)', '2. Beacons (fields)' ) );
	echo '<table class="va-table"><thead><tr><th>' . esc_html( vawc_t( 'Скажіть', 'Say' ) ) . '</th><th>' . esc_html( vawc_t( 'Що записується', 'What is saved' ) ) . '</th><th>' . esc_html( vawc_t( 'Приклад значення', 'Example value' ) ) . '</th></tr></thead><tbody>';
	foreach ( vawc_fields() as $d ) {
		$ex   = isset( $d['ex'] ) ? ( vawc_en() ? $d['ex'][1] : $d['ex'][0] ) : '';
		$desc = isset( $d['desc'] ) ? ( vawc_en() ? $d['desc'][1] : $d['desc'][0] ) : '';
		echo '<tr><td><b>' . esc_html( vawc_label( $d ) ) . '</b></td><td>' . esc_html( $desc ) . '</td><td class="va-muted">' . esc_html( $ex ) . '</td></tr>';
	}
	echo '</tbody></table>';
	vawc_instr_close();

	// 3+. Блоки конкретного плагіна (атрибути, варіації, категорії…)
	foreach ( vawc_extra_instructions() as $blk ) {
		vawc_instr_open( $blk['id'], $blk['title'] );
		echo $blk['html']; // phpcs:ignore -- HTML сформовано в плагіні, значення екрановано
		vawc_instr_close();
	}

	// CSV
	vawc_instr_open( 'va_i_csv', vawc_t( 'Імпорт та експорт CSV', 'CSV import and export' ) );
	$cols = vawc_csv_columns();
	echo '<p>' . esc_html( vawc_t( 'Перший рядок — назви колонок. Підтримуються такі колонки (регістр не важливий):', 'The first row holds column names. Supported columns (case-insensitive):' ) ) . '</p>';
	echo '<p><code>' . esc_html( implode( ' ; ', array_map( function ( $c ) { return $c[0]; }, $cols ) ) ) . '</code></p>';
	echo '<p>' . esc_html( vawc_t( 'Кілька значень (категорії, мітки, значення атрибутів) розділяйте комою. Колонка Images — посилання на фото через кому. Натисніть «Шаблон CSV», щоб завантажити готовий приклад.', 'Separate multiple values (categories, tags, attribute values) with commas. The Images column holds photo URLs separated by commas. Press “CSV template” to download a ready example.' ) ) . '</p>';
	vawc_instr_close();

	// Фон
	vawc_instr_open( 'va_i_bg', vawc_t( 'Фото та прибирання фону', 'Photos and background removal' ) );
	echo '<ul><li>' . esc_html( vawc_t( 'Кнопка «ФОТО» знімає з камери, «З ГАЛЕРЕЇ» — додає готові фото. Натисніть на мініатюру, щоб видалити.', '“PHOTO” shoots from the camera, “GALLERY” adds existing photos. Tap a thumbnail to delete it.' ) ) . '</li>';
	echo '<li>' . esc_html( vawc_t( 'Фон: «Оригінал» — без змін; «Прозорий» — PNG без фону; «Білий» — чисто білий фон. Працює через remove.bg (ключ у налаштуваннях).', 'Background: “Original” — unchanged; “Transparent” — PNG with no background; “White” — plain white. Powered by remove.bg (key in settings).' ) ) . '</li>';
	echo '<li>' . esc_html( vawc_t( 'Перше фото стає головним.', 'The first photo becomes the main one.' ) ) . '</li></ul>';
	vawc_instr_close();
}

/* ====================================================================== */
/* 11. AJAX                                                                */
/* ====================================================================== */
function vawc_ajax_guard() {
	if ( ! check_ajax_referer( 'vawc_nonce', 'nonce', false ) ) {
		wp_send_json_error( vawc_t( 'Сесія застаріла — оновіть сторінку', 'Session expired — reload the page' ) );
	}
	if ( ! current_user_can( 'vawc_use' ) ) {
		wp_send_json_error( vawc_t( 'Немає прав', 'No permission' ) );
	}
}

add_action( 'wp_ajax_vawc_save', function () {
	vawc_ajax_guard();
	if ( ! vawc_can_create( 1 ) ) {
		wp_send_json_error( vawc_t( 'Безкоштовний ліміт вичерпано. Придбайте PRO.', 'Free limit reached. Please buy PRO.' ) );
	}
	$raw  = sanitize_textarea_field( wp_unslash( isset( $_POST['text'] ) ? $_POST['text'] : '' ) );
	$imgs = json_decode( wp_unslash( isset( $_POST['images'] ) ? $_POST['images'] : '[]' ), true );
	$imgs = is_array( $imgs ) ? $imgs : array();

	$res = vawc_create_item( vawc_parse_text( $raw ), $imgs, array() );
	if ( is_wp_error( $res ) ) {
		wp_send_json_error( $res->get_error_message() );
	}
	if ( ! vawc_is_pro() ) {
		vawc_inc( 1 );
	}
	$res['left'] = vawc_is_pro() ? -1 : vawc_remaining();
	wp_send_json_success( $res );
} );

add_action( 'wp_ajax_vawc_pay_info', function () {
	vawc_ajax_guard();
	$svc = vawc_hub_call( 'GET', 'service', array( 'service' => 'voice-add-woocommerce' ) );
	if ( is_wp_error( $svc ) ) {
		wp_send_json_error( $svc->get_error_message() );
	}
	$st = vawc_pro_state( true );
	wp_send_json_success( array( 'name' => $svc['name'], 'price' => $svc['price'], 'url' => $svc['url'], 'domain' => vawc_domain(), 'state' => $st['state'] ) );
} );

add_action( 'wp_ajax_vawc_pay_activate', function () {
	vawc_ajax_guard();
	$r = vawc_hub_call( 'POST', 'activate', array( 'service' => 'voice-add-woocommerce', 'domain' => vawc_domain(), 'email' => get_option( 'admin_email' ), 'site_url' => home_url() ) );
	if ( is_wp_error( $r ) ) {
		wp_send_json_error( $r->get_error_message() );
	}
	vawc_pro_state( true );
	wp_send_json_success( array( 'url' => $r['url'] ) );
} );

add_action( 'wp_ajax_vawc_remove_bg', function () {
	vawc_ajax_guard();
	$key = (string) get_option( 'vawc_rbg_api', '' );
	if ( '' === $key ) {
		wp_send_json_error( 'No API key' );
	}
	$img = wp_unslash( isset( $_POST['image'] ) ? $_POST['image'] : '' );
	$bg  = isset( $_POST['bg'] ) ? sanitize_key( $_POST['bg'] ) : 'transparent';
	$p   = is_string( $img ) ? strpos( $img, 'base64,' ) : false;
	if ( false === $p ) {
		wp_send_json_error( 'Bad image' );
	}
	$body = array( 'image_file_b64' => substr( $img, $p + 7 ), 'size' => 'auto', 'format' => 'png' );
	if ( 'ffffff' === $bg ) {
		$body['bg_color'] = 'ffffff';
	}
	$r = wp_remote_post( 'https://api.remove.bg/v1.0/removebg', array( 'timeout' => 90, 'headers' => array( 'X-Api-Key' => $key ), 'body' => $body ) );
	if ( is_wp_error( $r ) ) {
		wp_send_json_error( $r->get_error_message() );
	}
	$code = (int) wp_remote_retrieve_response_code( $r );
	if ( 200 !== $code ) {
		$j   = json_decode( wp_remote_retrieve_body( $r ), true );
		$err = isset( $j['errors'][0]['title'] ) ? $j['errors'][0]['title'] : 'HTTP ' . $code;
		wp_send_json_error( $err );
	}
	wp_send_json_success( array( 'image' => 'data:image/png;base64,' . base64_encode( wp_remote_retrieve_body( $r ) ) ) );
} );

/* ---------- CSV ---------- */
/** Нормалізований заголовок колонки → ключ поля. */
function vawc_csv_header_map( array $header ) {
	$map   = vawc_field_map();
	$extra = vawc_csv_extra_aliases();
	$out   = array();
	foreach ( $header as $i => $h ) {
		$n = vawc_norm( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $h ) );
		if ( isset( $extra[ $n ] ) ) {
			$out[ $i ] = $extra[ $n ];
		} elseif ( isset( $map[ $n ] ) ) {
			$out[ $i ] = $map[ $n ];
		}
	}
	return $out;
}

/** Імпорт із вже прочитаного тексту CSV (використовується AJAX-обробником і тестами). */
function vawc_import_csv_text( $content ) {
	$content = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $content );
	if ( ! mb_check_encoding( $content, 'UTF-8' ) ) {
		$content = mb_convert_encoding( $content, 'UTF-8', 'Windows-1251' );
	}
	$first = strtok( $content, "\n" );
	$delim = ( substr_count( (string) $first, ';' ) >= substr_count( (string) $first, ',' ) ) ? ';' : ',';

	$fh = fopen( 'php://temp', 'r+' );
	fwrite( $fh, $content );
	rewind( $fh );
	$header = fgetcsv( $fh, 0, $delim );
	if ( ! $header ) {
		return new WP_Error( 'empty', vawc_t( 'Порожній файл', 'Empty file' ) );
	}
	$hmap = vawc_csv_header_map( $header );
	if ( ! $hmap ) {
		return new WP_Error( 'cols', vawc_t( 'Не знайдено відомих колонок. Завантажте шаблон CSV.', 'No known columns found. Download the CSV template.' ) );
	}
	@set_time_limit( 300 );
	$created = 0; $failed = 0; $errors = array(); $stopped = false; $rows = 0;
	while ( ( $row = fgetcsv( $fh, 0, $delim ) ) !== false && $rows < 500 ) {
		if ( 1 === count( $row ) && '' === trim( (string) $row[0] ) ) {
			continue;
		}
		$rows++;
		if ( ! vawc_can_create( 1 ) ) {
			$stopped = true;
			break;
		}
		$data = array(); $urls = array();
		foreach ( $hmap as $i => $key ) {
			$v = isset( $row[ $i ] ) ? trim( (string) $row[ $i ] ) : '';
			if ( '' === $v ) {
				continue;
			}
			if ( '__images' === $key ) {
				$urls = array_filter( array_map( 'trim', explode( ',', $v ) ) );
			} else {
				$data[ $key ] = $v;
			}
		}
		$res = vawc_create_item( array( 'data' => $data, 'rep' => array(), 'custom' => array() ), array(), $urls );
		if ( is_wp_error( $res ) ) {
			$failed++;
			if ( count( $errors ) < 5 ) {
				$errors[] = '#' . $rows . ': ' . $res->get_error_message();
			}
			continue;
		}
		$created++;
		if ( ! vawc_is_pro() ) {
			vawc_inc( 1 );
		}
	}
	fclose( $fh );
	return array( 'created' => $created, 'failed' => $failed, 'errors' => $errors, 'stopped' => $stopped, 'left' => vawc_is_pro() ? -1 : vawc_remaining() );
}

add_action( 'wp_ajax_vawc_import', function () {
	vawc_ajax_guard();
	if ( empty( $_FILES['file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ) ) {
		wp_send_json_error( vawc_t( 'Файл не отримано', 'No file received' ) );
	}
	if ( ! preg_match( '/\.csv$/i', (string) $_FILES['file']['name'] ) || (int) $_FILES['file']['size'] > 5 * MB_IN_BYTES ) {
		wp_send_json_error( vawc_t( 'Потрібен файл .csv до 5 МБ', 'A .csv file up to 5 MB is required' ) );
	}
	$res = vawc_import_csv_text( file_get_contents( $_FILES['file']['tmp_name'] ) );
	if ( is_wp_error( $res ) ) {
		wp_send_json_error( $res->get_error_message() );
	}
	wp_send_json_success( $res );
} );

add_action( 'wp_ajax_vawc_export', function () {
	if ( ! check_ajax_referer( 'vawc_nonce', 'nonce', false ) || ! current_user_can( 'vawc_use' ) ) {
		wp_die( 'Forbidden', 403 );
	}
	$sep  = ( isset( $_GET['sep'] ) && 'comma' === $_GET['sep'] ) ? ',' : ';';
	$tpl  = ! empty( $_GET['tpl'] );
	$cols = vawc_csv_columns();
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="voice-add-woocommerce-' . ( $tpl ? 'template' : 'export' ) . '-' . gmdate( 'Y-m-d' ) . '.csv"' );
	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" );
	fputcsv( $out, array_map( function ( $c ) { return $c[0]; }, $cols ), $sep );
	foreach ( $tpl ? vawc_csv_example_rows( $cols ) : vawc_csv_export_rows( $cols ) as $r ) {
		fputcsv( $out, $r, $sep );
	}
	fclose( $out );
	exit;
} );
