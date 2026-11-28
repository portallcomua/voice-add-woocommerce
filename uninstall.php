<?php
/**
 * Clean up Voice ADD settings when the plugin is deleted.
 * Product data and media are intentionally preserved.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$manager_ids = get_users(
	array(
		'role'   => 'vawc_manager',
		'fields' => 'ID',
	)
);

foreach ( $manager_ids as $manager_id ) {
	$manager = new WP_User( $manager_id );
	$manager->remove_role( 'vawc_manager' );
	if ( empty( $manager->roles ) ) {
		$manager->set_role( 'subscriber' );
	}
}

remove_role( 'vawc_manager' );

foreach ( array( 'vawc_draft', 'vawc_lang', 'vawc_allowed_roles', 'vawc_ver' ) as $option_name ) {
	delete_option( $option_name );
}
