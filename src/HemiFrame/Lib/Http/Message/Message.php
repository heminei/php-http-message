<?php

namespace HemiFrame\Lib\Http\Message;

/**
 * @author heminei <heminei@heminei.com>
 */
class Message implements \Psr\Http\Message\MessageInterface {

    /**
     * @var \Psr\Http\Message\StreamInterface
     */
    private $body;

    /**
     * @var array
     */
    private $headers = [];

    /**
     * @var string
     */
    private $protocolVersion = '1.1';

    /**
     * @var bool
     */
    private $immutable = true;

    /**
     *
     * @param array $headers
     * @param \HemiFrame\Lib\Http\Message\Stream||string $body
     * @param string $protocolVersion
     */
    public function __construct(array $headers = [], $body = null, string $protocolVersion = '1.1') {
        if (!empty($headers)) {
            $this->headers = $headers;
        }
        $this->body = new Stream();
        if ($body !== null) {
            if (is_string($this->body)) {
                $this->body->write($body);
            } else if ($body instanceof Stream) {
                $this->body = $body;
            }
        }
        if (!empty($protocolVersion)) {
            $this->protocolVersion = $protocolVersion;
        }
    }

    public function getBody(): \Psr\Http\Message\StreamInterface {
        return $this->body;
    }

    public function getHeader($name): array {
        foreach ($this->headers as $key => $value) {
            if (strtolower($key) == strtolower($name)) {
                return $value;
            }
        }
        return [];
    }

    public function getHeaderLine($name): string {
        return implode(', ', $this->getHeader($name));
    }

    public function getHeaders(): array {
        return $this->headers;
    }

    public function getProtocolVersion(): string {
        return $this->protocolVersion;
    }

    public function getImmutable() {
        return $this->immutable;
    }

    public function hasHeader($name): bool {
        if (isset($this->headers[$name])) {
            return true;
        }

        foreach ($this->headers as $key => $value) {
            if (strtolower($key) == strtolower($name)) {
                return true;
            }
        }

        return false;
    }

    public function withAddedHeader($name, $value): self {
        if (!is_array($value)) {
            $value = [$value];
        }

        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        array_walk($new->headers, function(&$item, $key) use($name, $value) {
            if (strtolower($key) == strtolower($name)) {
                $item = array_merge($item, $value);
            }
        });

        return $new;
    }

    /**
     *
     * @param \Psr\Http\Message\StreamInterface $body
     * @return self
     */
    public function withBody(\Psr\Http\Message\StreamInterface $body): self {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->body = $body;

        return $new;
    }

    public function withHeader($name, $value): self {
        if (!is_array($value)) {
            $value = [$value];
        }

        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        array_walk($new->headers, function(&$item, $key) use($name, $value) {
            if (strtolower($key) == strtolower($name)) {
                unset($item);
            }
        });

        $new->headers[$name] = $value;

        return $new;
    }

    public function withProtocolVersion($version): self {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->protocolVersion = $version;

        return $new;
    }

    public function withoutHeader($name): self {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        array_walk($new->headers, function(&$item, $key) use($name) {
            if (strtolower($key) == strtolower($name)) {
                unset($item);
            }
        });

        return $new;
    }

    public function setImmutable(bool $immutable) {
        $this->immutable = $immutable;

        return $this;
    }

}
