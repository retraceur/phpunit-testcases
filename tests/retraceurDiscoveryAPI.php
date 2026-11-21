<?php
use PHPUnit\Framework\Attributes\Depends;
use Retraceur\Tests\TestCase;

final class retraceurDiscoveryAPI extends TestCase {

	public function setUp(): void {
		parent::setUp();

		require_once ABSPATH . 'wp-admin/includes/discovery.php';
	}

	public function test_request_returns_the_provider_error_message() {
		add_filter(
			'pre_http_request',
			static function () {
				return array(
					'headers'  => array(),
					'body'     => wp_json_encode( array( 'message' => 'API rate limit exceeded' ) ),
					'response' => array( 'code' => 403, 'message' => 'Forbidden' ),
				);
			}
		);

		$result = retraceur_discovery_request( '/repos/owner/repo' );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'API rate limit exceeded', $result->get_error_message() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}
}
