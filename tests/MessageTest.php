<?php

namespace HemiFrame\Tests;

use HemiFrame\Lib\Http\Message\Message;
use Psr\Http\Message\StreamInterface;

class MessageTest extends TestCase
{
    public function testWithHeaderReplacesHeaderCaseInsensitively(): void
    {
        $message = (new Message())->withHeader('X-A', '1')->withHeader('x-a', '2');

        $this->assertSame(['x-a' => ['2']], $message->getHeaders());
        $this->assertSame('2', $message->getHeaderLine('X-A'));
    }

    public function testWithHeaderKeepsHeaderPosition(): void
    {
        $message = (new Message(['A' => '1', 'B' => '2', 'C' => '3']))->withHeader('b', 'x');

        $this->assertSame(['A' => ['1'], 'b' => ['x'], 'C' => ['3']], $message->getHeaders());
    }

    public function testWithoutHeaderRemovesHeaderCaseInsensitively(): void
    {
        $message = (new Message())->withHeader('X-A', '1')->withoutHeader('x-a');

        $this->assertFalse($message->hasHeader('X-A'));
        $this->assertSame([], $message->getHeaders());
    }

    public function testWithAddedHeaderAppendsToExistingHeader(): void
    {
        $message = (new Message())->withHeader('X-A', '1')->withAddedHeader('x-a', '2');

        $this->assertSame(['1', '2'], $message->getHeader('X-A'));
        $this->assertSame('1, 2', $message->getHeaderLine('X-A'));
    }

    public function testWithAddedHeaderCreatesMissingHeader(): void
    {
        $message = (new Message())->withAddedHeader('X-B', ['1', '2']);

        $this->assertSame(['1', '2'], $message->getHeader('X-B'));
    }

    public function testImmutableByDefault(): void
    {
        $original = new Message();
        $changed = $original->withHeader('X-A', '1')->withProtocolVersion('2.0');

        $this->assertNotSame($original, $changed);
        $this->assertFalse($original->hasHeader('X-A'));
        $this->assertSame('1.1', $original->getProtocolVersion());
    }

    public function testMutableWhenImmutabilityIsDisabled(): void
    {
        $message = (new Message())->setImmutable(false);

        $this->assertSame($message, $message->withHeader('X-A', '1'));
        $this->assertTrue($message->hasHeader('X-A'));
    }

    public function testConstructorNormalizesHeaders(): void
    {
        $message = new Message(['X-A' => 'v', 'x-a' => ['w'], 'X-N' => 5]);

        $this->assertSame(['v', 'w'], $message->getHeader('X-A'));
        $this->assertSame(['5'], $message->getHeader('X-N'));
    }

    public function testHeaderValuesAreTrimmed(): void
    {
        $this->assertSame(['v'], (new Message())->withHeader('X-A', " v\t")->getHeader('X-A'));
    }

    public function testRejectsInvalidHeaders(): void
    {
        $cases = [
            'name with CRLF' => ["Bad\r\nName", 'x'],
            'name with space' => ['Bad Name', 'x'],
            'empty name' => ['', 'x'],
            'value with CRLF' => ['X-A', "a\r\nInjected: 1"],
            'value with NUL' => ['X-A', "a\0b"],
            'empty array value' => ['X-A', []],
            'non-scalar value' => ['X-A', [['nested']]],
        ];

        foreach ($cases as $case => [$name, $value]) {
            $this->assertInvalidArgument(fn () => (new Message())->withHeader($name, $value), 'withHeader: '.$case);
            $this->assertInvalidArgument(fn () => (new Message())->withAddedHeader($name, $value), 'withAddedHeader: '.$case);
        }
    }

    public function testConstructorRejectsInvalidHeader(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Message(['X-A' => "a\nb"]);
    }

    public function testConstructorRejectsListOfHeaderLines(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Message(['Content-Type: text/html']);
    }

    public function testConstructorAcceptsAnyStreamImplementation(): void
    {
        $body = $this->createStub(StreamInterface::class);

        $this->assertSame($body, (new Message([], $body))->getBody());
    }

    public function testConstructorWritesStringBody(): void
    {
        $this->assertSame('hello', (string) (new Message([], 'hello'))->getBody());
    }

    public function testConstructorRewindsStringBody(): void
    {
        $this->assertSame('hello', (new Message([], 'hello'))->getBody()->getContents());
    }

    public function testWithProtocolVersionAcceptsValidVersions(): void
    {
        $this->assertSame('2', (new Message())->withProtocolVersion('2')->getProtocolVersion());
        $this->assertSame('1.0', (new Message([], null, '1.0'))->getProtocolVersion());
    }

    public function testWithProtocolVersionRejectsInvalidVersion(): void
    {
        foreach (["1.1\r\nX: y", 'HTTP/1.1', '1.'] as $version) {
            $this->assertInvalidArgument(fn () => (new Message())->withProtocolVersion($version), json_encode($version));
        }
    }
}
