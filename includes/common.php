<?php
/**
 * Спільне ядро Voice ADD (Voice ADD for WooCommerce PRO).
 * Language, roles, permissions, settings, product creation and CSV tools.
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
	$s = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $s, 'UTF-8' ) : strtolower( (string) $s );
	$s = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $s );
	return trim( $s );
}

/* ====================================================================== */
/* 2. ІНФОРМАЦІЯ ПРО РОЗШИРЕНУ ВЕРСІЮ                                    */
/* ====================================================================== */
function vawc_pro_url() {
	return 'https://uaserver.pp.ua/voice-add-woocommerce-pro/';
}
function vawc_pro_promo_html() {
	$url = vawc_pro_url();
	return '<section class="va-pro-card" aria-label="' . esc_attr( vawc_t( 'Розширена версія', 'Extended version' ) ) . '">' .
		'<div class="va-pro-card__top"><strong>Voice ADD PRO</strong></div>' .
		'<p>' . esc_html( vawc_t( 'Потрібні додаткові можливості? Існує розширена версія PRO. Перегляньте її опис та умови окремо.', 'Need additional capabilities? An extended PRO edition is available. Review its features and terms separately.' ) ) . '</p>' .
		'<a class="button" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( vawc_t( 'Дізнатися про PRO', 'Learn about PRO' ) ) . '</a>' .
		'</section>';
}
/* ====================================================================== */
/* 3. РОЛЬ, ПРАВА, АКТИВАЦІЯ                                               */
/* ====================================================================== */
/* ====================================================================== */
/* 4. РОЛЬ, ПРАВА, АКТИВАЦІЯ                                               */
/* ====================================================================== */
function vawc_setup() {
	$capabilities = vawc_role_caps();
	$role = get_role( 'vawc_manager' );
	if ( ! $role ) {
		add_role( 'vawc_manager', 'Voice ADD Manager', $capabilities );
		$role = get_role( 'vawc_manager' );
	}
	if ( $role ) {
		foreach ( $capabilities as $capability => $grant ) {
			if ( $grant ) {
				$role->add_cap( $capability );
			}
		}
	}
	add_option( 'vawc_draft', '1' );
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
	add_menu_page( 'Voice ADD for WooCommerce', 'Voice ADD WC', 'vawc_use', 'vawc_main', 'vawc_page_main', 'dashicons-microphone', 30 );
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
		update_option( 'vawc_draft', isset( $_POST['draft'] ) ? '1' : '0' );
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
	$draft   = '1' === get_option( 'vawc_draft', '1' );
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

		<?php echo wp_kses_post( vawc_pro_promo_html() ); ?>

		<video id="va_cam" autoplay playsinline muted></video>
		<div id="va_gal" class="va-gal"></div>

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
	$raw  = sanitize_textarea_field( wp_unslash( isset( $_POST['text'] ) ? $_POST['text'] : '' ) );
	$imgs = json_decode( sanitize_text_field( wp_unslash( isset( $_POST['images'] ) ? $_POST['images'] : '[]' ) ), true );
	$imgs = is_array( $imgs ) ? $imgs : array();

	$res = vawc_create_item( vawc_parse_text( $raw ), $imgs, array() );
	if ( is_wp_error( $res ) ) {
		wp_send_json_error( $res->get_error_message() );
	}
	wp_send_json_success( $res );
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
	if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $content, 'UTF-8' ) && function_exists( 'mb_convert_encoding' ) ) {
		$content = mb_convert_encoding( $content, 'UTF-8', 'Windows-1251' );
	} elseif ( ! function_exists( 'mb_check_encoding' ) && function_exists( 'iconv' ) ) {
		$converted = iconv( 'Windows-1251', 'UTF-8//IGNORE', $content );
		if ( false !== $converted ) {
			$content = $converted;
		}
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
	$created = 0; $failed = 0; $errors = array(); $rows = 0;
	while ( ( $row = fgetcsv( $fh, 0, $delim ) ) !== false && $rows < 500 ) {
		if ( 1 === count( $row ) && '' === trim( (string) $row[0] ) ) {
			continue;
		}
		$rows++;
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
	}
	fclose( $fh );
	return array( 'created' => $created, 'failed' => $failed, 'errors' => $errors, 'stopped' => false );
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
