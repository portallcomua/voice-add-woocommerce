<?php
/**
 * Clean up Voice ADD settings when the plugin is deleted.
 * Product data and media are intentionally preserved.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

remove_role( 'vawc_manager' );

foreach ( array( 'vawc_draft', 'vawc_lang', 'vawc_allowed_roles', 'vawc_ver' ) as $option_name ) {
	delete_option( $option_name );
}
