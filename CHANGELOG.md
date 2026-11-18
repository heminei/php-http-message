# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project follows [Semantic Versioning](https://semver.org/).

## [3.0.0] - 2026-11-18

### Breaking changes

- `ServerRequest::getClientIP()` uses only `REMOTE_ADDR` by default. Proxy headers are trusted only when listed via `setClientIpHeaders()`.
- `Request` keeps the HTTP method as given (`withMethod('post')` returns `post`); it is no longer uppercased.
- `Uri::getPort()` returns `null` when the port is the default one for the scheme (80 for `http`, 443 for `https`).
- `Uri` lowercases scheme and host and percent-encodes path, query, fragment and user info.
- `UploadedFile`:
  - `moveTo()` moves files given as a path (`move_uploaded_file()`, or `rename()` in CLI) instead of copying them.
  - `moveTo()` can be called only once; `getStream()` throws after a move or when the upload has an error.
  - `getClientFilename()` and `getClientMediaType()` return `null` when not provided, instead of guessing from the file.
- `ServerRequest::getUploadedFiles()` includes files that failed to upload (e.g. `UPLOAD_ERR_INI_SIZE`); check `getError()` before calling `moveTo()`. Empty fields (`UPLOAD_ERR_NO_FILE`) are still skipped.
- `ServerRequest::getParsedBody()` returns `null` instead of `[]` when there is no body.
- Invalid input now throws `InvalidArgumentException`:
  - header names and values (including CR/LF), empty header arrays and integer header keys (`['Content-Type: x']`);
  - HTTP method, protocol version, status code (outside 100–599) and reason phrase;
  - URI that cannot be parsed, invalid scheme, invalid host and port outside 0–65535;
  - parsed body that is not an array, object or `null`, and an invalid uploaded files structure;
  - invalid upload error status in `UploadedFile`.
- Native parameter and return types were added to match PSR-7 v2; passing a wrong type throws `TypeError`.
- `Response::send()` returns `void` and throws `RuntimeException` when headers were already sent.

### Added

- `ServerRequest::setClientIpHeaders()` and `getClientIpHeaders()`.
- `getClientIP()` understands the RFC 7239 `Forwarded` header, comma-separated lists and addresses with a port (`192.0.2.1:80`, `[2001:db8::1]:443`).
- `ServerRequest::fromGlobals()` parses `application/x-www-form-urlencoded` bodies for non-POST requests.
- `Request` sets the `Host` header from the URI in the constructor when it is not given.
- `Response` uses the standard reason phrase when none is given, also in the constructor.
- Reason phrases for 103, 226, 308, 421 and 510; 425 is now `Too Early`.
- PHPUnit test suite (`composer run-script test`), included in `composer run-script build`.

### Fixed

- `withHeader()` and `withoutHeader()` did not remove existing headers; `withAddedHeader()` ignored headers that were not set yet.
- `Request::withUri()` did not update the `Host` header.
- `Response::send()` joined repeated headers into one line, which broke multiple `Set-Cookie` headers.
- Multipart parsing for non-POST requests:
  - cut values containing the boundary text and corrupted file contents containing `Content-` lines;
  - lost the last character of field names such as `tab[b]` and did not support nested names (`a[b][c]`, `a[b][]`);
  - did not rewind uploaded file streams and did not handle quoted boundaries or bare LF line endings;
  - now applies the `max_input_vars`, `max_file_uploads` and `upload_max_filesize` limits;
  - normalizes field names the same way as `$_POST` (`a.b c` becomes `a_b_c`, names starting with `[` are ignored);
  - reads RFC 5987 file names (`filename*=UTF-8''...`).
- `fromGlobals()`:
  - reads the request body once and leaves `getBody()` at the start;
  - skips malformed client headers and multipart bodies instead of failing;
  - handles IPv6 hosts, absolute-form request targets and `SERVER_PORT` when there is no `Host` header.
- `Stream`:
  - detects readable and writable modes such as `rb+`, `wb+`, `ab` and `a+b`;
  - `getContents()` throws for write-only streams and `__toString()` no longer throws;
  - no warning for streams without a URI (e.g. pipes).
- `Message` accepts any `StreamInterface` as body, not only `Stream`.
- A string body passed to the constructor is rewound, so `getBody()->getContents()` returns it.
- `getClientIP()` takes the rightmost public address from trusted headers, since leftmost entries can be spoofed by the client.
- `withHeader()` keeps the position of a replaced header.
- `ServerRequest::getParsedBodyParam()` supports `ArrayAccess` bodies such as `ArrayObject`.
- `Uri` prefixes a relative path with `/` when there is an authority and no longer warns for unknown schemes with a port.
- `Uri::getAuthority()` is empty when there is no host; internationalized (non-ASCII) host names are accepted.

## [2.1.0]

- `UploadedFile` implements `Stringable`.

[3.0.0]: https://github.com/heminei/php-http-message/compare/2.1.0...3.0.0
[2.1.0]: https://github.com/heminei/php-http-message/compare/2.0.1...2.1.0
