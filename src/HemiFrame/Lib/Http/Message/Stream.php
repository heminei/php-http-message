<?php

namespace HemiFrame\Lib\Http\Message;

/**
 * @author heminei <heminei@heminei.com>
 */
class Stream implements \Psr\Http\Message\StreamInterface
{

    private $stream;
    private $isReadable;
    private $isSeekable;
    private $isWritable;
    private $size;
    private $uri;

    /**
     * @var array Hash of readable and writable stream types
     */
    private static $readWriteHash = [
        'read' => [
            'r' => true, 'w+' => true, 'r+' => true, 'x+' => true, 'c+' => true,
            'rb' => true, 'w+b' => true, 'r+b' => true, 'x+b' => true,
            'c+b' => true, 'rt' => true, 'w+t' => true, 'r+t' => true,
            'x+t' => true, 'c+t' => true, 'a+' => true
        ],
        'write' => [
            'w' => true, 'w+' => true, 'rw' => true, 'r+' => true, 'x+' => true,
            'c+' => true, 'wb' => true, 'w+b' => true, 'r+b' => true,
            'x+b' => true, 'c+b' => true, 'w+t' => true, 'r+t' => true,
            'x+t' => true, 'c+t' => true, 'a' => true, 'a+' => true
        ]
    ];

    public function __construct($stream = null)
    {
        if ($stream === null) {
            $this->stream = tmpfile();
        } else if (is_resource($stream)) {
            $this->stream = $stream;
        } else {
            throw new \InvalidArgumentException('Stream must be a resource');
        }

        $meta = $this->getMetadata();
        $this->isSeekable = $meta['seekable'];
        $this->isReadable = isset(self::$readWriteHash['read'][$meta['mode']]);
        $this->isWritable = isset(self::$readWriteHash['write'][$meta['mode']]);
        $this->uri = $meta['uri'];
    }

    public function __destruct()
    {
        $this->close();
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toString(): string
    {
        $this->seek(0);
        return $this->getContents();
    }

    public function close(): void
    {
        if (isset($this->stream)) {
            if (is_resource($this->stream)) {
                fclose($this->stream);
            }
            $this->detach();
        }
    }

    public function detach()
    {
        if (!isset($this->stream)) {
            return null;
        }
        $result = $this->stream;
        unset($this->stream);

        $this->size = null;
        $this->uri = null;
        $this->readable = false;
        $this->writable = false;
        $this->seekable = false;

        return $result;
    }

    public function eof(): bool
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }
        return feof($this->stream);
    }

    public function getContents(): string
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }
        $contents = stream_get_contents($this->stream);
        if ($contents === false) {
            throw new \RuntimeException('Unable to read stream contents');
        }
        return $contents;
    }

    public function getMetadata($key = null)
    {
        if (!isset($this->stream)) {
            return isset($key) ? null : [];
        } elseif (!isset($key)) {
            return stream_get_meta_data($this->stream);
        }
        $meta = stream_get_meta_data($this->stream);
        return isset($meta[$key]) ? $meta[$key] : null;
    }

    public function getSize()
    {
        if ($this->size !== null) {
            return $this->size;
        }
        if (!isset($this->stream)) {
            return null;
        }
        // Clear the stat cache if the stream has a URI
        if ($this->uri) {
            clearstatcache(true, $this->uri);
        }

        $stats = fstat($this->stream);
        if (isset($stats['size'])) {
            $this->size = $stats['size'];
            return $this->size;
        }
        return null;
    }

    public function isReadable(): bool
    {
        return $this->isReadable;
    }

    public function isSeekable(): bool
    {
        return $this->isSeekable;
    }

    public function isWritable(): bool
    {
        return $this->isWritable;
    }

    public function read($length): string
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }
        if (!$this->isReadable) {
            throw new \RuntimeException('Cannot read from non-readable stream');
        }
        if ($length < 0) {
            throw new \RuntimeException('Length parameter cannot be negative');
        }
        if ($length === 0) {
            return '';
        }
        $string = fread($this->stream, $length);
        if ($string === false) {
            throw new \RuntimeException('Unable to read from stream');
        }
        return $string;
    }

    public function rewind()
    {
        $this->seek(0);
    }

    public function seek($offset, $whence = SEEK_SET)
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }
        if (!$this->isSeekable) {
            throw new \RuntimeException('Stream is not seekable');
        }
        if (fseek($this->stream, $offset, $whence) === -1) {
            throw new \RuntimeException('Unable to seek to stream position '
                . $offset . ' with whence ' . var_export($whence, true));
        }
    }

    public function tell(): int
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }
        $result = ftell($this->stream);
        if ($result === false) {
            throw new \RuntimeException('Unable to determine stream position');
        }
        return $result;
    }

    public function write($string): int
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }
        if (!$this->isWritable) {
            throw new \RuntimeException('Cannot write to a non-writable stream');
        }
        // We can't know the size after writing anything
        $this->size = null;
        $result = fwrite($this->stream, $string);
        if ($result === false) {
            throw new \RuntimeException('Unable to write to stream');
        }
        return $result;
    }
}
