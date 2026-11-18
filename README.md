# php-http-message

PSR-7 HTTP message implementation for PHP >= 8.0 (`psr/http-message` ^2.0).

## Installation

```bash
composer require hemiframe/php-http-message
```

## Classes

All classes are in the `HemiFrame\Lib\Http\Message` namespace: `Message`, `Request`, `ServerRequest`, `Response`, `Uri`, `Stream` and `UploadedFile`.

## Usage

```php
use HemiFrame\Lib\Http\Message\Response;
use HemiFrame\Lib\Http\Message\ServerRequest;

$request = (new ServerRequest())->fromGlobals();
$name = $request->getQueryParam('name', 'world');

$response = (new Response())
    ->withStatus(200)
    ->withHeader('Content-Type', 'text/plain');
$response->getBody()->write('Hello '.$name);
$response->send();
```

`ServerRequest::fromGlobals()` fills the request from `$_SERVER`, `$_GET`, `$_COOKIE`, `$_POST` and `$_FILES`. For JSON, `multipart/form-data` and `application/x-www-form-urlencoded` bodies of non-POST requests the body is parsed from the request input. See [demo/index.php](demo/index.php).

## Immutability

Objects are immutable by default: every `with*()` method returns a modified copy. Call `setImmutable(false)` to modify the same instance instead:

```php
$response = (new Response())->setImmutable(false);
$response->withHeader('X-Test', '1'); // $response is changed
```

## Development

```bash
composer install
composer run-script build   # code style check + phpstan + phpunit
```

## Client IP

`ServerRequest::getClientIP()` returns `REMOTE_ADDR` by default. Forwarding headers such as `X-Forwarded-For` can be spoofed by the client, so they are ignored unless you list the ones you trust, in priority order:

```php
$request->setClientIpHeaders(['X-Forwarded-For']);
$ip = $request->getClientIP();
```

Supported formats: plain lists (`198.51.100.7, 10.0.0.1`), addresses with a port (`198.51.100.7:8080`, `[2001:db8::1]:443`) and the RFC 7239 `Forwarded` header (`for=198.51.100.7;proto=https`). The first valid public IP is used; if none is found, the first valid IP is used, then `REMOTE_ADDR`. Passing `[]` restores the default.
