<?php
/**
 * Base test case for Retraceur tests.
 */

declare( strict_types=1 );

namespace Retraceur\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Isolates each test: database changes are rolled back, hooks and the
 * object cache are restored, and the current user is reset.
 *
 * Limitation: statements that implicitly commit (DDL such as CREATE TABLE)
 * cannot be rolled back.
 */
abstract class TestCase extends PHPUnitTestCase {

	/**
	 * Hook globals saved before each test.
	 *
	 * @var array<string, mixed>
	 */
	private array $hooks_backup = array();

	protected function setUp(): void {
		parent::setUp();

		$this->backup_hooks();
		wp_set_current_user( 0 );
		$this->start_transaction();
	}

	protected function tearDown(): void {
		$this->rollback_transaction();
		$this->restore_hooks();
		wp_set_current_user( 0 );
		wp_cache_flush();

		parent::tearDown();
	}

	private function start_transaction(): void {
		global $wpdb;

		$wpdb->query( 'START TRANSACTION' );
	}

	private function rollback_transaction(): void {
		global $wpdb;

		$wpdb->query( 'ROLLBACK' );
	}

	private function backup_hooks(): void {
		foreach ( array( 'wp_filter', 'wp_actions', 'wp_filters', 'wp_current_filter' ) as $name ) {
			$value = $GLOBALS[ $name ] ?? null;

			if ( 'wp_filter' === $name && is_array( $value ) ) {
				foreach ( $value as $tag => $hook ) {
					if ( is_object( $hook ) ) {
						$value[ $tag ] = clone $hook;
					}
				}
			}

			$this->hooks_backup[ $name ] = $value;
		}
	}

	private function restore_hooks(): void {
		foreach ( $this->hooks_backup as $name => $value ) {
			$GLOBALS[ $name ] = $value;
		}
	}
}
