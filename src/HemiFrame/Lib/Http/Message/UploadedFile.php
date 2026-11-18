<?php

namespace HemiFrame\Lib\Http\Message;

/**
 * @author heminei <heminei@heminei.com>
 */
class UploadedFile implements \Psr\Http\Message\UploadedFileInterface, \Stringable
{
    private const ERRORS = [
        UPLOAD_ERR_OK,
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE,
        UPLOAD_ERR_PARTIAL,
        UPLOAD_ERR_NO_FILE,
        UPLOAD_ERR_NO_TMP_DIR,
        UPLOAD_ERR_CANT_WRITE,
        UPLOAD_ERR_EXTENSION,
    ];

    private ?\Psr\Http\Message\StreamInterface $stream = null;

    private ?string $file = null;

    private ?string $clientFilename;

    private ?string $clientMediaType;

    private ?int $size = null;

    private int $error;

    private bool $moved = false;

    /**
     * @param \Psr\Http\Message\StreamInterface|resource|string $streamOrFile stream, resource or path to the file; ignored when $error is not UPLOAD_ERR_OK
     */
    public function __construct($streamOrFile, ?string $clientFilename = null, ?string $clientMediaType = null, int $error = UPLOAD_ERR_OK)
    {
        if (!in_array($error, self::ERRORS, true)) {
            throw new \InvalidArgumentException('Invalid upload error status: '.$error);
        }

        $this->error = $error;
        $this->clientFilename = $clientFilename;
        $this->clientMediaType = $clientMediaType;

        if (UPLOAD_ERR_OK !== $error) {
            return;
        }

        if (is_string($streamOrFile)) {
            if (!is_file($streamOrFile)) {
                throw new \InvalidArgumentException('Invalid file provided for UploadedFile. File not exists: '.$streamOrFile);
            }
            $this->file = $streamOrFile;
            $size = filesize($streamOrFile);
            $this->size = false === $size ? null : $size;
        } elseif (is_resource($streamOrFile)) {
            $this->stream = new Stream($streamOrFile);
            $this->size = $this->stream->getSize();
        } elseif ($streamOrFile instanceof \Psr\Http\Message\StreamInterface) {
            $this->stream = $streamOrFile;
            $this->size = $this->stream->getSize();
        } else {
            throw new \InvalidArgumentException('Invalid stream or file provided for UploadedFile');
        }
    }

    /**
     * Returns the path to the file as a string.
     */
    public function __toString(): string
    {
        if (null !== $this->file) {
            return $this->file;
        }
        $uri = null !== $this->stream ? $this->stream->getMetadata('uri') : null;

        return is_string($uri) ? $uri : '';
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
        $this->assertActive();

        if (null === $this->stream) {
            $resource = fopen((string) $this->file, 'r');
            if (false === $resource) {
                throw new \RuntimeException('Unable to open uploaded file: '.$this->file);
            }
            $this->stream = new Stream($resource);
        }

        return $this->stream;
    }

    public function moveTo(string $targetPath): void
    {
        $this->assertActive();

        if ('' === $targetPath) {
            throw new \InvalidArgumentException('Invalid path provided for move operation; must be a non-empty string');
        }
        if (is_dir($targetPath)) {
            throw new \InvalidArgumentException('Invalid path provided for move operation; must be a file path, not a directory: '.$targetPath);
        }
        if (!is_writable(dirname($targetPath))) {
            throw new \InvalidArgumentException('Invalid path provided for move operation; directory is not writable: '.dirname($targetPath));
        }

        if (null !== $this->file) {
            // move_uploaded_file() only accepts files uploaded through the current SAPI request.
            $moved = 'cli' === PHP_SAPI ? rename($this->file, $targetPath) : move_uploaded_file($this->file, $targetPath);
            if (!$moved) {
                throw new \RuntimeException('Unable to move uploaded file to '.$targetPath);
            }
        } else {
            $this->copyStreamTo($this->getStream(), $targetPath);
        }

        $this->file = $targetPath;
        $this->moved = true;
    }

    private function copyStreamTo(\Psr\Http\Message\StreamInterface $source, string $targetPath): void
    {
        $target = fopen($targetPath, 'w');
        if (false === $target) {
            throw new \RuntimeException('Unable to open target path: '.$targetPath);
        }

        try {
            if ($source->isSeekable()) {
                $source->rewind();
            }
            while (!$source->eof()) {
                $chunk = $source->read(1048576);
                if ('' === $chunk) {
                    break;
                }
                if (false === fwrite($target, $chunk)) {
                    throw new \RuntimeException('Unable to write to target path: '.$targetPath);
                }
            }
        } finally {
            fclose($target);
        }
    }

    private function assertActive(): void
    {
        if (UPLOAD_ERR_OK !== $this->error) {
            throw new \RuntimeException('Cannot retrieve stream due to upload error');
        }
        if ($this->moved) {
            throw new \RuntimeException('Cannot retrieve stream after it has already been moved');
        }
    }
}
