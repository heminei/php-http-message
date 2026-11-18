<?php

namespace HemiFrame\Tests;

use HemiFrame\Lib\Http\Message\Uri;

class UriTest extends TestCase
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

    public function testParsesAndBuildsUri(): void
    {
        $uri = new Uri('https://user:pass@example.com:8443/path?a=1#frag');

        $this->assertSame('https', $uri->getScheme());
        $this->assertSame('user:pass', $uri->getUserInfo());
        $this->assertSame('example.com', $uri->getHost());
        $this->assertSame(8443, $uri->getPort());
        $this->assertSame('/path', $uri->getPath());
        $this->assertSame('a=1', $uri->getQuery());
        $this->assertSame('frag', $uri->getFragment());
        $this->assertSame('user:pass@example.com:8443', $uri->getAuthority());
        $this->assertSame('https://user:pass@example.com:8443/path?a=1#frag', (string) $uri);
    }

    public function testDefaultPortIsOmittedFromAuthority(): void
    {
        $this->assertSame('example.com', (new Uri('http://example.com:80/'))->getAuthority());
    }

    public function testPortWithoutKnownSchemeIsKept(): void
    {
        $uri = (new Uri())->withHost('example.com')->withPort(8080);

        $this->assertSame('example.com:8080', $uri->getAuthority());
    }

    public function testWithMethodsDoNotModifyOriginal(): void
    {
        $original = new Uri('http://example.com');
        $changed = $original->withPath('/x')->withQuery('a=1')->withFragment('f');

        $this->assertSame('', $original->getPath());
        $this->assertSame('http://example.com/x?a=1#f', (string) $changed);
    }

    public function testWithUserInfo(): void
    {
        $this->assertSame('user:secret', (new Uri())->withUserInfo('user', 'secret')->getUserInfo());
        $this->assertSame('user', (new Uri())->withUserInfo('user')->getUserInfo());
        $this->assertSame('user:0', (new Uri())->withUserInfo('user', '0')->getUserInfo());
    }

    public function testSchemeAndHostAreLowercased(): void
    {
        $uri = (new Uri())->withScheme('HTTP')->withHost('EXAMPLE.com');

        $this->assertSame('http', $uri->getScheme());
        $this->assertSame('example.com', $uri->getHost());
        $this->assertSame('https://example.com', (string) new Uri('HTTPS://Example.COM'));
    }

    public function testWithSchemeRejectsInvalidScheme(): void
    {
        foreach (['1http', 'ht tp', 'http:', 'http://'] as $scheme) {
            $this->assertInvalidArgument(static fn () => (new Uri())->withScheme($scheme), $scheme);
        }
        $this->assertSame('', (new Uri('http://a.com'))->withScheme('')->getScheme());
        $this->assertSame('svn+ssh', (new Uri())->withScheme('svn+ssh')->getScheme());
    }

    public function testWithPortRejectsOutOfRange(): void
    {
        foreach ([-1, 65536] as $port) {
            $this->assertInvalidArgument(fn () => (new Uri('http://a.com'))->withPort($port), (string) $port);
        }
    }

    public function testWithPortAcceptsNull(): void
    {
        $this->assertNull((new Uri('http://a.com:8080'))->withPort(null)->getPort());
    }

    public function testRelativePathGetsSlashWhenAuthorityIsPresent(): void
    {
        $uri = (new Uri('http://a.com'))->withPath('x');

        $this->assertSame('x', $uri->getPath());
        $this->assertSame('http://a.com/x', (string) $uri);
    }

    public function testLeadingSlashesAreReducedWithoutAuthority(): void
    {
        $this->assertSame('/x', (string) (new Uri())->withPath('//x'));
    }

    public function testComponentsArePercentEncoded(): void
    {
        $uri = (new Uri('http://a.com'))
            ->withPath('/a b')
            ->withQuery('q=a b')
            ->withFragment('f g')
            ->withUserInfo('us er', 'p@ss');

        $this->assertSame('/a%20b', $uri->getPath());
        $this->assertSame('q=a%20b', $uri->getQuery());
        $this->assertSame('f%20g', $uri->getFragment());
        $this->assertSame('us%20er:p%40ss', $uri->getUserInfo());
    }

    public function testEncodedValuesAreNotDoubleEncoded(): void
    {
        $uri = new Uri('http://a.com/a%20b?q=%2F&x=50%');

        $this->assertSame('/a%20b', $uri->getPath());
        $this->assertSame('q=%2F&x=50%25', $uri->getQuery());
    }

    public function testRejectsInvalidUri(): void
    {
        foreach (['http:///x', 'http://:80', 'http://a b/'] as $uri) {
            $this->assertInvalidArgument(fn () => new Uri($uri), $uri);
        }
    }

    public function testFromGlobalsParsesHostHeader(): void
    {
        $cases = [
            'host only' => ['example.com', 'example.com', null],
            'host and port' => ['example.com:8080', 'example.com', 8080],
            'ipv6' => ['[::1]', '[::1]', null],
            'ipv6 and port' => ['[::1]:8080', '[::1]', 8080],
            'port out of range' => ['example.com:70000', 'example.com', null],
        ];

        foreach ($cases as $case => [$hostHeader, $host, $port]) {
            $_SERVER = ['HTTP_HOST' => $hostHeader, 'REQUEST_URI' => '/p?a=1'];

            $uri = (new Uri())->fromGlobals();

            $this->assertSame($host, $uri->getHost(), $case);
            $this->assertSame($port, $uri->getPort(), $case);
            $this->assertSame('/p', $uri->getPath(), $case);
            $this->assertSame('a=1', $uri->getQuery(), $case);
        }
    }

    public function testFromGlobalsHandlesAbsoluteFormRequestUri(): void
    {
        $_SERVER = ['HTTP_HOST' => 'a.com', 'REQUEST_URI' => 'http://a.com:8080/p?q=1'];

        $uri = (new Uri())->fromGlobals();

        $this->assertSame('/p', $uri->getPath());
        $this->assertSame('q=1', $uri->getQuery());
    }

    public function testFromGlobalsFallsBackToServerNameForInvalidHostHeader(): void
    {
        $_SERVER = ['HTTP_HOST' => 'a b/c', 'SERVER_NAME' => 'example.com', 'REQUEST_URI' => '/'];

        $this->assertSame('example.com', (new Uri())->fromGlobals()->getHost());
    }

    public function testFromGlobalsUsesServerPortWithoutHostHeader(): void
    {
        $_SERVER = ['SERVER_NAME' => 'example.com', 'SERVER_PORT' => '8080', 'REQUEST_URI' => '/'];
        $this->assertSame('http://example.com:8080/', (string) (new Uri())->fromGlobals());

        $_SERVER = ['SERVER_ADDR' => '::1', 'SERVER_PORT' => '80', 'REQUEST_URI' => '/'];
        $this->assertSame('http://[::1]/', (string) (new Uri())->fromGlobals());
    }

    public function testFromGlobalsIgnoresServerPortWhenHostHeaderIsPresent(): void
    {
        $_SERVER = ['HTTP_HOST' => 'example.com', 'SERVER_PORT' => '8080', 'REQUEST_URI' => '/'];

        $this->assertNull((new Uri())->fromGlobals()->getPort());
    }

    public function testGetPortReturnsNullForDefaultPort(): void
    {
        $this->assertNull((new Uri('http://a.com:80'))->getPort());
        $this->assertNull((new Uri('https://a.com:443'))->getPort());
        $this->assertSame(443, (new Uri('http://a.com:443'))->getPort());
        $this->assertNull((new Uri('http://a.com:443'))->withScheme('https')->getPort());
    }

    public function testWithHostRejectsInvalidHost(): void
    {
        foreach (['a b', 'a/b', 'a?b', '[not-ip]'] as $host) {
            $this->assertInvalidArgument(fn () => (new Uri())->withHost($host), $host);
        }
    }

    public function testAuthorityIsEmptyWithoutHost(): void
    {
        $uri = (new Uri())->withUserInfo('u')->withPort(8080)->withPath('/p');

        $this->assertSame('', $uri->getAuthority());
        $this->assertSame('/p', (string) $uri);
    }

    public function testWithHostAcceptsInternationalizedName(): void
    {
        $this->assertSame('пример.бг', (new Uri())->withHost('пример.бг')->getHost());
        $this->assertSame('http://пример.бг/', (string) new Uri('http://пример.бг/'));
    }

    public function testWithHostAcceptsIpv6AndEmpty(): void
    {
        $this->assertSame('[2001:db8::1]', (new Uri())->withHost('[2001:DB8::1]')->getHost());
        $this->assertSame('', (new Uri('http://a.com'))->withHost('')->getHost());
    }
}
