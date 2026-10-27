<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
remove_role( 'vawc_manager' );
foreach ( array( 'vawc_hub', 'vawc_pro', 'vawc_draft', 'vawc_rbg_api', 'vawc_lang', 'vawc_allowed_roles', 'vawc_ver' ) as $vawc_option_name ) {
	delete_option( $vawc_option_name );
}
delete_metadata( 'user', 0, 'vawc_ops', '', true );
delete_metadata( 'user', 0, 'vawc_pro_since', '', true );
