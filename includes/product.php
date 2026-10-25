<?php
/**
 * Voice ADD for WooCommerce — частина, що працює з товарами WooCommerce.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vawc_role_caps() {
	return array(
		'read'                    => true,
		'upload_files'            => true,
		'edit_products'           => true,
		'publish_products'        => true,
		'edit_published_products' => true,
		'read_private_products'   => true,
		'manage_product_terms'    => true,
		'edit_product_terms'      => true,
		'assign_product_terms'    => true,
	);
}

add_action( 'admin_notices', function () {
	if ( ! class_exists( 'WooCommerce' ) && current_user_can( 'manage_options' ) ) {
		echo '<div class="notice notice-error"><p><strong>Voice ADD for WooCommerce:</strong> ' . esc_html( vawc_t( 'потрібен активний плагін WooCommerce.', 'the WooCommerce plugin must be active.' ) ) . '</p></div>';
	}
} );

function vawc_truthy( $v ) {
	return in_array( vawc_norm( $v ), array( 'так', 'yes', 'y', '1', 'true', 'да', 'ага', 'ок', 'ok', 'plus', 'плюс' ), true );
}

/* ---------------------------- ПОЛЯ ---------------------------- */
function vawc_base_fields() {
	return array(
		array( 'key' => 'name', 'ua' => 'Назва', 'en' => 'Name', 'aliases' => array( 'название', 'title', 'product name', 'назва товару' ), 'kind' => 'text', 'tpl' => true,
			'ex' => array( 'Холодильник Samsung', 'Samsung refrigerator' ), 'desc' => array( 'Назва товару', 'Product name' ) ),
		array( 'key' => 'brand', 'ua' => 'Бренд', 'en' => 'Brand', 'aliases' => array( 'виробник', 'brands' ), 'kind' => 'text', 'tpl' => true,
			'ex' => array( 'Samsung', 'Samsung' ), 'desc' => array( 'Бренд / виробник', 'Brand / manufacturer' ) ),
		array( 'key' => 'price', 'ua' => 'Ціна', 'en' => 'Price', 'aliases' => array( 'цена', 'regular price', 'ціна товару' ), 'kind' => 'text', 'tpl' => true,
			'ex' => array( '15000', '150' ), 'desc' => array( 'Звичайна ціна', 'Regular price' ) ),
		array( 'key' => 'sale', 'ua' => 'Акція', 'en' => 'Sale', 'aliases' => array( 'знижка', 'акційна ціна', 'sale price', 'discount' ), 'kind' => 'text', 'tpl' => true,
			'ex' => array( '13500', '120' ), 'desc' => array( 'Акційна ціна', 'Sale price' ) ),
		array( 'key' => 'sku', 'ua' => 'Артикул', 'en' => 'SKU', 'aliases' => array( 'код товару', 'артікул' ), 'kind' => 'text', 'tpl' => true,
			'ex' => array( 'SM-RF-001', 'SM-RF-001' ), 'desc' => array( 'Унікальний артикул', 'Unique SKU' ) ),
		array( 'key' => 'stock', 'ua' => 'Кількість', 'en' => 'Stock', 'aliases' => array( 'запас', 'залишок', 'quantity', 'qty', 'в наявності' ), 'kind' => 'text', 'tpl' => true,
			'ex' => array( '5', '5' ), 'desc' => array( 'Залишок на складі', 'Stock quantity' ) ),
		array( 'key' => 'weight', 'ua' => 'Вага', 'en' => 'Weight', 'aliases' => array( 'weight kg' ), 'kind' => 'text', 'tpl' => true,
			'ex' => array( '65', '65' ), 'desc' => array( 'Вага (одиниці з налаштувань WooCommerce)', 'Weight (units from WooCommerce settings)' ) ),
		array( 'key' => 'category', 'ua' => 'Категорія', 'en' => 'Category', 'aliases' => array( 'категорії', 'categories', 'категория' ), 'kind' => 'list', 'join' => ', ', 'tpl' => true,
			'ex' => array( 'Побутова техніка > Холодильники', 'Appliances > Refrigerators' ), 'desc' => array( 'Категорія (вкладену записуйте через «>»; кілька — через кому)', 'Category (nested with “>”; several separated by commas)' ) ),
		array( 'key' => 'tags', 'ua' => 'Мітки', 'en' => 'Tags', 'aliases' => array( 'мітка', 'теги', 'тег', 'tag' ), 'kind' => 'list', 'join' => ', ', 'tpl' => true,
			'ex' => array( 'новинка, хіт', 'new, hit' ), 'desc' => array( 'Мітки через кому', 'Tags separated by commas' ) ),
		array( 'key' => 'description', 'ua' => 'Опис', 'en' => 'Description', 'aliases' => array( 'описание', 'description text' ), 'kind' => 'text', 'tpl' => true,
			'ex' => array( 'Двокамерний, No Frost', 'Double door, No Frost' ), 'desc' => array( 'Опис товару', 'Product description' ) ),
		array( 'key' => 'attribute', 'ua' => 'Атрибут', 'en' => 'Attribute', 'aliases' => array( 'атрибути', 'attributes' ), 'kind' => 'attr', 'tpl' => true,
			'ex' => array( 'Атрибут колір значення червоний значення жовтий', 'Attribute color value red value yellow' ), 'desc' => array( 'Скажіть «Атрибут», назву, слово «значення» і значення; можна повторювати', 'Say “Attribute”, the name, the word “value” and the value; repeatable' ) ),
		array( 'key' => 'variations', 'ua' => 'Варіації', 'en' => 'Variations', 'aliases' => array( 'варіативний', 'варіативний товар', 'variable', 'variable product' ), 'kind' => 'text', 'tpl' => true,
			'ex' => array( 'так', 'yes' ), 'desc' => array( 'Скажіть «так» — створить усі комбінації атрибутів як варіативний товар', 'Say “yes” — creates all attribute combinations as a variable product' ) ),
		array( 'key' => 'variation', 'ua' => 'Варіація', 'en' => 'Variation', 'aliases' => array( 'варіанти', 'варіант' ), 'kind' => 'repeat', 'tpl' => false,
			'ex' => array( 'червоний M ціна 300 кількість 4', 'red M price 300 quantity 4' ), 'desc' => array( 'Окрема комбінація: назвіть значення атрибутів, «ціна …», «кількість …» (можна повторювати)', 'One combination: name the attribute values, “price …”, “quantity …” (repeatable)' ) ),
	);
}

