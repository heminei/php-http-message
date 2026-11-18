<?php

namespace HemiFrame\Lib\Http\Message;

/**
 * @author heminei <heminei@heminei.com>
 */
class Response extends Message implements \Psr\Http\Message\ResponseInterface
{
    private static array $phrases = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-status',
        208 => 'Already Reported',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];

    private string $reasonPhrase = '';

    private int $statusCode = 200;

    /**
     * @param \Psr\Http\Message\StreamInterface|string|null $body
     */
    public function __construct(int $statusCode = 200, string $reasonPhrase = '', array $headers = [], $body = null, string $protocolVersion = '1.1')
    {
        self::assertStatusCode($statusCode);
        $this->statusCode = $statusCode;
        $this->reasonPhrase = self::filterReasonPhrase($statusCode, $reasonPhrase);
        parent::__construct($headers, $body, $protocolVersion);
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): self
    {
        self::assertStatusCode($code);
        $reasonPhrase = self::filterReasonPhrase($code, $reasonPhrase);

        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->statusCode = $code;
        $new->reasonPhrase = $reasonPhrase;

        return $new;
    }

    /**
     * Returns the standard phrase when $reasonPhrase is empty.
     */
    private static function filterReasonPhrase(int $code, string $reasonPhrase): string
    {
        if ('' === $reasonPhrase) {
            return self::$phrases[$code] ?? '';
        }
        // Reason phrase goes into the status line, so line breaks and control characters are rejected.
        if (1 !== preg_match('/^[\x20\x09\x21-\x7E\x80-\xFF]*$/D', $reasonPhrase)) {
            throw new \InvalidArgumentException('Invalid reason phrase: '.json_encode($reasonPhrase));
        }

        return $reasonPhrase;
    }

    public function send(): void
    {
        if (headers_sent($file, $line)) {
            throw new \RuntimeException(sprintf('Unable to send response; headers already sent in %s on line %d', $file, $line));
        }

        header('HTTP/'.$this->getProtocolVersion().' '.$this->getStatusCode().' '.$this->getReasonPhrase(), true, $this->getStatusCode());

        foreach ($this->getHeaders() as $name => $values) {
            // Keep cookies already set by setcookie(); each Set-Cookie value must be a separate line.
            $replace = 0 !== strcasecmp((string) $name, 'Set-Cookie');
            foreach ($values as $value) {
                header($name.': '.$value, $replace);
                $replace = false;
            }
        }

        echo (string) $this->getBody();
    }

    private static function assertStatusCode(int $code): void
    {
        if ($code < 100 || $code > 599) {
            throw new \InvalidArgumentException('Invalid status code: '.$code.'. Must be between 100 and 599');
        }
    }
}
