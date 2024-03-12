<?php

namespace HemiFrame\Lib\Http\Message;

/**
 * @author heminei <heminei@heminei.com>
 */
class UploadedFile implements \Psr\Http\Message\UploadedFileInterface
{
    /**
     * @var \Psr\Http\Message\StreamInterface|null
     */
    private $stream;

    /**
     * @var string
     */
    private $clientFilename;

    /**
     * @var string
     */
    private $clientMediaType;

    /**
     * @var int
     */
    private $size;

    /**
     * @var int
     */
    private $error;

    public function __construct($streamOrFile, $clientFilename = null, $clientMediaType = null, $error = UPLOAD_ERR_OK)
    {
        if (is_string($streamOrFile)) {
            if (!file_exists($streamOrFile)) {
                throw new \InvalidArgumentException('Invalid file provided for UploadedFile. File not exists: '.$streamOrFile);
            }
            $this->stream = new Stream(fopen($streamOrFile, 'r+'));
            $this->clientFilename = basename($streamOrFile);
            $this->clientMediaType = mime_content_type($streamOrFile);
        } elseif (is_resource($streamOrFile)) {
            $this->stream = new Stream($streamOrFile);
            $this->clientFilename = basename($this->getStream()->getMetadata('uri'));
            $this->clientMediaType = mime_content_type($this->getStream()->getMetadata('uri'));
        } elseif ($streamOrFile instanceof \Psr\Http\Message\StreamInterface) {
            $this->stream = $streamOrFile;
            $this->clientFilename = basename($this->getStream()->getMetadata('uri'));
            $this->clientMediaType = mime_content_type($this->getStream()->getMetadata('uri'));
        } else {
            throw new \InvalidArgumentException('Invalid stream or file provided for UploadedFile');
        }

        if (null !== $clientFilename) {
            $this->clientFilename = $clientFilename;
        }
        if (null !== $clientMediaType) {
            $this->clientMediaType = $clientMediaType;
        }
        if (!is_int($error)) {
            throw new \InvalidArgumentException('Upload file error status must be an integer');
        }

        $this->error = $error;
        $this->size = $this->stream->getSize();
    }

    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getStream(): \Psr\Http\Message\StreamInterface
    {
        return $this->stream;
    }

    public function moveTo($targetPath): void
    {
        if (empty($targetPath) || !is_string($targetPath)) {
            throw new \InvalidArgumentException('Invalid path provided for move operation; must be a non-empty string');
        }
        $pathinfo = pathinfo($targetPath);

        if (!is_writable($pathinfo['dirname'])) {
            throw new \InvalidArgumentException('Invalid path provided for move operation; must be a writable');
        }

        $stream = new Stream(fopen($targetPath, 'w'));
        $stream->write((string) $this->getStream());
        $stream->close();
    }
}
