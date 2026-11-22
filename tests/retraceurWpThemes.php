<?php
use PHPUnit\Framework\Attributes\CoversMethod;
use Retraceur\Tests\TestCase;

#[CoversMethod(WP_Theme::class,'get')]
final class retraceurWpThemes extends TestCase {

	public function test_github_theme_uri_header() {
		$theme = new WP_Theme( 'github-theme', DIR_TESTDATA . '/discovery-themes' );

		$this->assertSame( 'https://github.com/owner/github-theme', $theme->get( 'GitHubThemeURI' ) );
	}

	public function test_github_theme_uri_header_is_empty_when_missing() {
		$theme = new WP_Theme( 'plain-theme', DIR_TESTDATA . '/discovery-themes' );

		$this->assertEmpty( $theme->get( 'GitHubThemeURI' ) );
	}

	public function test_github_theme_uri_header_is_sanitized() {
		$theme = new WP_Theme( 'unsafe-theme', DIR_TESTDATA . '/discovery-themes' );

		$this->assertEmpty( $theme->get( 'GitHubThemeURI' ) );
	}
}
