<?php

namespace HemiFrame\Lib\Http\Message;

/**
 * @author heminei <heminei@heminei.com>
 */
class Message implements \Psr\Http\Message\MessageInterface
{
    private \Psr\Http\Message\StreamInterface $body;

    private array $headers = [];

    private string $protocolVersion = '1.1';

    private bool $immutable = true;

    /**
     * @param array<array-key, string|int|float|array<string|int|float>> $headers header name => value(s); integer keys are rejected
     * @param \Psr\Http\Message\StreamInterface|string|null              $body
     */
    public function __construct(array $headers = [], $body = null, string $protocolVersion = '1.1')
    {
        foreach ($headers as $name => $value) {
            if (is_int($name)) {
                throw new \InvalidArgumentException('Header names must be string array keys, e.g. ["Content-Type" => "text/html"]; use withHeader() for numeric names');
            }
            $this->addHeader($name, $value);
        }
        if ($body instanceof \Psr\Http\Message\StreamInterface) {
            $this->body = $body;
        } else {
            $this->body = new Stream();
            if (null !== $body) {
                $this->body->write($body);
                $this->body->rewind();
            }
        }
        if ('' !== $protocolVersion) {
            $this->protocolVersion = self::filterProtocolVersion($protocolVersion);
        }
    }

    public function getBody(): \Psr\Http\Message\StreamInterface
    {
        return $this->body;
    }

    public function getHeader(string $name): array
    {
        foreach ($this->headers as $key => $value) {
            if (0 === strcasecmp((string) $key, $name)) {
                return $value;
            }
        }

        return [];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function getImmutable(): bool
    {
        return $this->immutable;
    }

    public function hasHeader(string $name): bool
    {
        if (isset($this->headers[$name])) {
            return true;
        }

        foreach (array_keys($this->headers) as $key) {
            if (0 === strcasecmp((string) $key, $name)) {
                return true;
            }
        }

        return false;
    }

    public function withAddedHeader(string $name, $value): self
    {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->addHeader($name, $value);

        return $new;
    }

    /**
     * @return static
     */
    public function withBody(\Psr\Http\Message\StreamInterface $body): self
    {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->body = $body;

        return $new;
    }

    /**
     * @return static
     */
    public function withHeader(string $name, $value): self
    {
        self::assertHeaderName($name);
        $value = self::normalizeHeaderValue($value);

        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        // Replace in place so the header keeps its position.
        $headers = [];
        $replaced = false;
        foreach ($new->headers as $key => $existing) {
            if (0 !== strcasecmp((string) $key, $name)) {
                $headers[$key] = $existing;
            } elseif (!$replaced) {
                $headers[$name] = $value;
                $replaced = true;
            }
        }
        if (!$replaced) {
            $headers[$name] = $value;
        }
        $new->headers = $headers;

        return $new;
    }

    /**
     * @return static
     */
    public function withProtocolVersion(string $version): self
    {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->protocolVersion = self::filterProtocolVersion($version);

        return $new;
    }

    public function withoutHeader(string $name): self
    {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->unsetHeader($name);

        return $new;
    }

    private function addHeader(string $name, $value): void
    {
        self::assertHeaderName($name);
        $value = self::normalizeHeaderValue($value);

        foreach (array_keys($this->headers) as $key) {
            if (0 === strcasecmp((string) $key, $name)) {
                $this->headers[$key] = array_merge($this->headers[$key], $value);

                return;
            }
        }

        $this->headers[$name] = $value;
    }

    private static function filterProtocolVersion(string $version): string
    {
        if (1 !== preg_match('/^\d+(?:\.\d+)?$/D', $version)) {
            throw new \InvalidArgumentException('Invalid HTTP protocol version: '.json_encode($version));
        }

        return $version;
    }

    private static function assertHeaderName(string $name): void
    {
        // RFC 7230 token
        if (1 !== preg_match("/^[a-zA-Z0-9'`#$%&*+.^_|~!-]+$/D", $name)) {
            throw new \InvalidArgumentException('Invalid header name: '.json_encode($name));
        }
    }

    /**
     * @return string[]
     */
    private static function normalizeHeaderValue($value): array
    {
        if (!is_array($value)) {
            $value = [$value];
        }
        if ([] === $value) {
            throw new \InvalidArgumentException('Header value can not be an empty array');
        }

        $result = [];
        foreach ($value as $item) {
            if (!is_string($item) && !is_int($item) && !is_float($item)) {
                throw new \InvalidArgumentException('Header value must be a string or a number');
            }
            $item = trim((string) $item, " \t");
            // Visible characters, spaces and tabs only; rejects CR/LF injection.
            if (1 !== preg_match('/^[\x20\x09\x21-\x7E\x80-\xFF]*$/D', $item)) {
                throw new \InvalidArgumentException('Invalid header value: '.json_encode($item));
            }
            $result[] = $item;
        }

        return $result;
    }

    private function unsetHeader(string $name): void
    {
        foreach (array_keys($this->headers) as $key) {
            if (0 === strcasecmp((string) $key, $name)) {
                unset($this->headers[$key]);
            }
        }
    }

    /**
     * @return static
     */
    public function setImmutable(bool $immutable): self
    {
        $this->immutable = $immutable;

        return $this;
    }
}