function vawc_dynamic_fields() {
	return array(); // атрибути диктуються єдиним маяком «Атрибут»
}

/** Глобальні атрибути WooCommerce. */
function vawc_attr_defs() {
	$out = array();
	if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
		foreach ( (array) wc_get_attribute_taxonomies() as $a ) {
			$out[] = array( 'key' => 'attr:' . $a->attribute_name, 'slug' => $a->attribute_name, 'label' => $a->attribute_label );
		}
	}
	return $out;
}

/* ---------------------------- ІНСТРУКЦІЇ ---------------------------- */
function vawc_extra_instructions() {
	$blocks = array();

	// Атрибути магазину
	ob_start();
	$attrs = function_exists( 'wc_get_attribute_taxonomies' ) ? (array) wc_get_attribute_taxonomies() : array();
	if ( $attrs ) {
		echo '<table class="va-table"><thead><tr><th>' . esc_html( vawc_t( 'Скажіть', 'Say' ) ) . '</th><th>' . esc_html( vawc_t( 'Значення у вашому магазині', 'Values in your store' ) ) . '</th></tr></thead><tbody>';
		foreach ( $attrs as $a ) {
			$tax   = wc_attribute_taxonomy_name( $a->attribute_name );
			$terms = taxonomy_exists( $tax ) ? get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'number' => 30, 'fields' => 'names' ) ) : array();
			$terms = is_wp_error( $terms ) ? array() : $terms;
			echo '<tr><td><b>' . esc_html( vawc_t( 'Атрибут ', 'Attribute ' ) . $a->attribute_label ) . '</b><br><span class="va-muted">' . esc_html( vawc_t( 'значення ', 'value ' ) ) . '…</span></td><td>' . ( $terms ? esc_html( implode( ', ', $terms ) ) : '<span class="va-muted">' . esc_html( vawc_t( 'значень ще немає — нові будуть створені автоматично', 'no values yet — new ones are created automatically' ) ) . '</span>' ) . '</td></tr>';
		}
		echo '</tbody></table>';
	} else {
		echo '<p>' . esc_html( vawc_t( 'У WooCommerce ще немає глобальних атрибутів (Товари → Атрибути).', 'There are no global WooCommerce attributes yet (Products → Attributes).' ) ) . '</p>';
	}
	echo '<p>' . esc_html( vawc_t( 'Як диктувати:', 'How to dictate:' ) ) . '</p><pre class="va-pre">' . esc_html( vawc_t( 'Атрибут колір значення червоний значення жовтий Атрибут стан значення новий', 'Attribute color value red value yellow Attribute condition value new' ) ) . '</pre>';
	echo '<p>' . esc_html( vawc_t( 'Назва атрибута магазину також працює як маяк: «Колір червоний» = «Атрибут колір значення червоний». Якщо атрибута або його значення ще немає — вони створюються автоматично (новий атрибут з’явиться в Товари → Атрибути). Те саме з категоріями, мітками та брендом.', 'A store attribute name also works as a beacon: “Color red” = “Attribute color value red”. If the attribute or its value does not exist yet, it is created automatically (a new attribute appears under Products → Attributes). The same applies to categories, tags and brand.' ) ) . '</p>';
	$blocks[] = array( 'id' => 'va_i_attrs', 'title' => vawc_t( 'Атрибути вашого магазину', 'Your store attributes' ), 'html' => ob_get_clean() );

	// Варіативні товари
	ob_start();
	echo '<p>' . esc_html( vawc_t( 'Варіативний товар — це товар з комбінаціями атрибутів (наприклад, Колір × Розмір).', 'A variable product has attribute combinations (e.g. Color × Size).' ) ) . '</p>';
	echo '<ol><li>' . esc_html( vawc_t( 'Надиктуйте атрибути: «Атрибут Колір» → «червоний, синій», «Атрибут Розмір» → «S, M».', 'Dictate attributes: “Attribute Color” → “red, blue”, “Attribute Size” → “S, M”.' ) ) . '</li>';
	echo '<li>' . esc_html( vawc_t( 'Скажіть «Варіації» → «так» — будуть створені всі комбінації за ціною товару.', 'Say “Variations” → “yes” — all combinations are created with the product price.' ) ) . '</li>';
	echo '<li>' . esc_html( vawc_t( 'Для окремих комбінацій скажіть «Варіація» → «червоний M ціна 300 кількість 4». Маяк «Варіація» можна повторювати.', 'For single combinations say “Variation” → “red M price 300 quantity 4”. The “Variation” beacon can be repeated.' ) ) . '</li></ol>';
	echo '<pre class="va-pre">' . esc_html( vawc_t(
		"Назва: Футболка\nЦіна: 400\nАтрибут Колір: червоний, синій\nАтрибут Розмір: S, M\nВаріації: так\nВаріація: червоний M ціна 450 кількість 3",
		"Name: T-shirt\nPrice: 400\nAttribute Color: red, blue\nAttribute Size: S, M\nVariations: yes\nVariation: red M price 450 quantity 3"
	) ) . '</pre>';
	echo '<p>' . esc_html( vawc_t( 'Спочатку продиктуйте основні поля товару (ціна, кількість), а вже потім «Варіація…». Усередині рядка «Варіація» слова «ціна», «кількість», «артикул» вважаються текстом варіації.', 'Dictate the main product fields (price, stock) first, then “Variation…”. Inside a “Variation” line the words “price”, “quantity”, “SKU” are treated as part of that variation.' ) ) . '</p>';
	echo '<p>' . esc_html( vawc_t( 'Атрибути надиктовуються так: «Атрибут колір значення червоний значення синій Атрибут розмір значення S значення M».', 'Dictate attributes like: “Attribute color value red value blue Attribute size value S value M”.' ) ) . '</p>';
	$blocks[] = array( 'id' => 'va_i_vars', 'title' => vawc_t( 'Варіативні товари', 'Variable products' ), 'html' => ob_get_clean() );

	// Категорії
	ob_start();
	$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'number' => 60, 'fields' => 'names' ) );
	if ( ! is_wp_error( $cats ) && $cats ) {
		echo '<p>' . esc_html( implode( ' · ', $cats ) ) . '</p>';
	} else {
		echo '<p>' . esc_html( vawc_t( 'Категорій ще немає — нові створюються автоматично.', 'No categories yet — new ones are created automatically.' ) ) . '</p>';
	}
	$blocks[] = array( 'id' => 'va_i_cats', 'title' => vawc_t( 'Ваші категорії', 'Your categories' ), 'html' => ob_get_clean() );

	return $blocks;
}

