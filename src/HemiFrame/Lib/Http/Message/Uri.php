<?php

namespace HemiFrame\Lib\Http\Message;

/**
 * @author heminei <heminei@heminei.com>
 */
class Uri implements \Psr\Http\Message\UriInterface
{
    private string $scheme = '';

    private string $userInfo = '';

    private string $host = '';

    private ?int $port = null;

    private string $path = '';

    private string $query = '';

    private string $fragment = '';

    private bool $immutable = true;

    private static array $defaultPorts = [
        'http' => 80,
        'https' => 443,
        'ftp' => 21,
        'gopher' => 70,
        'nntp' => 119,
        'news' => 119,
        'telnet' => 23,
        'tn3270' => 23,
        'imap' => 143,
        'pop' => 110,
        'ldap' => 389,
    ];

    private const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';

    private const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';

    public function __construct(string $uri = '')
    {
        if ('' !== $uri) {
            $parts = parse_url($uri);
            if (false === $parts) {
                throw new \InvalidArgumentException('Unable to parse URI: '.$uri);
            }

            if (isset($parts['scheme'])) {
                $this->scheme = self::toLower($parts['scheme']);
            }
            if (isset($parts['host'])) {
                $this->host = self::filterHost($parts['host']);
            }
            if (isset($parts['port'])) {
                $this->port = self::filterPort($parts['port']);
            }
            if (isset($parts['path'])) {
                $this->path = self::filterPath($parts['path']);
            }
            if (isset($parts['query'])) {
                $this->query = self::filterQueryOrFragment($parts['query']);
            }
            if (isset($parts['fragment'])) {
                $this->fragment = self::filterQueryOrFragment($parts['fragment']);
            }
            if (isset($parts['user'])) {
                $this->userInfo = self::filterUserInfo($parts['user']);
            }
            if (isset($parts['pass'])) {
                $this->userInfo .= ':'.self::filterUserInfo($parts['pass']);
            }
        }
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toString(): string
    {
        $uri = '';
        if ('' !== $this->scheme) {
            $uri .= $this->scheme.':';
        }
        $authority = $this->getAuthority();
        $path = $this->path;
        if ('' !== $authority || 'file' === $this->scheme) {
            $uri .= '//'.$authority;
            if ('' !== $path && '/' !== $path[0]) {
                $path = '/'.$path;
            }
        } elseif (str_starts_with($path, '//')) {
            // Without an authority a leading "//" would be read as one.
            $path = '/'.ltrim($path, '/');
        }
        $uri .= $path;
        if ('' !== $this->query) {
            $uri .= '?'.$this->query;
        }
        if ('' != $this->fragment) {
            $uri .= '#'.$this->fragment;
        }

        return $uri;
    }

    public function getAuthority(): string
    {
        if ('' === $this->host) {
            return '';
        }

        $authority = $this->host;
        if ('' !== $this->userInfo) {
            $authority = $this->userInfo.'@'.$authority;
        }
        $port = $this->getPort();
        if (null !== $port) {
            $authority .= ':'.$port;
        }

        return $authority;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getPort(): ?int
    {
        if (null !== $this->port && (self::$defaultPorts[$this->scheme] ?? null) === $this->port) {
            return null;
        }

        return $this->port;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    public function getImmutable(): bool
    {
        return $this->immutable;
    }

    public function withFragment(string $fragment): self
    {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->fragment = self::filterQueryOrFragment($fragment);

        return $new;
    }

    public function withHost(string $host): self
    {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->host = self::filterHost($host);

        return $new;
    }

    public function withPath(string $path): self
    {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->path = self::filterPath($path);

        return $new;
    }

    public function withPort(?int $port): self
    {
        $port = self::filterPort($port);

        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->port = $port;

        return $new;
    }

    public function withQuery(string $query): self
    {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->query = self::filterQueryOrFragment($query);

        return $new;
    }

    public function buildQuery(array $array): self
    {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->query = http_build_query($array);

        return $new;
    }

    public function withScheme(string $scheme): self
    {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->scheme = self::filterScheme($scheme);

        return $new;
    }

    public function withUserInfo(string $user, ?string $password = null): self
    {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $userInfo = self::filterUserInfo($user);
        if (null !== $password && '' !== $password) {
            $userInfo .= ':'.self::filterUserInfo($password);
        }
        $new->userInfo = $userInfo;

        return $new;
    }

    /**
     * @return static
     */
    public function setImmutable(bool $immutable): self
    {
        $this->immutable = $immutable;

        return $this;
    }

    /**
     * Get a Uri populated with values from $_SERVER.
     */
    public function fromGlobals(): self
    {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new = $new->withScheme(!empty($_SERVER['HTTPS']) && 'off' !== $_SERVER['HTTPS'] ? 'https' : 'http');
        // Host with optional port; IPv6 addresses are enclosed in brackets.
        if (isset($_SERVER['HTTP_HOST']) && 1 === preg_match('/^(?<host>\[[0-9a-fA-F:.]+\]|[a-zA-Z0-9\-._~!$&\'()*+,;=%\x80-\xFF]+)(?::(?<port>\d{1,5}))?$/D', $_SERVER['HTTP_HOST'], $hostMatch)) {
            $new = $new->withHost($hostMatch['host']);
            if (isset($hostMatch['port']) && (int) $hostMatch['port'] <= 65535) {
                $new = $new->withPort((int) $hostMatch['port']);
            }
        } elseif (isset($_SERVER['SERVER_NAME']) || isset($_SERVER['SERVER_ADDR'])) {
            $host = (string) ($_SERVER['SERVER_NAME'] ?? $_SERVER['SERVER_ADDR']);
            if (false !== filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $host = '['.$host.']';
            }
            $new = $new->withHost($host);
            // Without a Host header the port the client used is unknown, so the server port is the best guess.
            $serverPort = (int) ($_SERVER['SERVER_PORT'] ?? 0);
            if ($serverPort > 0 && $serverPort <= 65535) {
                $new = $new->withPort($serverPort);
            }
        }
        $hasQuery = false;
        if (isset($_SERVER['REQUEST_URI'])) {
            // Absolute-form request targets (sent to proxies) include scheme and authority.
            $requestUri = preg_replace('#^[a-zA-Z][a-zA-Z0-9+.\-]*://[^/?\#]*#', '', $_SERVER['REQUEST_URI']) ?? $_SERVER['REQUEST_URI'];
            $requestUriParts = explode('?', $requestUri, 2);
            $new = $new->withPath($requestUriParts[0]);
            if (isset($requestUriParts[1])) {
                $hasQuery = true;
                $new = $new->withQuery($requestUriParts[1]);
            }
        }
        if (!$hasQuery && isset($_SERVER['QUERY_STRING'])) {
            $new = $new->withQuery($_SERVER['QUERY_STRING']);
        }

        return $new;
    }

    private static function toLower(string $value): string
    {
        // ASCII only, independent of the current locale.
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    private static function filterScheme(string $scheme): string
    {
        // RFC 3986: ALPHA *( ALPHA / DIGIT / "+" / "-" / "." )
        if ('' !== $scheme && 1 !== preg_match('/^[a-zA-Z][a-zA-Z0-9+\-.]*$/D', $scheme)) {
            throw new \InvalidArgumentException('Invalid scheme: '.json_encode($scheme));
        }

        return self::toLower($scheme);
    }

    private static function filterHost(string $host): string
    {
        // reg-name (RFC 3986, plus non-ASCII bytes for internationalized names) or bracketed IP literal
        if (1 !== preg_match('/^(?:\[[0-9a-fA-F:.]+(?:%25[a-zA-Z0-9\-._~%]+)?\]|[a-zA-Z0-9\-._~!$&\'()*+,;=%\x80-\xFF]*)$/D', $host)) {
            throw new \InvalidArgumentException('Invalid host: '.json_encode($host));
        }

        return self::toLower($host);
    }

    private static function filterPort(?int $port): ?int
    {
        if (null !== $port && ($port < 0 || $port > 65535)) {
            throw new \InvalidArgumentException('Invalid port: '.$port.'. Must be between 0 and 65535');
        }

        return $port;
    }

    private static function filterPath(string $path): string
    {
        return self::encode($path, '/(?:[^'.self::CHAR_UNRESERVED.self::CHAR_SUB_DELIMS.'%:@\/]++|%(?![A-Fa-f0-9]{2}))/');
    }

    private static function filterQueryOrFragment(string $value): string
    {
        return self::encode($value, '/(?:[^'.self::CHAR_UNRESERVED.self::CHAR_SUB_DELIMS.'%:@\/\?]++|%(?![A-Fa-f0-9]{2}))/');
    }

    private static function filterUserInfo(string $value): string
    {
        return self::encode($value, '/(?:[^'.self::CHAR_UNRESERVED.self::CHAR_SUB_DELIMS.'%]++|%(?![A-Fa-f0-9]{2}))/');
    }

    /**
     * Percent-encodes characters matched by $pattern; existing %XX sequences are kept.
     */
    private static function encode(string $value, string $pattern): string
    {
        return preg_replace_callback($pattern, static fn (array $match): string => rawurlencode($match[0]), $value) ?? $value;
    }
}
