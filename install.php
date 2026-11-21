<?php
/**
 * Installs Retraceur in the tests database.
 *
 * Drops every table that uses the tests prefix, then runs a fresh installation.
 * Run it through `npm run phpunit:install-db` in coeur.
 */

declare( strict_types=1 );

define( 'RETRACEUR_TESTS_INSTALL', true );
define( 'WP_INSTALLING', true );

require __DIR__ . '/bootstrap.php';
require_once rtrim( ABSPATH, '/\\' ) . '/wp-admin/includes/upgrade.php';

global $wpdb;

$retraceur_tables = $wpdb->get_col(
	$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%' )
);

foreach ( $retraceur_tables as $retraceur_table ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$retraceur_table}`" );
}

wp_install( 'Retraceur tests', 'admin', 'admin@example.org', true, '', 'password' );

echo "Retraceur installed in the tests database (prefix: {$wpdb->prefix}).\n";
