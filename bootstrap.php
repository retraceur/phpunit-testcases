<?php
/**
 * Retraceur tests bootstrap.
 *
 * Loads the tests configuration, then the core, then the base test case.
 * Targets PHPUnit 12 (PHP 8.3+). No Composer, no Yoast polyfills.
 *
 * Run `npm run phpunit:install-db` in coeur once to install Retraceur in the
 * (disposable) tests database before running the tests.
 */

declare( strict_types=1 );

if ( PHP_VERSION_ID < 80300 ) {
	fwrite( STDERR, "Retraceur tests require PHP 8.3 or later.\n" );
	exit( 1 );
}

define( 'RETRACEUR_TESTS_DIR', __DIR__ );
define( 'DIR_TESTDATA', RETRACEUR_TESTS_DIR . '/data' );

$retraceur_config_file = getenv( 'RETRACEUR_TESTS_CONFIG' ) ?: __DIR__ . '/tests-config.php';

if ( ! is_readable( $retraceur_config_file ) ) {
	fwrite( STDERR, "Tests config not found: {$retraceur_config_file}\nCopy tests-config-sample.php to tests-config.php and edit it.\n" );
	exit( 1 );
}

require_once $retraceur_config_file;

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "The tests config must define ABSPATH.\n" );
	exit( 1 );
}

if ( ! defined( 'RETRACEUR_TESTS_DISPOSABLE_DB' ) || true !== RETRACEUR_TESTS_DISPOSABLE_DB ) {
	fwrite( STDERR, "Refusing to run: set RETRACEUR_TESTS_DISPOSABLE_DB to true in the tests config to confirm the database can be used for tests.\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']       ??= 'example.org';
$_SERVER['SERVER_NAME']     ??= 'example.org';
$_SERVER['SERVER_PROTOCOL'] ??= 'HTTP/1.1';
$_SERVER['REQUEST_METHOD']  ??= 'GET';
$_SERVER['REQUEST_URI']     ??= '/';

// The core expects these to be globals, and PHPUnit includes this file inside a function.
global $wpdb, $current_site, $current_blog, $wp_rewrite, $shortcode_tags, $wp, $phpmailer, $wp_theme_directories, $table_prefix;

$table_prefix = defined( 'RETRACEUR_TESTS_TABLE_PREFIX' ) ? RETRACEUR_TESTS_TABLE_PREFIX : 'wp_';

require_once rtrim( ABSPATH, '/\\' ) . '/wp-settings.php';

// The installer runs without PHPUnit, so it must not load the base test case.
if ( ! defined( 'RETRACEUR_TESTS_INSTALL' ) ) {
	require_once __DIR__ . '/src/TestCase.php';
}
