<?php

namespace HemiFrame\Lib\Http\Message;

class ServerRequest extends Request implements \Psr\Http\Message\ServerRequestInterface
{
    /**
     * @var array
     */
    private $attributes = [];

    /**
     * @var array
     */
    private $cookieParams = [];

    /**
     * @var array
     */
    private $serverParams = [];

    /**
     * @var array
     */
    private $queryParams = [];

    /**
     * @var array
     */
    private $uploadedFiles = [];

    /**
     * @var array|object
     */
    private $parsedBody = [];

    public function getAttribute($name, $default = null)
    {
        if (false === array_key_exists($name, $this->attributes)) {
            return $default;
        }

        return $this->attributes[$name];
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    public function getCookieParam($name, $default = null)
    {
        if (false === array_key_exists($name, $this->cookieParams)) {
            return $default;
        }

        return $this->cookieParams[$name];
    }

    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    public function getParsedBodyParam($name, $default = null)
    {
        if (false === array_key_exists($name, $this->parsedBody)) {
            return $default;
        }

        return $this->parsedBody[$name];
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function getQueryParam($name, $default = null)
    {
        if (false === array_key_exists($name, $this->queryParams)) {
            return $default;
        }

        return $this->queryParams[$name];
    }

    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    public function getServerParam($name, $default = null)
    {
        if (false === array_key_exists($name, $this->serverParams)) {
            return $default;
        }

        return $this->serverParams[$name];
    }

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * @param string $name
     *
     * @return UploadedFile|mixed
     */
    public function getUploadedFile($name, $default = null)
    {
        if (false === array_key_exists($name, $this->uploadedFiles)) {
            return $default;
        }

        return $this->uploadedFiles[$name];
    }

    public function withAttribute($name, $value): self
    {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->attributes[$name] = $value;

        return $new;
    }

    public function withCookieParams(array $cookies): self
    {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->cookieParams = $cookies;

        return $new;
    }

    public function withParsedBody($data): self
    {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->parsedBody = $data;

        return $new;
    }

    public function withQueryParams(array $query): self
    {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->queryParams = $query;

        return $new;
    }

    public function withUploadedFiles(array $uploadedFiles): self
    {
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $new->uploadedFiles = $uploadedFiles;

        return $new;
    }

    public function withoutAttribute($name): self
    {
        if (false === array_key_exists($name, $this->attributes)) {
            return $this;
        }

        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        unset($new->attributes[$name]);

        return $new;
    }

    public function getClientIP()
    {
        foreach (['CLIENT-IP', 'X-FORWARDED-FOR', 'X-FORWARDED', 'X-CLUSTER-CLIENT-IP', 'FORWARDED-FOR', 'FORWARDED'] as $key) {
            if (true === $this->hasHeader($key)) {
                foreach ($this->getHeader($key) as $ip) {
                    if (false !== filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
                foreach ($this->getHeader($key) as $ip) {
                    if (false !== filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }

        return $this->getServerParam('REMOTE_ADDR');
    }

    public function fromGlobals(): self
    {
        /** @var ServerRequest $new */
        $new = $this;
        if ($this->getImmutable()) {
            $new = clone $this;
        }

        $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if ('HTTP_' == substr($name, 0, 5)) {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
            if (isset($_SERVER['CONTENT_TYPE'])) {
                $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
            }
        }

        foreach ($headers as $name => $value) {
            $new = $new->withHeader($name, [$value]);
        }

        $uri = new Uri();
        $uri = $uri->fromGlobals();

        $body = new Stream(fopen('php://input', 'r'));
        $protocolVersion = isset($_SERVER['SERVER_PROTOCOL']) ? str_replace('HTTP/', '', $_SERVER['SERVER_PROTOCOL']) : '1.1';

        $new->serverParams = $_SERVER;

        $new = $new->withMethod($method);
        $new = $new->withUri($uri);
        $new = $new->withBody($body);
        $new = $new->withProtocolVersion($protocolVersion);
        $new = $new->withCookieParams($_COOKIE);
        $new = $new->withQueryParams($_GET);

        if ($new->hasHeader('Content-Type') && strstr($new->getHeaderLine('Content-Type'), 'application/json')) {
            $input = file_get_contents('php://input');
            $json = json_decode($input, true);
            if (is_array($json)) {
                $new = $new->withParsedBody($json);
            }
        } elseif ($new->hasHeader('Content-Type') && strstr($new->getHeaderLine('Content-Type'), 'multipart/form-data') && 'POST' !== $new->getMethod()) {
            $multipartFormData = $new->parseMultipartFormData($body->getContents(), $new->getHeaderLine('Content-Type'));
            $new = $new->withParsedBody($multipartFormData['params']);
            $new = $new->withUploadedFiles($multipartFormData['files']);
        } else {
            $new = $new->withParsedBody($_POST);

            $uploadedFiles = [];
            foreach ($_FILES as $name => $file) {
                if (is_string($file['tmp_name'])) {
                    if (0 == strlen($file['tmp_name'])) {
                        continue;
                    }
                    $uploadedFiles[$name] = new UploadedFile($file['tmp_name'], $file['name'], $file['type'], $file['error']);
                } else {
                    $uploadedFiles[$name] = [];
                    foreach (array_keys($file['tmp_name']) as $key) {
                        $uploadedFiles[$name][] = new UploadedFile($file['tmp_name'][$key], $file['name'][$key], $file['type'][$key], $file['error'][$key]);
                    }
                }
            }
            $new = $new->withUploadedFiles($uploadedFiles);
        }

        return $new;
    }

    private function parseMultipartFormData(string $string, string $contentType)
    {
        $boundaryArray = [];
        preg_match('/boundary=(?<boundary>.*)$/', $contentType, $boundaryArray);
        $boundary = isset($boundaryArray['boundary']) ? $boundaryArray['boundary'] : null;

        if (empty($boundary)) {
            throw new \InvalidArgumentException("Can't find boundary in string");
        }
        $blocks = explode("--$boundary", $string);

        $files = [];
        $params = [];

        foreach ($blocks as $block) {
            $block = ltrim($block, "\n\r");

            $contentDisposition = [];
            preg_match("/Content-Disposition\:(.*?)(name=\"(?<name>.*?)\")(;\s)?(filename=\"(?<filename>.*?)\")?/", $block, $contentDisposition);

            if (!isset($contentDisposition['name'])) {
                continue;
            }
            $isArray = false;

            $keyMatch = [];
            if (1 === preg_match("/(.*)\[(?<key>.*)\]$/", $contentDisposition['name'], $keyMatch)) {
                $isArray = true;
                $contentDisposition['name'] = rtrim($contentDisposition['name'], '['.$keyMatch['key'].']');
            }

            $value = preg_replace('/Content-(.*)[\n|\n\r]+/', '', $block);
            $value = rtrim($value, "\n\r");

            if (isset($contentDisposition['filename'])) {
                $contentType = [];
                preg_match("/Content-Type\:\s(?<value>[a-zA-Z\/\-]+)/", $block, $contentType);

                if (!isset($contentType['value'])) {
                    $contentType['value'] = null;
                }
                $file = tmpfile();
                fwrite($file, $value);

                $uploadedFile = new UploadedFile($file, $contentDisposition['filename'], $contentType['value']);

                if ($isArray) {
                    if (!isset($files[$contentDisposition['name']])) {
                        $files[$contentDisposition['name']] = [];
                    }
                    if (empty($keyMatch['key'])) {
                        $files[$contentDisposition['name']][] = $uploadedFile;
                    } else {
                        $files[$contentDisposition['name']][$keyMatch['key']] = $uploadedFile;
                    }
                } else {
                    $files[$contentDisposition['name']] = $uploadedFile;
                }
            } else {
                if ($isArray) {
                    if (!isset($params[$contentDisposition['name']])) {
                        $params[$contentDisposition['name']] = [];
                    }
                    if (empty($keyMatch['key'])) {
                        $params[$contentDisposition['name']][] = $value;
                    } else {
                        $params[$contentDisposition['name']][$keyMatch['key']] = $value;
                    }
                } else {
                    $params[$contentDisposition['name']] = $value;
                }
            }
        }

        return [
            'files' => $files,
            'params' => $params,
        ];
    }
}
