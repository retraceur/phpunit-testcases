# phpunit-testcases

PHPUnit tests for the `trunk` branch of [retraceur/coeur](https://github.com/retraceur/coeur).

- PHPUnit 12 as a phar (PHP 8.3+): no Composer, no Yoast polyfills.
- This repository is cloned by `coeur` into `phpunit-testcases/` (git-ignored there) by `bin/phpunit.mjs`, which also downloads the phar into `.phpunit/`.

## Layout

- `bootstrap.php`, `install.php`: load the config and the core; install Retraceur in the tests database.
- `src/TestCase.php`: `Retraceur\Tests\TestCase`, the base class (transaction rollback, hooks/cache/user reset).
- `tests/`: the tests, in files named `*.php`.
- `phpunit-phar.json`: PHPUnit version and pinned sha256.
- `tests-config.php`: your local config (copy of `tests-config-sample.php`, git-ignored).

## Writing a test

```php
use Retraceur\Tests\TestCase;

final class retraceurOptionsTest extends TestCase {
	public function test_something(): void {
		$this->assertSame( 'x', get_option( 'x', 'x' ) );
	}
}
```

## Running the tests

```bash
npm run phpunit
```
