<?php
use PHPUnit\Framework\Attributes\DataProvider;
use Retraceur\Tests\TestCase;

final class retraceurDiscoveryAPI extends TestCase {

	public static function setUpBeforeClass():void {
		parent::setUpBeforeClass();

		require_once ABSPATH . 'wp-admin/includes/discovery.php';
	}

	public function setUp(): void {
		parent::setUp();

		delete_transient( 'retraceur_discovery_installed_map' );
	}

	/**
	 * Fakes every HTTP request with the same response.
	 *
	 * @param int        $code    HTTP response code.
	 * @param string     $body    Response body.
	 * @param array|null $request Gets the `args`, the `url` and the `count` of the requests.
	 */
	protected function mock_http_response( $code, $body, &$request = null ) {
		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) use ( $code, $body, &$request ) {
				$request = array(
					'args'  => $args,
					'url'   => $url,
					'count' => ( $request['count'] ?? 0 ) + 1,
				);

				return array(
					'headers'  => array(),
					'body'     => $body,
					'response' => array(
						'code'    => $code,
						'message' => '',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);
	}

	/**
	 * Fakes HTTP requests according to a part of their URL.
	 *
	 * @param array $routes URL part => array( code, body ). Other requests get a 404.
	 */
	protected function mock_http_routes( $routes ) {
		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) use ( $routes ) {
				$code = 404;
				$body = wp_json_encode( array( 'message' => 'Not Found' ) );

				foreach ( $routes as $url_part => $response ) {
					if ( false !== strpos( $url, $url_part ) ) {
						list( $code, $body ) = $response;
						break;
					}
				}

				return array(
					'headers'  => array(),
					'body'     => $body,
					'response' => array(
						'code'    => $code,
						'message' => '',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);
	}

	/**
	 * @covers ::retraceur_discovery_request
	 */
	public function test_request_returns_the_decoded_json_body() {
		$this->mock_http_response( 200, wp_json_encode( array( 'full_name' => 'owner/repo' ) ) );

		$this->assertSame(
			array( 'full_name' => 'owner/repo' ),
			retraceur_discovery_request( '/repos/owner/repo' )
		);
	}

	/**
	 * @covers ::retraceur_discovery_request
	 */
	public function test_request_returns_the_raw_body_when_asked() {
		$this->mock_http_response( 200, '<p>Hello</p>' );

		$this->assertSame(
			'<p>Hello</p>',
			retraceur_discovery_request( '/repos/owner/repo/contents/CHANGELOG.md', array( 'raw' => true ) )
		);
	}

	/**
	 * @covers ::retraceur_discovery_request
	 */
	public function test_request_returns_the_provider_error_message() {
		$this->mock_http_response( 403, wp_json_encode( array( 'message' => 'API rate limit exceeded' ) ) );

		$result = retraceur_discovery_request( '/repos/owner/repo' );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'API rate limit exceeded', $result->get_error_message() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	/**
	 * @covers ::retraceur_discovery_request
	 */
	public function test_request_returns_the_provider_error_message_in_raw_mode() {
		$this->mock_http_response( 403, wp_json_encode( array( 'message' => 'API rate limit exceeded' ) ) );

		$result = retraceur_discovery_request( '/repos/owner/repo/contents/CHANGELOG.md', array( 'raw' => true ) );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'API rate limit exceeded', $result->get_error_message() );
	}

	/**
	 * @covers ::retraceur_discovery_request
	 */
	public function test_request_falls_back_to_a_generic_message_when_the_error_is_not_json() {
		$this->mock_http_response( 502, '<html>Bad gateway</html>' );

		$result = retraceur_discovery_request( '/repos/owner/repo' );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'An error occurred. Please try again later.', $result->get_error_message() );
		$this->assertSame( 502, $result->get_error_data()['status'] );
	}

	/**
	 * @covers ::retraceur_discovery_request
	 */
	public function test_request_uses_the_provider_url_filter() {
		$request = null;

		add_filter(
			'retraceur_discovery_provider_url',
			static function () {
				return 'https://git.example.org/api/v1';
			}
		);

		$this->mock_http_response( 200, '{}', $request );

		retraceur_discovery_request( '/repos/owner/repo' );

		$this->assertStringContainsString( 'git.example.org/api/v1/repos/owner/repo', $request['url'] );
	}

	/**
	 * @covers ::retraceur_discovery_request
	 */
	public function test_request_merges_the_custom_headers() {
		$request = null;

		$this->mock_http_response( 200, '{}', $request );

		retraceur_discovery_request(
			'/repos/owner/repo',
			array(
				'headers' => array( 'Accept' => 'application/vnd.github.html+json' ),
			)
		);

		$this->assertSame( 'application/vnd.github.html+json', $request['args']['headers']['Accept'] );
		$this->assertArrayHasKey( 'User-Agent', $request['args']['headers'] );
	}

	/**
	 * @covers ::retraceur_discovery_request
	 */
	public function test_request_sends_the_token() {
		if ( defined( 'RETRACEUR_GHT' ) && RETRACEUR_GHT ) {
			$this->markTestSkipped( 'The RETRACEUR_GHT constant is defined.' );
		}

		$request = null;

		update_option( 'retraceur_github_token', 'secret-token' );
		$this->mock_http_response( 200, '{}', $request );

		retraceur_discovery_request( '/repos/owner/repo' );

		$this->assertSame( 'Bearer secret-token', $request['args']['headers']['Authorization'] );
	}

	public static function data_repository_actions() {
		return array(
			'plugin' => array( 'retraceur-plugin' ),
			'block'  => array( 'retraceur-block' ),
			'theme'  => array( 'retraceur-theme' ),
		);
	}

	/**
	 * @covers ::retraceur_discovery_api
	 *
	 * @param string $action The Discovery API action.
	 */
	#[DataProvider('data_repository_actions')]
	public function test_api_sets_a_default_per_page( $action ) {
		$args = null;

		add_filter(
			'retraceur_discovery_api_args',
			static function ( $filtered_args ) use ( &$args ) {
				$args = $filtered_args;
				return $filtered_args;
			}
		);

		// Short-circuit the request.
		add_filter(
			'retraceur_discovery_api',
			static function () {
				return array( 'items' => array() );
			}
		);

		retraceur_discovery_api( $action );

		$this->assertSame( 10, $args['per_page'] );
	}

	/**
	 * @covers ::retraceur_discovery_api
	 */
	public function test_api_keeps_a_custom_per_page() {
		$args = null;

		add_filter(
			'retraceur_discovery_api_args',
			static function ( $filtered_args ) use ( &$args ) {
				$args = $filtered_args;
				return $filtered_args;
			}
		);

		add_filter(
			'retraceur_discovery_api',
			static function () {
				return array( 'items' => array() );
			}
		);

		retraceur_discovery_api( 'retraceur-plugin', array( 'per_page' => 25 ) );

		$this->assertSame( 25, $args['per_page'] );
	}

	/**
	 * @covers ::retraceur_discovery_api
	 */
	public function test_api_marks_a_short_circuited_result_as_external() {
		add_filter(
			'retraceur_discovery_api',
			static function () {
				return array( 'items' => array() );
			}
		);

		$result = retraceur_discovery_api( 'retraceur-theme' );

		$this->assertTrue( $result['external'] );
	}

	/**
	 * @covers ::retraceur_discovery_api
	 */
	public function test_api_builds_the_search_query() {
		$request = null;

		$this->mock_http_response( 200, wp_json_encode( array( 'items' => array() ) ), $request );

		retraceur_discovery_api(
			'retraceur-theme',
			array(
				'search' => 'twenty',
				'sort'   => 'stars',
				'order'  => 'desc',
			)
		);

		wp_parse_str( wp_parse_url( $request['url'], PHP_URL_QUERY ), $query );

		$this->assertSame( 'topic:retraceur-theme twenty in:name,description', $query['q'] );
		$this->assertSame( 'stars', $query['sort'] );
		$this->assertSame( 'desc', $query['order'] );
	}

	/**
	 * @covers ::retraceur_discovery_api
	 */
	public function test_api_only_uses_the_topic_without_search_terms() {
		$request = null;

		$this->mock_http_response( 200, wp_json_encode( array( 'items' => array() ) ), $request );

		retraceur_discovery_api( 'retraceur-block', array( 'search' => '' ) );

		wp_parse_str( wp_parse_url( $request['url'], PHP_URL_QUERY ), $query );

		$this->assertSame( 'topic:retraceur-block', $query['q'] );
		$this->assertArrayNotHasKey( 'search', $query );
	}

	/**
	 * @covers ::retraceur_discovery_api
	 */
	public function test_api_ignores_an_invalid_sort() {
		$request = null;

		$this->mock_http_response( 200, wp_json_encode( array( 'items' => array() ) ), $request );

		retraceur_discovery_api(
			'retraceur-plugin',
			array(
				'sort'  => 'bogus',
				'order' => 'desc',
			)
		);

		wp_parse_str( wp_parse_url( $request['url'], PHP_URL_QUERY ), $query );

		$this->assertArrayNotHasKey( 'sort', $query );
		$this->assertArrayNotHasKey( 'order', $query );
	}

	/**
	 * @covers ::retraceur_discovery_api
	 */
	public function test_api_caches_the_search_results() {
		$request = null;

		$this->mock_http_response( 200, wp_json_encode( array( 'items' => array() ) ), $request );

		retraceur_discovery_api( 'retraceur-plugin', array( 'search' => 'cache' ) );
		retraceur_discovery_api( 'retraceur-plugin', array( 'search' => 'cache' ) );

		$this->assertSame( 1, $request['count'] );
	}

	/**
	 * @covers ::retraceur_discovery_api
	 */
	public function test_api_does_not_cache_a_failed_search() {
		$request = null;

		$this->mock_http_response( 403, wp_json_encode( array( 'message' => 'API rate limit exceeded' ) ), $request );

		$first  = retraceur_discovery_api( 'retraceur-plugin', array( 'search' => 'failure' ) );
		$second = retraceur_discovery_api( 'retraceur-plugin', array( 'search' => 'failure' ) );

		$this->assertTrue( is_wp_error( $first ) );
		$this->assertTrue( is_wp_error( $second ) );
		$this->assertSame( 2, $request['count'] );
	}

	/**
	 * @covers ::retraceur_discovery_api
	 */
	public function test_api_repository_requires_a_repository() {
		$result = retraceur_discovery_api( 'retraceur-repository' );

		$this->assertTrue( is_wp_error( $result) );
		$this->assertSame( 'retraceur_discovery_missing_repository', $result->get_error_code() );
	}

	/**
	 * @covers ::retraceur_discovery_api
	 */
	public function test_api_repository_returns_the_repository_its_manifest_and_its_release() {
		$this->mock_http_routes(
			array(
				'/contents/retraceur/manifest.json' => array(
					200,
					wp_json_encode( array( 'content' => base64_encode( wp_json_encode( array( 'type' => 'theme' ) ) ) ) ),
				),
				'/releases/latest'                  => array( 200, wp_json_encode( array( 'tag_name' => '1.0.0' ) ) ),
				'/repos/owner/repo'                 => array( 200, wp_json_encode( array( 'full_name' => 'owner/repo' ) ) ),
			)
		);

		$result = retraceur_discovery_api( 'retraceur-repository', array( 'repository' => 'owner/repo' ) );

		$this->assertSame( 'owner/repo', $result['repository']['full_name'] );
		$this->assertSame( 'theme', $result['manifest']['type'] );
		$this->assertSame( '1.0.0', $result['release']['tag_name'] );
	}

	/**
	 * @covers ::retraceur_discovery_api
	 */
	public function test_api_changelog_requires_a_repository() {
		$result = retraceur_discovery_api( 'retraceur-changelog' );

		$this->assertTrue( is_wp_error( $result) );
		$this->assertSame( 'retraceur_discovery_missing_repository', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	/**
	 * @covers ::retraceur_discovery_api
	 */
	public function test_api_changelog_is_an_error_when_the_repository_has_none() {
		$this->mock_http_response( 404, wp_json_encode( array( 'message' => 'Not Found' ) ) );

		$result = retraceur_discovery_api( 'retraceur-changelog', array( 'repository' => 'owner/repo' ) );

		$this->assertTrue( is_wp_error( $result) );
		$this->assertSame( 'retraceur_discovery_empty_changelog', $result->get_error_code() );
		$this->assertSame( 404, $result->get_error_data()['status'] );
	}

	/**
	 * @covers ::retraceur_discovery_api
	 */
	public function test_api_changelog_removes_the_title_and_the_links() {
		$this->mock_http_response(
			200,
			'<h1>Changelog</h1><h2>1.0.0</h2><ul><li>Fix, see <a href="https://example.org">this</a></li></ul>'
		);

		$result = retraceur_discovery_api( 'retraceur-changelog', array( 'repository' => 'owner/repo' ) );

		$this->assertStringNotContainsString( '<h1', $result );
		$this->assertStringContainsString( '<h2>1.0.0</h2>', $result );
		$this->assertStringNotContainsString( '<a ', $result );
		$this->assertStringContainsString( 'this', $result );
	}

	public static function data_full_names() {
		return array(
			'explicit header'          => array(
				array( 'GitHubPluginURI' => 'https://github.com/owner/repo' ),
				'owner/repo',
			),
			'with a path'              => array(
				array( 'GitHubPluginURI' => 'https://github.com/owner/repo/tree/trunk' ),
				'owner/repo',
			),
			'with a trailing slash'    => array(
				array( 'GitHubPluginURI' => 'https://github.com/owner/repo/' ),
				'owner/repo',
			),
			'explicit header first'    => array(
				array(
					'GitHubPluginURI' => 'https://github.com/owner/repo',
					'PluginURI'       => 'https://github.com/other/thing',
				),
				'owner/repo',
			),
			'plugin uri on github'     => array(
				array( 'PluginURI' => 'https://github.com/owner/repo' ),
				'owner/repo',
			),
			'plugin uri somewhere else' => array(
				array( 'PluginURI' => 'https://example.org/my-plugin' ),
				'',
			),
			'owner only'               => array(
				array( 'GitHubPluginURI' => 'https://github.com/owner' ),
				'',
			),
			'no headers'               => array(
				array(),
				'',
			),
		);
	}

	/**
	 * @covers ::retraceur_discovery_resolve_full_name
	 *
	 * @param array  $headers  Plugin headers.
	 * @param string $expected Expected full name.
	 */
	#[DataProvider('data_full_names')]
	public function test_resolve_full_name( $headers, $expected ) {
		$this->assertSame( $expected, retraceur_discovery_resolve_full_name( $headers ) );
	}

	/**
	 * @covers ::retraceur_discovery_get_installed_map
	 */
	public function test_installed_map_is_an_empty_array_when_nothing_resolves() {
		$map = retraceur_discovery_get_installed_map(
			array(
				'hello/hello.php' => array( 'PluginURI' => 'https://example.org/hello' ),
			),
			'plugins'
		);

		$this->assertSame( array(), $map );
	}

	/**
	 * @covers ::retraceur_discovery_get_installed_map
	 */
	public function test_installed_map_keys_are_lowercase_full_names() {
		$map = retraceur_discovery_get_installed_map(
			array(
				'my-plugin/my-plugin.php' => array( 'GitHubPluginURI' => 'https://github.com/Owner/My-Plugin' ),
			),
			'plugins'
		);

		$this->assertSame( array( 'owner/my-plugin' => 'my-plugin/my-plugin.php' ), $map );
	}

	/**
	 * @covers ::retraceur_discovery_get_installed_map
	 */
	public function test_installed_map_is_cached() {
		$items = array(
			'one/one.php' => array( 'GitHubPluginURI' => 'https://github.com/owner/one' ),
		);

		retraceur_discovery_get_installed_map( $items, 'plugins' );

		$second = retraceur_discovery_get_installed_map(
			array(
				'two/two.php' => array( 'GitHubPluginURI' => 'https://github.com/owner/two' ),
			),
			'plugins'
		);

		$this->assertSame( array( 'owner/one' => 'one/one.php' ), $second );
	}

	/**
	 * @covers ::retraceur_discovery_get_installed_map
	 */
	public function test_installed_map_caches_an_empty_map() {
		retraceur_discovery_get_installed_map( array(), 'plugins' );

		$second = retraceur_discovery_get_installed_map(
			array(
				'one/one.php' => array( 'GitHubPluginURI' => 'https://github.com/owner/one' ),
			),
			'plugins'
		);

		$this->assertSame( array(), $second );
	}

	/**
	 * @covers ::retraceur_discovery_get_installed_map
	 */
	public function test_installed_maps_of_different_item_types_do_not_overwrite_each_other() {
		$plugins = array(
			'one/one.php' => array( 'GitHubPluginURI' => 'https://github.com/owner/one' ),
		);
		$themes  = array(
			'my-theme' => array( 'GitHubPluginURI' => 'https://github.com/owner/my-theme' ),
		);

		retraceur_discovery_get_installed_map( $plugins, 'plugins' );
		retraceur_discovery_get_installed_map( $themes, 'themes' );

		// Both maps come from the cache: the items are not used anymore.
		$this->assertSame(
			array( 'owner/one' => 'one/one.php' ),
			retraceur_discovery_get_installed_map( array(), 'plugins' )
		);
		$this->assertSame(
			array( 'owner/my-theme' => 'my-theme' ),
			retraceur_discovery_get_installed_map( array(), 'themes' )
		);
	}

	/**
	 * @covers ::retraceur_discovery_flush_installed_map
	 */
	public function test_flush_installed_map_deletes_the_cached_maps() {
		retraceur_discovery_get_installed_map(
			array(
				'one/one.php' => array( 'GitHubPluginURI' => 'https://github.com/owner/one' ),
			),
			'plugins'
		);

		retraceur_discovery_flush_installed_map();

		$map = retraceur_discovery_get_installed_map(
			array(
				'two/two.php' => array( 'GitHubPluginURI' => 'https://github.com/owner/two' ),
			),
			'plugins'
		);

		$this->assertSame( array( 'owner/two' => 'two/two.php' ), $map );
	}

	/**
	 * @covers ::retraceur_discovery_flush_installed_map
	 */
	public function test_flush_installed_map_is_hooked_to_the_upgrader() {
		$this->assertNotFalse( has_action( 'upgrader_process_complete', 'retraceur_discovery_flush_installed_map' ) );
	}
}