/* ---------------------------- CSV ---------------------------- */
function vawc_csv_columns() {
	$cols = array();
	foreach ( array( 'name', 'sku', 'price', 'sale', 'stock', 'weight', 'brand', 'category', 'tags', 'description' ) as $k ) {
		$d      = vawc_field_def( $k );
		$cols[] = array( vawc_label( $d ), $k );
	}
	$cols[] = array( 'Images', '__images' );
	foreach ( vawc_attr_defs() as $a ) {
		$cols[] = array( vawc_t( 'Атрибут ', 'Attribute ' ) . $a['label'], $a['key'] );
	}
	return $cols;
}
function vawc_csv_extra_aliases() {
	$m = array( 'images' => '__images', 'image' => '__images', 'фото' => '__images', 'зображення' => '__images', 'картинки' => '__images', 'pictures' => '__images' );
	foreach ( vawc_attr_defs() as $a ) {
		$m[ vawc_norm( 'Атрибут ' . $a['label'] ) ]   = $a['key'];
		$m[ vawc_norm( 'Attribute ' . $a['label'] ) ] = $a['key'];
	}
	return $m;
}
function vawc_csv_example_rows( $cols ) {
	$ex = array(
		'name' => 'Футболка', 'sku' => 'TS-001', 'price' => '400', 'sale' => '', 'stock' => '10', 'weight' => '0.2',
		'brand' => 'Nike', 'category' => 'Одяг > Футболки', 'tags' => 'новинка, хіт', 'description' => 'Бавовняна футболка',
		'__images' => 'https://example.com/photo1.jpg',
	);
	$row = array();
	foreach ( $cols as $c ) {
		$row[] = isset( $ex[ $c[1] ] ) ? $ex[ $c[1] ] : '';
	}
	return array( $row );
}
function vawc_csv_export_rows( $cols ) {
	$rows = array();
	$ids  = wc_get_products( array( 'limit' => 2000, 'status' => array( 'publish', 'draft', 'private', 'pending' ), 'return' => 'objects', 'orderby' => 'date', 'order' => 'DESC' ) );
	foreach ( $ids as $p ) {
		$pid  = $p->get_id();
		$imgs = array();
		if ( $p->get_image_id() ) {
			$imgs[] = wp_get_attachment_url( $p->get_image_id() );
		}
		foreach ( $p->get_gallery_image_ids() as $gid ) {
			$imgs[] = wp_get_attachment_url( $gid );
		}
		$brand = '';
		if ( taxonomy_exists( 'product_brand' ) ) {
			$brand = implode( ', ', (array) wp_get_object_terms( $pid, 'product_brand', array( 'fields' => 'names' ) ) );
		}
		$vals = array(
			'name'        => $p->get_name(),
			'sku'         => $p->get_sku(),
			'price'       => $p->get_regular_price(),
			'sale'        => $p->get_sale_price(),
			'stock'       => $p->get_stock_quantity(),
			'weight'      => $p->get_weight(),
			'brand'       => $brand,
			'category'    => implode( ', ', (array) wp_get_object_terms( $pid, 'product_cat', array( 'fields' => 'names' ) ) ),
			'tags'        => implode( ', ', (array) wp_get_object_terms( $pid, 'product_tag', array( 'fields' => 'names' ) ) ),
			'description' => $p->get_description(),
			'__images'    => implode( ', ', array_filter( $imgs ) ),
		);
		foreach ( vawc_attr_defs() as $a ) {
			$tax = wc_attribute_taxonomy_name( $a['slug'] );
			$vals[ $a['key'] ] = taxonomy_exists( $tax ) ? implode( ', ', (array) wp_get_object_terms( $pid, $tax, array( 'fields' => 'names' ) ) ) : '';
		}
		$row = array();
		foreach ( $cols as $c ) {
			$v     = isset( $vals[ $c[1] ] ) ? $vals[ $c[1] ] : '';
			$row[] = is_string( $v ) && preg_match( '/^[=+\-@]/', $v ) ? "'" . $v : $v; // захист від CSV-ін'єкцій
		}
		$rows[] = $row;
	}
	return $rows;
}

