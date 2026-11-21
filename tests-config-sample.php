<?php
/**
 * Retraceur tests configuration.
 *
 * Copy this file to tests-config.php (git-ignored) and edit it.
 */

// This repository is cloned into <coeur>/phpunit-testcases, so the core is one level up.
// Adapt if wp-settings.php is not at the root of coeur.
define( 'ABSPATH', dirname( __DIR__ ) . '/' );

// Confirms the database can be used for tests. Never point this at a real site.
define( 'RETRACEUR_TESTS_DISPOSABLE_DB', true );

define( 'RETRACEUR_TESTS_TABLE_PREFIX', 'rtests_' );

// MAMP defaults on macOS: MySQL on port 8889, user root, password root.
define( 'DB_NAME', getenv( 'RETRACEUR_TESTS_DB_NAME' ) ?: 'retraceur_tests' );
define( 'DB_USER', getenv( 'RETRACEUR_TESTS_DB_USER' ) ?: 'root' );
define( 'DB_PASSWORD', getenv( 'RETRACEUR_TESTS_DB_PASSWORD' ) ?: 'root' );
define( 'DB_HOST', getenv( 'RETRACEUR_TESTS_DB_HOST' ) ?: '127.0.0.1:8889' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

// Authentication keys are required by the core. Any value works for tests.
foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as $retraceur_key ) {
	define( $retraceur_key, 'tests' );
}

define( 'WP_DEBUG', true );
