<?php
/**
 * Retraceur tests configuration for the bacÀsable `phpunit` command (Playground, PHP Wasm).
 *
 * No secret here: Playground provides an empty SQLite database, so the credentials below
 * are placeholders that the SQLite drop-in ignores.
 */

// This repository is mounted in <retraceur>/phpunit-testcases, so the core is one level up.
define( 'ABSPATH', dirname( __DIR__ ) . '/' );

define( 'RETRACEUR_TESTS_DISPOSABLE_DB', true );

// The database is created empty for each run: there is nothing to drop before installing.
define( 'RETRACEUR_TESTS_FRESH_DB', true );

define( 'RETRACEUR_TESTS_TABLE_PREFIX', 'wp_' );

define( 'DB_NAME', 'retraceur' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as $retraceur_key ) {
	define( $retraceur_key, 'tests' );
}

define( 'WP_DEBUG', true );