/* ---------------------------- СТВОРЕННЯ ТОВАРУ ---------------------------- */
/** Транслітерація назви в латинський slug (для slug атрибута WooCommerce). */
function vawc_slug( $s ) {
	$map = array( 'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'h', 'ґ' => 'g', 'д' => 'd', 'е' => 'e', 'є' => 'ye', 'ж' => 'zh', 'з' => 'z', 'и' => 'y', 'і' => 'i', 'ї' => 'yi', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ь' => '', 'ю' => 'yu', 'я' => 'ya', 'ы' => 'y', 'э' => 'e', 'ё' => 'yo', 'ъ' => '' );
	$s   = strtr( mb_strtolower( (string) $s, 'UTF-8' ), $map );
	$s   = sanitize_title( $s );
	return substr( $s, 0, 20 );
}

/**
 * Глобальний атрибут за назвою: існуючий або НОВИЙ (створюється автоматично).
 * Повертає назву таксономії (pa_xxx) або '' якщо не вдалося.
 */
function vawc_ensure_global_attr( $label ) {
	$label = trim( $label );
	$key   = vawc_resolve_attr( $label );
	if ( $key ) {
		return wc_attribute_taxonomy_name( substr( $key, 5 ) );
	}
	$slug = vawc_slug( $label );
	if ( '' === $slug || ! function_exists( 'wc_create_attribute' ) ) {
		return '';
	}
	$id = wc_create_attribute( array( 'name' => $label, 'slug' => $slug, 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false ) );
	if ( is_wp_error( $id ) ) {
		return taxonomy_exists( wc_attribute_taxonomy_name( $slug ) ) ? wc_attribute_taxonomy_name( $slug ) : '';
	}
	delete_transient( 'wc_attribute_taxonomies' );
	$tax = wc_attribute_taxonomy_name( $slug );
	if ( ! taxonomy_exists( $tax ) ) {
		register_taxonomy( $tax, apply_filters( 'woocommerce_taxonomy_objects_' . $tax, array( 'product' ) ), array( 'labels' => array( 'name' => $label ), 'hierarchical' => false, 'show_ui' => false, 'query_var' => true, 'rewrite' => false, 'public' => false ) );
	}
	return $tax;
}

