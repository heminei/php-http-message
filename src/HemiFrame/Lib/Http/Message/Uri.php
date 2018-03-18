<?php

namespace HemiFrame\Lib\Http\Message;

/**
 * @author heminei <heminei@heminei.com>
 */
class Uri implements \Psr\Http\Message\UriInterface {

    /**
     * @var string Uri scheme.
     */
    private $scheme = '';

    /**
     * @var string Uri user info.
     */
    private $userInfo = '';

    /**
     * @var string Uri host.
     */
    private $host = '';

    /**
     * @var int|null Uri port.
     */
    private $port;

    /**
     * @var string Uri path.
     */
    private $path = '';

    /**
     * @var string Uri query string.
     */
    private $query = '';

    /**
     * @var string Uri fragment.
     */
    private $fragment = '';

    /**
     * @var bool
     */
    private $immutable = true;

    /**
     *
     * @var array
     */
    private static $defaultPorts = [
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

    public function __construct(string $uri = "") {
        if ($uri !== "") {
            $parts = parse_url($uri);

            if (isset($parts['scheme'])) {
                $this->scheme = $parts['scheme'];
            }
            if (isset($parts['host'])) {
                $this->host = $parts['host'];
            }
            if (isset($parts['port'])) {
                $this->port = $parts['port'];
            }
            if (isset($parts['path'])) {
                $this->path = $parts['path'];
            }
            if (isset($parts['query'])) {
                $this->query = $parts['query'];
            }
            if (isset($parts['fragment'])) {
                $this->fragment = $parts['fragment'];
            }
            if (isset($parts['user'])) {
                $this->userInfo = $parts['user'];
            }
            if (isset($parts['pass'])) {
                $this->userInfo .= ":" . $parts['pass'];
            }
        }
    }

    public function __toString(): string {
        return $this->toString();
    }

    public function toString(): string {
        $uri = '';
        // weak type checks to also accept null until we can add scalar type hints
        if ($this->scheme != '') {
            $uri .= $this->scheme . ':';
        }
        if ($this->getAuthority() != '' || $this->scheme === 'file') {
            $uri .= '//' . $this->getAuthority();
        }
        $uri .= $this->path;
        if ($this->query != '') {
            $uri .= '?' . $this->query;
        }
        if ($this->fragment != '') {
            $uri .= '#' . $this->fragment;
        }
        return $uri;
    }

    public function getAuthority(): string {
        $authority = $this->host;
        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }
        if ($this->port !== null && self::$defaultPorts[$this->scheme] != $this->port) {
            $authority .= ':' . $this->port;
        }
        return $authority;
    }

    public function getFragment(): string {
        return $this->fragment;
    }

    public function getHost(): string {
        return $this->host;
    }

    public function getPath(): string {
        return $this->path;
    }

    public function getPort() {
        return $this->port;
    }

    public function getQuery(): string {
        return $this->query;
    }

    public function getScheme(): string {
        return $this->scheme;
    }

    public function getUserInfo(): string {
        return $this->userInfo;
    }

    public function getImmutable() {
        return $this->immutable;
    }

    public function withFragment($fragment): self {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->fragment = $fragment;

        return $new;
    }

    public function withHost($host): self {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->host = $host;

        return $new;
    }

    public function withPath($path): self {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->path = $path;

        return $new;
    }

    public function withPort($port): self {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->port = $port;

        return $new;
    }

    public function withQuery($query): self {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->query = $query;

        return $new;
    }

    public function buildQuery(array $array): self {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->query = http_build_query($array);

        return $new;
    }

    public function withScheme($scheme): self {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->scheme = $scheme;

        return $new;
    }

    public function withUserInfo($user, $password = null): self {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $userInfo = $user;
        if ($password != '') {
            $userInfo .= ':' . $password;
        }
        $new->userInfo = $userInfo;

        return $new;
    }

    public function setImmutable(bool $immutable) {
        $this->immutable = $immutable;

        return $this;
    }

    /**
     * Get a Uri populated with values from $_SERVER.
     * @return self
     */
    public function fromGlobals() {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new = $new->withScheme(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http');
        $hasPort = false;
        if (isset($_SERVER['HTTP_HOST'])) {
            $hostHeaderParts = explode(':', $_SERVER['HTTP_HOST']);
            $new = $new->withHost($hostHeaderParts[0]);
            if (isset($hostHeaderParts[1])) {
                $hasPort = true;
                $new = $new->withPort($hostHeaderParts[1]);
            }
        } elseif (isset($_SERVER['SERVER_NAME'])) {
            $new = $new->withHost($_SERVER['SERVER_NAME']);
        } elseif (isset($_SERVER['SERVER_ADDR'])) {
            $new = $new->withHost($_SERVER['SERVER_ADDR']);
        }
        if (!$hasPort && isset($_SERVER['SERVER_PORT'])) {
            $new = $new->withPort($_SERVER['SERVER_PORT']);
        }
        $hasQuery = false;
        if (isset($_SERVER['REQUEST_URI'])) {
            $requestUriParts = explode('?', $_SERVER['REQUEST_URI'], 2);
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

}
