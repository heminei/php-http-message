<?php

namespace HemiFrame\Tests;

use HemiFrame\Lib\Http\Message\Request;
use HemiFrame\Lib\Http\Message\Uri;

class RequestTest extends TestCase
{
    public function testMethodCaseIsPreserved(): void
    {
        $this->assertSame('post', (new Request('post'))->getMethod());
        $this->assertSame('PATCH', (new Request())->withMethod('PATCH')->getMethod());
    }

    public function testWithMethodRejectsInvalidMethod(): void
    {
        foreach (['', 'GE T', "GET\r\n"] as $method) {
            $this->assertInvalidArgument(fn () => (new Request())->withMethod($method), json_encode($method));
        }
    }

    public function testConstructorRejectsInvalidMethod(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Request('');
    }

    public function testRequestTargetIsBuiltFromUri(): void
    {
        $request = new Request('GET', 'http://example.com/path?a=1');

        $this->assertSame('/path?a=1', $request->getRequestTarget());
        $this->assertSame('/', (new Request())->getRequestTarget());
    }

    public function testWithRequestTargetRejectsWhitespace(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Request())->withRequestTarget('/a b');
    }

    public function testWithUriUpdatesHostHeader(): void
    {
        $request = (new Request())->withUri(new Uri('http://example.com:8080/x'));

        $this->assertSame('example.com:8080', $request->getHeaderLine('Host'));
    }

    public function testWithUriPreserveHostKeepsExistingHeader(): void
    {
        $request = (new Request('GET', '', ['Host' => ['old.example']]))
            ->withUri(new Uri('http://example.com'), true);

        $this->assertSame('old.example', $request->getHeaderLine('Host'));
    }

    public function testWithUriPreserveHostSetsMissingHeader(): void
    {
        $request = (new Request())->withUri(new Uri('http://example.com'), true);

        $this->assertSame('example.com', $request->getHeaderLine('Host'));
    }

    public function testWithUriWithoutHostKeepsExistingHeader(): void
    {
        $request = (new Request('GET', '', ['Host' => ['old.example']]))->withUri(new Uri('/only/path'));

        $this->assertSame('old.example', $request->getHeaderLine('Host'));
    }

    public function testConstructorSetsHostFromUriAsFirstHeader(): void
    {
        $request = new Request('GET', 'http://example.com:8080/x', ['Accept' => 'text/html']);

        $this->assertSame(['Host', 'Accept'], array_keys($request->getHeaders()));
        $this->assertSame('example.com:8080', $request->getHeaderLine('Host'));
    }

    public function testConstructorKeepsExplicitHostHeader(): void
    {
        $request = new Request('GET', 'http://example.com', ['host' => 'other.example']);

        $this->assertSame('other.example', $request->getHeaderLine('Host'));
    }

    public function testConstructorWithoutUriHostHasNoHostHeader(): void
    {
        $this->assertFalse((new Request('GET', '/path'))->hasHeader('Host'));
    }
}