function vawc_split_values( $s ) {
	return array_values( array_unique( array_filter( array_map( 'trim', preg_split( '/[,;]+/u', (string) $s ) ) ) ) );
}

function vawc_parse_variation_line( $line, $axes ) {
	$price = $stock = $sku = '';
	if ( preg_match( '/(?:ціна|ціну|цена|price)\s*[:=]?\s*(-?\d+(?:[.,]\d+)?)/iu', $line, $m ) ) {
		$price = vawc_num( $m[1] );
		$line  = str_replace( $m[0], ' ', $line );
	}
	if ( preg_match( '/(?:кількість|кількості|запас|залишок|stock|qty|quantity)\s*[:=]?\s*(\d+)/iu', $line, $m ) ) {
		$stock = $m[1];
		$line  = str_replace( $m[0], ' ', $line );
	}
	if ( preg_match( '/(?:артикул|sku)\s*[:=]?\s*([\w\-\.]+)/iu', $line, $m ) ) {
		$sku  = $m[1];
		$line = str_replace( $m[0], ' ', $line );
	}
	$n   = ' ' . vawc_norm( $line ) . ' ';
	$sel = array();
	foreach ( $axes as $ai => $ax ) {
		$best = -1;
		$bl   = 0;
		foreach ( $ax['opts'] as $oi => $o ) {
			$t = vawc_norm( $o['text'] );
			if ( '' === $t ) {
				continue;
			}
			$t = ' ' . $t . ' ';
			if ( false !== strpos( $n, $t ) && strlen( $t ) > $bl ) {
				$best = $oi;
				$bl   = strlen( $t );
			}
		}
		if ( $best >= 0 ) {
			$sel[ $ai ] = $best;
		}
	}
	return array( $sel, $price, $stock, $sku );
}

