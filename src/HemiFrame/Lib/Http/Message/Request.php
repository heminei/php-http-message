<?php

namespace HemiFrame\Lib\Http\Message;

/**
 * @author heminei <heminei@heminei.com>
 */
class Request extends Message implements \Psr\Http\Message\RequestInterface
{

    /**
     * @var string
     */
    private $method = "GET";

    /**
     * @var null|string
     */
    private $requestTarget;

    /**
     * @var \Psr\Http\Message\UriInterface
     */
    private $uri;

    /**
     *
     * @param string $method
     * @param \Psr\Http\Message\UriInterface|string $uri
     * @param array $headers
     * @param Stream|string $body
     * @param string $protocolVersion
     */
    public function __construct(string $method = "GET", $uri = "", array $headers = array(), $body = null, string $protocolVersion = '1.1')
    {
        if (!($uri instanceof \Psr\Http\Message\UriInterface)) {
            $uri = new Uri($uri);
        }
        $this->method = strtoupper($method);
        $this->uri = $uri;

        parent::__construct($headers, $body, $protocolVersion);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }
        $target = $this->uri->getPath();
        if ($target == '') {
            $target = '/';
        }
        if ($this->uri->getQuery() != '') {
            $target .= '?' . $this->uri->getQuery();
        }
        return $target;
    }

    public function getUri(): \Psr\Http\Message\UriInterface
    {
        return $this->uri;
    }

    public function withMethod($method): self
    {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->method = strtoupper($method);

        return $new;
    }

    public function withRequestTarget($requestTarget): self
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

    public function withUri(\Psr\Http\Message\UriInterface $uri, $preserveHost = false): self
    {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->uri = $uri;

        if (!$preserveHost) {
            $host = $new->uri->getHost();
            $port = $new->uri->getPort();
            if ($port !== null) {
                $host .= ':' . $port;
            }

            if ($new->hasHeader("Host")) {
                $new->withHeader("Host", $host);
            }
        }

        return $new;
    }
}
