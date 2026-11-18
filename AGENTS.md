# AGENTS.md

PSR-7 HTTP message implementation (`hemiframe/php-http-message`) for PHP >= 8.0, depending on `psr/http-message` ^2.0.

## Layout

- `src/HemiFrame/Lib/Http/Message/` – `Message`, `Request`, `ServerRequest`, `Response`, `Uri`, `Stream`, `UploadedFile`.
- `demo/index.php` – manual usage example.
- `tests/` – PHPUnit tests (namespace `HemiFrame\Tests`), configured in `phpunit.xml.dist`.
- Autoload: PSR-4, namespace `HemiFrame\` -> `src/HemiFrame`.

## Commands

- `composer run-script php-cs-fixer` – code style check (dry run).
- `composer run-script php-cs-fixer-apply` – apply code style fixes.
- `composer run-script phpstan` – static analysis (level 5, covers `src`, `demo` and `tests`).
- `composer run-script test` – PHPUnit.
- `composer run-script build` – style check + phpstan + tests.

## Conventions

- Code style: `@Symfony` rules via `.php-cs-fixer.dist.php`.
- Code must remain compatible with PHP 8.0 and PSR-7 v2 signatures (typed parameters/returns).
- Classes support toggling immutability via `setImmutable()`; preserve this behavior when changing `with*` methods.
- Do not edit `vendor/`.
- `ServerRequest::getClientIP()` uses only `REMOTE_ADDR` unless trusted headers are set via `setClientIpHeaders()`; do not add default proxy headers.
- Dev tools (PHPUnit, php-cs-fixer) may run on a newer PHP than 8.0; the php-cs-fixer "newer PHP version" notice is informational. Do not use syntax newer than PHP 8.0 in `src/`.
- Methods returning a new/same instance are declared `: self` with `@return static` in the docblock; native `: static` triggers Intelephense P1112 against the PSR interfaces.
- Add or update tests in `tests/` when changing behavior, and run `composer run-script build`.
- Tests must run on PHPUnit 9.6–13 (PHP 8.0 needs 9.6): extend `HemiFrame\Tests\TestCase`, do not use PHPUnit attributes or annotations (`DataProvider`, `TestWith`, `BackupGlobals`); loop over cases and use `assertInvalidArgument()` instead.
