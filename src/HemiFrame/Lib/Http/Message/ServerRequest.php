<?php

namespace HemiFrame\Lib\Http\Message;

class ServerRequest extends Request implements \Psr\Http\Message\ServerRequestInterface
{
    private array $attributes = [];

    private array $cookieParams = [];

    private array $serverParams = [];

    private array $queryParams = [];

    private array $uploadedFiles = [];

    private array|object|null $parsedBody = null;

    /**
     * Headers trusted for resolving the client IP, in priority order.
     *
     * @var string[]
     */
    private array $clientIpHeaders = [];

    public function getAttribute(string $name, $default = null)
    {
        if (false === array_key_exists($name, $this->attributes)) {
            return $default;
        }

        return $this->attributes[$name];
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    public function getCookieParam(string $name, $default = null)
    {
        if (false === array_key_exists($name, $this->cookieParams)) {
            return $default;
        }

        return $this->cookieParams[$name];
    }

    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    public function getParsedBodyParam(string $name, $default = null)
    {
        if ($this->parsedBody instanceof \ArrayAccess) {
            return $this->parsedBody->offsetExists($name) ? $this->parsedBody->offsetGet($name) : $default;
        }

        $body = is_object($this->parsedBody) ? get_object_vars($this->parsedBody) : $this->parsedBody;
        if (!is_array($body) || !array_key_exists($name, $body)) {
            return $default;
        }

        return $body[$name];
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function getQueryParam(string $name, $default = null)
    {
        if (false === array_key_exists($name, $this->queryParams)) {
            return $default;
        }

        return $this->queryParams[$name];
    }

    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    public function getServerParam(string $name, $default = null)
    {
        if (false === array_key_exists($name, $this->serverParams)) {
            return $default;
        }

        return $this->serverParams[$name];
    }

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * @return UploadedFile|mixed
     */
    public function getUploadedFile(string $name, $default = null)
    {
        if (false === array_key_exists($name, $this->uploadedFiles)) {
            return $default;
        }

        return $this->uploadedFiles[$name];
    }

    public function withAttribute(string $name, $value): self
    {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->attributes[$name] = $value;

        return $new;
    }

    public function withCookieParams(array $cookies): self
    {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->cookieParams = $cookies;

        return $new;
    }

    /**
     * @param mixed $data array, object or null; anything else is rejected as PSR-7 requires
     */
    public function withParsedBody($data): self
    {
        if (null !== $data && !is_array($data) && !is_object($data)) {
            throw new \InvalidArgumentException('Parsed body must be an array, an object or null');
        }

        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->parsedBody = $data;

        return $new;
    }

    public function withQueryParams(array $query): self
    {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->queryParams = $query;

        return $new;
    }

    public function withUploadedFiles(array $uploadedFiles): self
    {
        self::assertUploadedFiles($uploadedFiles);

        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->uploadedFiles = $uploadedFiles;

        return $new;
    }

    public function withoutAttribute(string $name): self
    {
        if (false === array_key_exists($name, $this->attributes)) {
            return $this;
        }

        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        unset($new->attributes[$name]);

        return $new;
    }

    /**
     * @return string[]
     */
    public function getClientIpHeaders(): array
    {
        return $this->clientIpHeaders;
    }

    /**
     * Sets the headers trusted for resolving the client IP; an empty list means only REMOTE_ADDR is used.
     *
     * @param string[] $headers
     */
    public function setClientIpHeaders(array $headers): self
    {
        $this->clientIpHeaders = array_values($headers);

        return $this;
    }

    public function getClientIP(): ?string
    {
        foreach ($this->clientIpHeaders as $key) {
            if (!$this->hasHeader($key)) {
                continue;
            }

            $isForwarded = 0 === strcasecmp($key, 'Forwarded');
            $ips = [];
            foreach ($this->getHeader($key) as $line) {
                foreach (explode(',', $line) as $entry) {
                    $ip = self::extractIp($entry, $isForwarded);
                    if (null !== $ip) {
                        $ips[] = $ip;
                    }
                }
            }

            // Proxies append to the right, so leftmost entries are client-controlled and can be spoofed.
            foreach (array_reverse($ips) as $ip) {
                if (false !== filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
            if ([] !== $ips) {
                return $ips[count($ips) - 1];
            }
        }

        return $this->getServerParam('REMOTE_ADDR');
    }

    /**
     * Extracts the IP from a header entry such as "192.0.2.1", "192.0.2.1:80", "[2001:db8::1]:443"
     * or, for the RFC 7239 Forwarded header, 'for="[2001:db8::1]";proto=https'.
     */
    private static function extractIp(string $entry, bool $isForwarded): ?string
    {
        $entry = trim($entry);
        if ($isForwarded) {
            if (1 !== preg_match('/(?:^|;)\s*for\s*=\s*("[^"]*"|[^;\s]*)/i', $entry, $forMatch)) {
                return null;
            }
            $entry = trim($forMatch[1], '"');
        }

        if (1 === preg_match('/^\[(?<ip>[^\]]+)\](?::\d+)?$/D', $entry, $match)
            || 1 === preg_match('/^(?<ip>\d{1,3}(?:\.\d{1,3}){3}):\d+$/D', $entry, $match)) {
            $entry = $match['ip'];
        }

        return false !== filter_var($entry, FILTER_VALIDATE_IP) ? $entry : null;
    }

    public function fromGlobals(): self
    {
        $immutable = $this->getImmutable();
        $new = $immutable ? clone $this : $this;
        // Avoid cloning on every with*() call while building the request.
        $new->setImmutable(false);

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (str_starts_with($name, 'HTTP_')) {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
            if (isset($_SERVER['CONTENT_TYPE'])) {
                $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
            }
        }

        foreach ($headers as $name => $value) {
            try {
                $new = $new->withHeader((string) $name, [$value]);
            } catch (\InvalidArgumentException) {
                // Skip malformed client headers instead of failing the whole request.
            }
        }

        $uri = new Uri();
        $uri = $uri->fromGlobals();

        $body = new Stream(fopen('php://input', 'r'));
        $protocolVersion = 1 === preg_match('#^HTTP/(\d+(?:\.\d+)?)$#D', $_SERVER['SERVER_PROTOCOL'] ?? '', $protocolMatch) ? $protocolMatch[1] : '1.1';

        $new->serverParams = $_SERVER;

        $new = $new->withMethod($method);
        $new = $new->withUri($uri);
        $new = $new->withBody($body);
        $new = $new->withProtocolVersion($protocolVersion);
        $new = $new->withCookieParams($_COOKIE);
        $new = $new->withQueryParams($_GET);

        $contentType = strtolower($new->getHeaderLine('Content-Type'));
        $isPost = 'POST' === $new->getMethod();
        $isJson = str_contains($contentType, 'application/json');
        $isMultipart = !$isJson && !$isPost && str_contains($contentType, 'multipart/form-data');
        $isUrlEncoded = !$isJson && !$isPost && str_contains($contentType, 'application/x-www-form-urlencoded');

        // $_POST and $_FILES already cover regular POST requests, so the body is read only when needed.
        $raw = '';
        if ($isJson || $isMultipart || $isUrlEncoded) {
            $raw = $body->getContents();
            $body->rewind();
        }

        if ($isJson) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $new = $new->withParsedBody($json);
            }
        } elseif ($isMultipart) {
            try {
                $multipartFormData = $new->parseMultipartFormData($raw, $new->getHeaderLine('Content-Type'));
                $new = $new->withParsedBody($multipartFormData['params']);
                $new = $new->withUploadedFiles($multipartFormData['files']);
            } catch (\InvalidArgumentException) {
                // Malformed multipart body (e.g. missing boundary): leave the parsed body empty.
            }
        } elseif ($isUrlEncoded) {
            parse_str($raw, $parsedBody);
            $new = $new->withParsedBody($parsedBody);
        } else {
            $new = $new->withParsedBody($_POST);

            $uploadedFiles = [];
            foreach ($_FILES as $name => $file) {
                $uploadedFile = $this->createUploadedFiles($file['tmp_name'], $file['name'], $file['type'], $file['error']);
                if (null !== $uploadedFile) {
                    $uploadedFiles[$name] = $uploadedFile;
                }
            }
            $new = $new->withUploadedFiles($uploadedFiles);
        }

        $new->setImmutable($immutable);

        return $new;
    }

    /**
     * @return UploadedFile|array|null null when no file was selected for the field
     */
    private function createUploadedFiles($tmpName, $name, $type, $error)
    {
        if (is_array($tmpName)) {
            $files = [];
            foreach (array_keys($tmpName) as $key) {
                $file = $this->createUploadedFiles($tmpName[$key], $name[$key], $type[$key], $error[$key]);
                if (null !== $file) {
                    $files[$key] = $file;
                }
            }

            return $files;
        }

        $error = (int) $error;
        if (UPLOAD_ERR_NO_FILE === $error) {
            return null;
        }

        // Other errors are kept so callers can report them via getError().
        return new UploadedFile($tmpName, '' === $name ? null : $name, '' === $type ? null : $type, $error);
    }

    private static function assertUploadedFiles(array $files): void
    {
        foreach ($files as $file) {
            if (is_array($file)) {
                self::assertUploadedFiles($file);
            } elseif (!$file instanceof \Psr\Http\Message\UploadedFileInterface) {
                throw new \InvalidArgumentException('Invalid uploaded files structure; every leaf must implement UploadedFileInterface');
            }
        }
    }

    /**
     * Assigns $value using the same name rules as $_POST, e.g. "a[b][]" becomes $target['a']['b'][]
     * and "a.b c" becomes $target['a_b_c'].
     */
    private static function setFormValue(array &$target, string $name, $value): void
    {
        $name = ltrim($name, ' ');
        $bracket = strpos($name, '[');
        $base = strtr(false === $bracket ? $name : substr($name, 0, $bracket), '. ', '__');
        $keys = [];
        if (false !== $bracket) {
            preg_match_all('/\G\[([^\]]*)\]/', $name, $keyMatches, PREG_PATTERN_ORDER, $bracket);
            if ([] === $keyMatches[1]) {
                // An unmatched "[" becomes "_" and the rest of the name is kept as is.
                $base .= '_'.substr($name, $bracket + 1);
            } else {
                $keys = $keyMatches[1];
            }
        }
        if ('' === $base) {
            return;
        }

        $current = &$target;
        foreach ([$base, ...$keys] as $key) {
            if (!is_array($current)) {
                $current = [];
            }
            if ('' === $key) {
                $current[] = null;
                $key = array_key_last($current);
            }
            $current = &$current[$key];
        }
        $current = $value;
    }

    private function parseMultipartFormData(string $string, string $contentType): array
    {
        if (1 !== preg_match('/boundary=("[^"]+"|[^;\s]+)/i', $contentType, $boundaryMatch)) {
            throw new \InvalidArgumentException("Can't find boundary in string");
        }
        $boundary = trim($boundaryMatch[1], '"');

        $result = ['files' => [], 'params' => []];
        $maxParams = (int) ini_get('max_input_vars');
        $maxFiles = (int) ini_get('max_file_uploads');
        $maxFileSize = self::iniBytes((string) ini_get('upload_max_filesize'));
        $paramCount = 0;
        $fileCount = 0;

        // RFC 2046 requires CRLF, but some clients send bare LF.
        $eol = str_contains($string, '--'.$boundary."\r\n") ? "\r\n" : "\n";

        // The delimiter is EOL + "--" + boundary; the leading EOL lets the first delimiter match too.
        $blocks = explode($eol.'--'.$boundary, $eol.$string);
        array_shift($blocks);

        foreach ($blocks as $block) {
            if (str_starts_with($block, '--')) {
                break;
            }
            // Skip optional transport padding and the EOL that ends the delimiter line.
            $lineEnd = strpos($block, $eol);
            if (false === $lineEnd) {
                continue;
            }
            $parts = explode($eol.$eol, substr($block, $lineEnd + strlen($eol)), 2);
            if (2 !== count($parts)) {
                continue;
            }
            [$rawHeaders, $value] = $parts;

            if (1 !== preg_match('/^Content-Disposition:[^\r\n]*?\bname="(?<name>[^"]*)"/im', $rawHeaders, $disposition)) {
                continue;
            }
            $name = $disposition['name'];

            $filename = null;
            // RFC 5987 extended value (filename*=UTF-8''%D1%84.txt) takes precedence over filename="...".
            if (1 === preg_match("/^Content-Disposition:[^\\r\\n]*?\\bfilename\\*\\s*=\\s*[^']*'[^']*'(?<filename>[^;\\r\\n]*)/im", $rawHeaders, $filenameMatch)) {
                $filename = rawurldecode(trim($filenameMatch['filename']));
            } elseif (1 === preg_match('/^Content-Disposition:[^\r\n]*?\bfilename="(?<filename>[^"]*)"/im', $rawHeaders, $filenameMatch)) {
                $filename = $filenameMatch['filename'];
            }

            if (null !== $filename) {
                if ('' === $filename && '' === $value) {
                    continue;
                }
                // Same limits PHP applies to $_FILES.
                if ($maxFiles > 0 && $fileCount >= $maxFiles) {
                    continue;
                }
                ++$fileCount;

                $mediaType = 1 === preg_match('/^Content-Type:\s*(?<value>[^\r\n;]+)/im', $rawHeaders, $typeMatch) ? trim($typeMatch['value']) : null;

                if ($maxFileSize > 0 && strlen($value) > $maxFileSize) {
                    $uploadedFile = new UploadedFile('', $filename, $mediaType, UPLOAD_ERR_INI_SIZE);
                } else {
                    $file = tmpfile();
                    fwrite($file, $value);
                    rewind($file);
                    $uploadedFile = new UploadedFile($file, $filename, $mediaType);
                }

                self::setFormValue($result['files'], $name, $uploadedFile);
            } else {
                // Same limit PHP applies to $_POST.
                if ($maxParams > 0 && $paramCount >= $maxParams) {
                    continue;
                }
                ++$paramCount;

                self::setFormValue($result['params'], $name, $value);
            }
        }

        return $result;
    }

    /**
     * Converts php.ini shorthand such as "2M" to bytes.
     */
    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        $multiplier = ['k' => 1024, 'm' => 1048576, 'g' => 1073741824][strtolower(substr($value, -1))] ?? 1;

        return (int) $value * $multiplier;
    }
}
