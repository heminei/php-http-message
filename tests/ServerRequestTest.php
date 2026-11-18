<?php

namespace HemiFrame\Tests;

use HemiFrame\Lib\Http\Message\ServerRequest;
use HemiFrame\Lib\Http\Message\UploadedFile;
use PHPUnit\Framework\TestCase;

class ServerRequestTest extends TestCase
{
    private array $server;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
    }

    private function requestFromServer(array $server): ServerRequest
    {
        $_SERVER = $server + ['REQUEST_METHOD' => 'GET'];

        return (new ServerRequest())->fromGlobals();
    }

    public function testClientIpUsesOnlyRemoteAddrByDefault(): void
    {
        $request = $this->requestFromServer([
            'REMOTE_ADDR' => '203.0.113.5',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.7',
        ]);

        $this->assertSame([], $request->getClientIpHeaders());
        $this->assertSame('203.0.113.5', $request->getClientIP());
    }

    public function testClientIpUsesTrustedHeaders(): void
    {
        $request = $this->requestFromServer([
            'REMOTE_ADDR' => '203.0.113.5',
            'HTTP_X_FORWARDED_FOR' => '10.0.0.1, 198.51.100.7',
        ])->setClientIpHeaders(['X-Forwarded-For']);

        $this->assertSame('198.51.100.7', $request->getClientIP());
    }

    public function testClientIpIgnoresSpoofedLeftmostEntry(): void
    {
        $request = $this->requestFromServer([
            'REMOTE_ADDR' => '10.0.0.2',
            'HTTP_X_FORWARDED_FOR' => '192.0.2.66, 198.51.100.7, 10.0.0.1',
        ])->setClientIpHeaders(['X-Forwarded-For']);

        $this->assertSame('198.51.100.7', $request->getClientIP());
    }

    public function testClientIpIgnoresUntrustedHeaders(): void
    {
        $request = $this->requestFromServer([
            'REMOTE_ADDR' => '203.0.113.5',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.7',
            'HTTP_CLIENT_IP' => '192.0.2.9',
        ])->setClientIpHeaders(['Client-Ip']);

        $this->assertSame('192.0.2.9', $request->getClientIP());
    }

    public function testClientIpFallsBackToRemoteAddrForInvalidHeader(): void
    {
        $request = $this->requestFromServer([
            'REMOTE_ADDR' => '203.0.113.5',
            'HTTP_X_FORWARDED_FOR' => 'not-an-ip',
        ])->setClientIpHeaders(['X-Forwarded-For']);

        $this->assertSame('203.0.113.5', $request->getClientIP());
    }

    public function testParsedBodyParamHandlesNullArrayAndObject(): void
    {
        $request = new ServerRequest();
        $this->assertNull($request->getParsedBody());
        $this->assertSame('d', $request->getParsedBodyParam('a', 'd'));

        $this->assertSame(1, $request->withParsedBody(['a' => 1])->getParsedBodyParam('a'));
        $this->assertSame(2, $request->withParsedBody((object) ['a' => 2])->getParsedBodyParam('a'));
    }

    public function testParsedBodyParamSupportsArrayAccess(): void
    {
        $request = (new ServerRequest())->withParsedBody(new \ArrayObject(['a' => 1]));

        $this->assertSame(1, $request->getParsedBodyParam('a'));
        $this->assertSame('d', $request->getParsedBodyParam('b', 'd'));
    }

    public function testWithParsedBodyRejectsScalar(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ServerRequest())->withParsedBody('x');
    }

    public function testWithUploadedFilesRejectsInvalidStructure(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ServerRequest())->withUploadedFiles(['a' => [new UploadedFile(tmpfile()), 'not a file']]);
    }

    public function testFromGlobalsKeepsUploadErrorsAndSkipsEmptyFields(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'upl');
        $files = $_FILES;
        $_FILES = [
            'ok' => ['name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => 0],
            'big' => ['name' => 'b.bin', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0],
            'none' => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0],
            'list' => [
                'name' => ['x' => ['c.txt', '']],
                'type' => ['x' => ['text/plain', '']],
                'tmp_name' => ['x' => [$tmp, '']],
                'error' => ['x' => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE]],
                'size' => ['x' => [0, 0]],
            ],
        ];

        try {
            $uploaded = $this->requestFromServer(['REQUEST_METHOD' => 'POST'])->getUploadedFiles();
        } finally {
            $_FILES = $files;
            unlink($tmp);
        }

        $this->assertSame(['ok', 'big', 'list'], array_keys($uploaded));
        $this->assertSame(UPLOAD_ERR_OK, $uploaded['ok']->getError());
        $this->assertSame(UPLOAD_ERR_INI_SIZE, $uploaded['big']->getError());
        $this->assertSame('b.bin', $uploaded['big']->getClientFilename());
        $this->assertNull($uploaded['big']->getClientMediaType());
        $this->assertCount(1, $uploaded['list']['x']);
        $this->assertSame('c.txt', $uploaded['list']['x'][0]->getClientFilename());
    }

    public function testFromGlobalsFallsBackToDefaultProtocolVersion(): void
    {
        $this->assertSame('2', $this->requestFromServer(['SERVER_PROTOCOL' => 'HTTP/2'])->getProtocolVersion());
        $this->assertSame('1.1', $this->requestFromServer(['SERVER_PROTOCOL' => 'INCLUDED'])->getProtocolVersion());
    }

    public function testFromGlobalsSkipsMalformedHeaders(): void
    {
        $request = $this->requestFromServer([
            'HTTP_X_GOOD' => 'ok',
            'HTTP_X_INJECTED' => "a\r\nSet-Cookie: x=1",
            'HTTP_X(BAD' => 'v',
        ]);

        $this->assertSame('ok', $request->getHeaderLine('X-Good'));
        $this->assertFalse($request->hasHeader('X-Injected'));
        $this->assertFalse($request->hasHeader('Set-Cookie'));
    }

    public function testAttributes(): void
    {
        $request = (new ServerRequest())->withAttribute('a', 1);

        $this->assertSame(1, $request->getAttribute('a'));
        $this->assertNull((new ServerRequest())->getAttribute('a'));
        $this->assertSame([], $request->withoutAttribute('a')->getAttributes());
    }

    public function testFromGlobalsKeepsImmutability(): void
    {
        $this->assertTrue((new ServerRequest())->fromGlobals()->getImmutable());
        $this->assertFalse((new ServerRequest())->setImmutable(false)->fromGlobals()->getImmutable());
    }

    public function testParseMultipartFormData(): void
    {
        $body = implode("\r\n", [
            '--XyZ',
            'Content-Disposition: form-data; name="name"',
            '',
            'hello',
            '--XyZ',
            'Content-Disposition: form-data; name="tab[b]"',
            '',
            'v1',
            '--XyZ',
            'Content-Disposition: form-data; name="items[]"',
            '',
            'a',
            '--XyZ',
            'Content-Disposition: form-data; name="items[]"',
            '',
            'b',
            '--XyZ',
            'Content-Disposition: form-data; name="doc"; filename="a.txt"',
            'Content-Type: text/plain',
            '',
            "line1\nContent-Test: keep me\nline2",
            '--XyZ--',
            '',
        ]);

        $result = $this->parseMultipart($body, 'multipart/form-data; boundary=XyZ');

        $this->assertSame([
            'name' => 'hello',
            'tab' => ['b' => 'v1'],
            'items' => ['a', 'b'],
        ], $result['params']);

        $this->assertCount(1, $result['files']);
        /** @var UploadedFile $file */
        $file = $result['files']['doc'];
        $this->assertSame('a.txt', $file->getClientFilename());
        $this->assertSame('text/plain', $file->getClientMediaType());
        $this->assertSame("line1\nContent-Test: keep me\nline2", (string) $file->getStream());
    }

    public function testParseMultipartFormDataKeepsBoundaryLikeContent(): void
    {
        $body = "--X\r\nContent-Disposition: form-data; name=\"a\"\r\n\r\nfoo--Xbar\r\n--X--\r\n";

        $result = $this->parseMultipart($body, 'multipart/form-data; boundary=X');

        $this->assertSame(['a' => 'foo--Xbar'], $result['params']);
    }

    public function testParseMultipartFormDataSupportsNestedNames(): void
    {
        $part = static fn (string $name, string $value, string $extra = ''): string => "--X\r\nContent-Disposition: form-data; name=\"$name\"$extra\r\n\r\n$value\r\n";
        $body = $part('a[b][c]', '1')
            .$part('a[b][d][]', '2')
            .$part('a[b][d][]', '3')
            .$part('f[x][]', 'data', '; filename="x.txt"')
            .'--X--';

        $result = $this->parseMultipart($body, 'multipart/form-data; boundary=X');

        $this->assertSame(['a' => ['b' => ['c' => '1', 'd' => ['2', '3']]]], $result['params']);
        $this->assertSame('x.txt', $result['files']['f']['x'][0]->getClientFilename());
    }

    public function testParseMultipartFormDataSupportsQuotedBoundary(): void
    {
        $body = "--a b\r\nContent-Disposition: form-data; name=\"x\"\r\n\r\n1\r\n--a b--\r\n";

        $result = $this->parseMultipart($body, 'multipart/form-data; boundary="a b"');

        $this->assertSame(['x' => '1'], $result['params']);
    }

    public function testClientIpParsesHeaderFormats(): void
    {
        $cases = [
            'Forwarded' => ['for=192.0.2.60;proto=http;by=203.0.113.43', '192.0.2.60'],
            'Forwarded with IPv6 and port' => ['for="[2001:db8:cafe::17]:4711"', '2001:db8:cafe::17'],
            'Forwarded skips unknown' => ['for=unknown, for=198.51.100.17', '198.51.100.17'],
            'X-Forwarded-For IPv4 with port' => ['198.51.100.7:8080', '198.51.100.7'],
            'X-Forwarded-For IPv6 with port' => ['[2001:db8::1]:443', '2001:db8::1'],
        ];

        foreach ($cases as $case => [$value, $expected]) {
            $header = str_starts_with($case, 'Forwarded') ? 'Forwarded' : 'X-Forwarded-For';
            $request = (new ServerRequest())->withHeader($header, $value)->setClientIpHeaders([$header]);

            $this->assertSame($expected, $request->getClientIP(), $case);
        }
    }

    public function testParseMultipartFormDataSupportsBareLineFeeds(): void
    {
        $body = "--X\nContent-Disposition: form-data; name=\"a\"\n\n1\n--X\nContent-Disposition: form-data; name=\"b\"\n\nx\r\ny\n--X--\n";

        $result = $this->parseMultipart($body, 'multipart/form-data; boundary=X');

        $this->assertSame(['a' => '1', 'b' => "x\r\ny"], $result['params']);
    }

    public function testParseMultipartFormDataRespectsMaxInputVars(): void
    {
        $max = (int) ini_get('max_input_vars');
        if ($max <= 0 || $max > 10000) {
            $this->markTestSkipped('max_input_vars is not limited to a testable value');
        }

        $body = '';
        for ($i = 0; $i <= $max; ++$i) {
            $body .= "--X\r\nContent-Disposition: form-data; name=\"p$i\"\r\n\r\nv\r\n";
        }
        $body .= '--X--';

        $result = $this->parseMultipart($body, 'multipart/form-data; boundary=X');

        $this->assertCount($max, $result['params']);
    }

    public function testParseMultipartFormDataRespectsFileLimits(): void
    {
        $maxFiles = (int) ini_get('max_file_uploads');
        if ($maxFiles <= 0 || $maxFiles > 1000) {
            $this->markTestSkipped('max_file_uploads is not limited to a testable value');
        }

        $body = '';
        for ($i = 0; $i <= $maxFiles; ++$i) {
            $body .= "--X\r\nContent-Disposition: form-data; name=\"f$i\"; filename=\"f$i.txt\"\r\n\r\nv\r\n";
        }
        $body .= '--X--';

        $result = $this->parseMultipart($body, 'multipart/form-data; boundary=X');

        $this->assertCount($maxFiles, $result['files']);
    }

    public function testParseMultipartFormDataMarksOversizedFiles(): void
    {
        $value = trim((string) ini_get('upload_max_filesize'));
        $maxSize = (int) $value * (['k' => 1024, 'm' => 1048576, 'g' => 1073741824][strtolower(substr($value, -1))] ?? 1);
        if ($maxSize <= 0 || $maxSize > 16 * 1048576) {
            $this->markTestSkipped('upload_max_filesize is not limited to a testable value');
        }

        $body = "--X\r\nContent-Disposition: form-data; name=\"f\"; filename=\"big.bin\"\r\n\r\n"
            .str_repeat('a', $maxSize + 1)."\r\n--X--";

        $file = $this->parseMultipart($body, 'multipart/form-data; boundary=X')['files']['f'];

        $this->assertSame(UPLOAD_ERR_INI_SIZE, $file->getError());
        $this->assertSame('big.bin', $file->getClientFilename());
    }

    public function testParseMultipartFormDataNamesMatchPhp(): void
    {
        $names = ['a.b c', 'a[b', 'a.x[b', '[a]', '', ' lead', 'a[b]c', 'x[y][]', 'x[y][]', 'p[q][r]'];

        $body = '';
        $query = [];
        foreach ($names as $i => $name) {
            $body .= "--X\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\nv$i\r\n";
            $query[] = rawurlencode($name).'=v'.$i;
        }
        $body .= '--X--';
        parse_str(implode('&', $query), $expected);

        $this->assertSame($expected, $this->parseMultipart($body, 'multipart/form-data; boundary=X')['params']);
    }

    public function testParseMultipartFormDataDecodesExtendedFilename(): void
    {
        $body = "--X\r\nContent-Disposition: form-data; name=\"f\"; filename=\"fallback.txt\"; filename*=UTF-8''%D1%84%D0%B0%D0%B9%D0%BB.txt\r\n\r\ndata\r\n--X--";

        $result = $this->parseMultipart($body, 'multipart/form-data; boundary=X');

        $this->assertSame([], $result['params']);
        $this->assertSame('файл.txt', $result['files']['f']->getClientFilename());
    }

    public function testParseMultipartFormDataRequiresBoundary(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->parseMultipart('', 'multipart/form-data');
    }

    private function parseMultipart(string $body, string $contentType): array
    {
        $method = new \ReflectionMethod(ServerRequest::class, 'parseMultipartFormData');

        return $method->invoke(new ServerRequest(), $body, $contentType);
    }
}
