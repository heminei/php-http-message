<?php

namespace HemiFrame\Lib\Http\Message;

/**
 * @author heminei <heminei@heminei.com>
 */
class Request extends Message implements \Psr\Http\Message\RequestInterface
{
    private string $method = 'GET';

    private ?string $requestTarget = null;

    private \Psr\Http\Message\UriInterface $uri;

    /**
     * @param \Psr\Http\Message\UriInterface|string         $uri
     * @param \Psr\Http\Message\StreamInterface|string|null $body
     */
    public function __construct(string $method = 'GET', $uri = '', array $headers = [], $body = null, string $protocolVersion = '1.1')
    {
        if (!$uri instanceof \Psr\Http\Message\UriInterface) {
            $uri = new Uri($uri);
        }
        $this->method = self::filterMethod($method);
        $this->uri = $uri;

        if ('' !== $uri->getHost() && !self::containsHeader($headers, 'Host')) {
            // PSR-7 recommends Host as the first header.
            $headers = ['Host' => self::getHostFromUri($uri)] + $headers;
        }

        parent::__construct($headers, $body, $protocolVersion);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getRequestTarget(): string
    {
        if (null !== $this->requestTarget) {
            return $this->requestTarget;
        }
        $target = $this->uri->getPath();
        if ('' == $target) {
            $target = '/';
        }
        if ('' != $this->uri->getQuery()) {
            $target .= '?'.$this->uri->getQuery();
        }

        return $target;
    }

    public function getUri(): \Psr\Http\Message\UriInterface
    {
        return $this->uri;
    }

    /**
     * @return static
     */
    public function withMethod(string $method): self
    {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->method = self::filterMethod($method);

        return $new;
    }

    public function withRequestTarget(string $requestTarget): self
    {
        if (preg_match('#\s#', $requestTarget)) {
            throw new \InvalidArgumentException('Invalid request target provided; cannot contain whitespace');
        }

        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->requestTarget = $requestTarget;

        return $new;
    }

    /**
     * @return static
     */
    public function withUri(\Psr\Http\Message\UriInterface $uri, bool $preserveHost = false): self
    {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->uri = $uri;

        if ('' !== $uri->getHost() && (!$preserveHost || '' === $new->getHeaderLine('Host'))) {
            $new = $new->withHeader('Host', self::getHostFromUri($uri));
        }

        return $new;
    }

    private static function filterMethod(string $method): string
    {
        // RFC 7230 token; methods are case-sensitive, so the value is kept as given.
        if (1 !== preg_match("/^[a-zA-Z0-9'`#$%&*+.^_|~!-]+$/D", $method)) {
            throw new \InvalidArgumentException('Invalid HTTP method: '.json_encode($method));
        }

        return $method;
    }

    private static function getHostFromUri(\Psr\Http\Message\UriInterface $uri): string
    {
        $host = $uri->getHost();
        $port = $uri->getPort();

        return null !== $port ? $host.':'.$port : $host;
    }

    private static function containsHeader(array $headers, string $name): bool
    {
        foreach (array_keys($headers) as $key) {
            if (0 === strcasecmp((string) $key, $name)) {
                return true;
            }
        }

        return false;
    }
}