function vawc_combos( $axes, $cap = 100 ) {
	$combos = array( array() );
	foreach ( $axes as $ai => $ax ) {
		$next = array();
		foreach ( $combos as $c ) {
			foreach ( array_keys( $ax['opts'] ) as $oi ) {
				$cc        = $c;
				$cc[ $ai ] = $oi;
				$next[]    = $cc;
				if ( count( $next ) >= $cap ) {
					break 2;
				}
			}
		}
		$combos = $next;
	}
	return $combos;
}

function vawc_create_item( $p, $imgs = array(), $urls = array() ) {
	if ( ! class_exists( 'WC_Product_Simple' ) ) {
		return new WP_Error( 'wc', vawc_t( 'WooCommerce не активний', 'WooCommerce is not active' ) );
	}
	$d    = $p['data'];
	$rep  = $p['rep'];
	$warn = array();
	if ( empty( $d['name'] ) ) {
		return new WP_Error( 'name', vawc_t( 'Вкажіть назву товару (маяк «Назва»)', 'Enter the product name (beacon “Name”)' ) );
	}

	// --- атрибути: назви зі слів диктовки зіставляються з атрибутами магазину ---
	$custom_left = array();
	foreach ( (array) $p['custom'] as $cname => $cval ) {
		$ck = vawc_resolve_attr( $cname );
		if ( $ck ) {
			$d[ $ck ] = isset( $d[ $ck ] ) ? $d[ $ck ] . ', ' . $cval : $cval;
		} else {
			$custom_left[ $cname ] = $cval;
		}
	}
	$attr_in = array();
	foreach ( $d as $k => $v ) {
		if ( 0 !== strpos( $k, 'attr:' ) ) {
			continue;
		}
		$slug = substr( $k, 5 );
		$tax  = wc_attribute_taxonomy_name( $slug );
		if ( ! taxonomy_exists( $tax ) ) {
			continue;
		}
		$vals = vawc_split_values( $v );
		if ( $vals ) {
			$attr_in[] = array( 'label' => wc_attribute_label( $tax ), 'tax' => $tax, 'values' => $vals );
		}
	}
	foreach ( $custom_left as $label => $v ) {
		$vals = vawc_split_values( $v );
		if ( ! $vals ) {
			continue;
		}
		$label = sanitize_text_field( $label );
		$tax   = vawc_ensure_global_attr( $label ); // нового атрибута ще немає — створюємо
		$attr_in[] = array( 'label' => $tax ? wc_attribute_label( $tax ) : $label, 'tax' => $tax, 'values' => $vals );
	}
	$is_var = $attr_in && ( ! empty( $rep['variation'] ) || vawc_truthy( isset( $d['variations'] ) ? $d['variations'] : '' ) );

	$product = $is_var ? new WC_Product_Variable() : new WC_Product_Simple();
	$product->set_status( '1' === get_option( 'vawc_draft', '1' ) ? 'draft' : 'publish' );
	$product->set_name( sanitize_text_field( $d['name'] ) );
	$price = isset( $d['price'] ) ? vawc_num( $d['price'] ) : '';
	$sale  = isset( $d['sale'] ) ? vawc_num( $d['sale'] ) : '';
	if ( ! $is_var ) {
		if ( '' !== $price ) {
			$product->set_regular_price( $price );
		}
		if ( '' !== $sale ) {
			$product->set_sale_price( $sale );
		}
		if ( isset( $d['stock'] ) && '' !== vawc_num( $d['stock'] ) ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity( (int) vawc_num( $d['stock'] ) );
		}
	}
	if ( isset( $d['weight'] ) && '' !== vawc_num( $d['weight'] ) ) {
		$product->set_weight( vawc_num( $d['weight'] ) );
	}
	if ( ! empty( $d['description'] ) ) {
		$product->set_description( wp_kses_post( $d['description'] ) );
	}
	if ( ! empty( $d['sku'] ) ) {
		$sku = sanitize_text_field( $d['sku'] );
		if ( wc_get_product_id_by_sku( $sku ) ) {
			$warn[] = vawc_t( 'Артикул уже існує — не збережено: ', 'SKU already exists — not saved: ' ) . $sku;
		} else {
			try { $product->set_sku( $sku ); } catch ( Exception $e ) { $warn[] = $e->getMessage(); }
		}
	}

	// Атрибути → WC_Product_Attribute; для варіацій готуємо осі.
	$wc_attrs = array();
	$axes     = array();
	$pos      = 0;
	foreach ( $attr_in as $a ) {
		$at = new WC_Product_Attribute();
		if ( $a['tax'] ) {
			$ids = vawc_term_ids( $a['values'], $a['tax'] );
			$at->set_id( wc_attribute_taxonomy_id_by_name( $a['tax'] ) );
			$at->set_name( $a['tax'] );
			$at->set_options( $ids );
			$opts = array();
			foreach ( $ids as $tid ) {
				$term = get_term( $tid, $a['tax'] );
				if ( $term && ! is_wp_error( $term ) ) {
					$opts[] = array( 'text' => $term->name, 'val' => $term->slug );
				}
			}
			$key = $a['tax'];
		} else {
			$at->set_id( 0 );
			$at->set_name( $a['label'] );
			$at->set_options( $a['values'] );
			$opts = array();
			foreach ( $a['values'] as $val ) {
				$opts[] = array( 'text' => $val, 'val' => $val );
			}
			$key = sanitize_title( $a['label'] );
		}
		$at->set_position( $pos++ );
		$at->set_visible( true );
		$at->set_variation( (bool) $is_var );
		$wc_attrs[]  = $at;
		$axes[]      = array( 'key' => $key, 'opts' => $opts );
	}
	// Бренд: таксономія product_brand (WooCommerce 9.6+) або глобальний атрибут «Бренд» (створюється автоматично)
	$brand = isset( $d['brand'] ) ? sanitize_text_field( $d['brand'] ) : '';
	if ( $brand && ! taxonomy_exists( 'product_brand' ) ) {
		$btax = vawc_ensure_global_attr( vawc_t( 'Бренд', 'Brand' ) );
		if ( $btax ) {
			$bt = new WC_Product_Attribute();
			$bt->set_id( wc_attribute_taxonomy_id_by_name( $btax ) );
			$bt->set_name( $btax );
			$bt->set_options( vawc_term_ids( array( $brand ), $btax ) );
		} else {
			$bt = new WC_Product_Attribute();
			$bt->set_id( 0 );
			$bt->set_name( vawc_t( 'Бренд', 'Brand' ) );
			$bt->set_options( array( $brand ) );
		}
		$bt->set_position( $pos++ );
		$bt->set_visible( true );
		$bt->set_variation( false );
		$wc_attrs[] = $bt;
	}
	if ( $wc_attrs ) {
		$product->set_attributes( $wc_attrs );
	}

	// Фото
	$pid = $product->save();
	if ( ! $pid ) {
		return new WP_Error( 'save', vawc_t( 'Не вдалося створити товар', 'Could not create the product' ) );
	}
	$aids = array_merge( vawc_save_images( $pid, $imgs ), vawc_sideload_images( $pid, $urls ) );
	if ( $aids ) {
		$product->set_image_id( $aids[0] );
		if ( count( $aids ) > 1 ) {
			$product->set_gallery_image_ids( array_slice( $aids, 1 ) );
		}
		$product->save();
	}

	// Категорії, мітки, бренд
	if ( ! empty( $d['category'] ) ) {
		$tids = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', $d['category'] ) ) ) as $path ) {
			$t = vawc_term_path( $path, 'product_cat' );
			if ( $t ) {
				$tids[] = $t;
			}
		}
		if ( $tids ) {
			wp_set_object_terms( $pid, $tids, 'product_cat' );
		}
	}
	if ( ! empty( $d['tags'] ) ) {
		$tids = vawc_term_ids( vawc_split_values( $d['tags'] ), 'product_tag' );
		if ( $tids ) {
			wp_set_object_terms( $pid, $tids, 'product_tag' );
		}
	}
	if ( $brand && taxonomy_exists( 'product_brand' ) ) {
		$tids = vawc_term_ids( array( $brand ), 'product_brand' );
		if ( $tids ) {
			wp_set_object_terms( $pid, $tids, 'product_brand' );
		}
	}

	// Варіації
	$nvar = 0;
	if ( $is_var ) {
		$wanted = array(); // підпис => [sel, price, stock, sku]
		if ( vawc_truthy( isset( $d['variations'] ) ? $d['variations'] : '' ) ) {
			foreach ( vawc_combos( $axes ) as $c ) {
				ksort( $c );
				$wanted[ wp_json_encode( $c ) ] = array( $c, $price, isset( $d['stock'] ) ? vawc_num( $d['stock'] ) : '', '' );
			}
		}
		foreach ( (array) $rep['variation'] as $line ) {
			list( $sel, $vp, $vs, $vk ) = vawc_parse_variation_line( $line, $axes );
			if ( ! $sel ) {
				$warn[] = vawc_t( 'Варіацію не розпізнано: ', 'Variation not recognized: ' ) . $line;
				continue;
			}
			ksort( $sel );
			$sig = wp_json_encode( $sel );
			$old = isset( $wanted[ $sig ] ) ? $wanted[ $sig ] : array( $sel, $price, '', '' );
			$wanted[ $sig ] = array( $sel, '' !== $vp ? $vp : $old[1], '' !== $vs ? $vs : $old[2], $vk ? $vk : $old[3] );
		}
		foreach ( $wanted as $w ) {
			list( $sel, $vp, $vs, $vk ) = $w;
			$v    = new WC_Product_Variation();
			$v->set_parent_id( $pid );
			$attrs = array();
			foreach ( $axes as $ai => $ax ) {
				$attrs[ $ax['key'] ] = isset( $sel[ $ai ] ) ? $ax['opts'][ $sel[ $ai ] ]['val'] : '';
			}
			$v->set_attributes( $attrs );
			$v->set_status( 'publish' );
			if ( '' !== $vp ) {
				$v->set_regular_price( $vp );
				if ( '' !== $sale && $vp === $price ) {
					$v->set_sale_price( $sale );
				}
			}
			if ( '' !== $vs ) {
				$v->set_manage_stock( true );
				$v->set_stock_quantity( (int) $vs );
			}
			if ( $vk && ! wc_get_product_id_by_sku( $vk ) ) {
				try { $v->set_sku( $vk ); } catch ( Exception $e ) { $warn[] = $e->getMessage(); }
			}
			$v->save();
			$nvar++;
		}
		WC_Product_Variable::sync( $pid );
		wc_delete_product_transients( $pid );
	}

	return array(
		'id'         => $pid,
		'title'      => $product->get_name(),
		'edit'       => get_edit_post_link( $pid, 'raw' ),
		'variations' => $nvar,
		'warnings'   => $warn,
	);
}
