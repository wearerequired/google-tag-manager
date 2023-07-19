<?php
/**
 * Functionality that is executed when the plugin is uninstalled via built-in WordPress commands.
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'required_gtm_container_id' );
