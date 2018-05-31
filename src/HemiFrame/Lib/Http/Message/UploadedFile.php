<?php

namespace HemiFrame\Lib\Http\Message;

/**
 * @author heminei <heminei@heminei.com>
 */
class UploadedFile implements \Psr\Http\Message\UploadedFileInterface
{

    /**
     * @var StreamInterface|null
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

    /**
     * @var int[]
     */
    private static $errors = [
        UPLOAD_ERR_OK,
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE,
        UPLOAD_ERR_PARTIAL,
        UPLOAD_ERR_NO_FILE,
        UPLOAD_ERR_NO_TMP_DIR,
        UPLOAD_ERR_CANT_WRITE,
        UPLOAD_ERR_EXTENSION,
    ];

    public function __construct($streamOrFile, $clientFilename = null, $clientMediaType = null, $error = UPLOAD_ERR_OK)
    {
        if (is_string($streamOrFile)) {
            if (!file_exists($streamOrFile)) {
                throw new \InvalidArgumentException('Invalid file provided for UploadedFile. File not exists: ' . $streamOrFile);
            }
            $this->stream = new Stream(fopen($streamOrFile, "r+"));
            $this->clientFilename = basename($streamOrFile);
            $this->clientMediaType = mime_content_type($streamOrFile);
        } elseif (is_resource($streamOrFile)) {
            $this->stream = new Stream($streamOrFile);
        } elseif ($streamOrFile instanceof \Psr\Http\Message\StreamInterface) {
            $this->stream = $streamOrFile;
        } else {
            throw new \InvalidArgumentException('Invalid stream or file provided for UploadedFile');
        }

        if ($clientFilename !== null) {
            $this->clientFilename = $clientFilename;
        }
        if ($clientMediaType !== null) {
            $this->clientMediaType = $clientMediaType;
        }
        if (!is_int($error)) {
            throw new \InvalidArgumentException('Upload file error status must be an integer');
        }

        $this->error = $error;
        $this->size = $this->stream->getSize();
    }

    public function getClientFilename()
    {
        return $this->clientFilename;
    }

    public function getClientMediaType()
    {
        return $this->clientMediaType;
    }

    public function getError() : int
    {
        return $this->error;
    }

    public function getSize()
    {
        return $this->size;
    }

    public function getStream() : \Psr\Http\Message\StreamInterface
    {
        return $this->stream;
    }

    public function moveTo($targetPath)
    {
        if (empty($targetPath) || !is_string($targetPath)) {
            throw new \InvalidArgumentException('Invalid path provided for move operation; must be a non-empty string');
        }
        $pathinfo = pathinfo($targetPath);

        if (!is_writable($pathinfo['dirname'])) {
            throw new \InvalidArgumentException('Invalid path provided for move operation; must be a writable');
        }

        $stream = new Stream(fopen($targetPath, "w"));
        $stream->write((string)$this->getStream());
        $stream->close();
    }

}
