<?php

namespace HemiFrame\Tests;

use HemiFrame\Lib\Http\Message\Stream;
use PHPUnit\Framework\TestCase;

class StreamTest extends TestCase
{
    public function testWriteAndReadBack(): void
    {
        $stream = new Stream();
        $stream->write('hello');

        $this->assertSame(5, $stream->getSize());
        $this->assertSame('hello', (string) $stream);

        $stream->rewind();
        $this->assertSame('he', $stream->read(2));
        $this->assertSame('llo', $stream->getContents());
        $this->assertTrue($stream->eof());
    }

    public function testDetachedStreamThrows(): void
    {
        $stream = new Stream();
        $stream->detach();

        $this->expectException(\RuntimeException::class);

        $stream->getContents();
    }

    public function testRejectsNonResource(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Stream('not a resource');
    }

    public function testGetContentsThrowsOnWriteOnlyStream(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'str');
        $stream = new Stream(fopen($path, 'w'));

        try {
            $this->expectException(\RuntimeException::class);
            $stream->getContents();
        } finally {
            $stream->close();
            unlink($path);
        }
    }

    public function testToStringDoesNotThrowOnWriteOnlyStream(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'str');
        $stream = new Stream(fopen($path, 'w'));

        try {
            $this->assertSame('', (string) $stream);
        } finally {
            $stream->close();
            unlink($path);
        }
    }

    public function testToStringReadsNonSeekableStream(): void
    {
        $stream = new Stream(popen('echo hi', 'r'));

        $this->assertFalse($stream->isSeekable());
        $this->assertSame("hi\n", (string) $stream);
    }

    public function testDetectsReadableAndWritableModes(): void
    {
        $modes = [
            'r' => [true, false],
            'rb' => [true, false],
            'r+' => [true, true],
            'rb+' => [true, true],
            'r+b' => [true, true],
            'w' => [false, true],
            'wb+' => [true, true],
            'a' => [false, true],
            'ab' => [false, true],
            'a+b' => [true, true],
            'c' => [false, true],
            'cb+' => [true, true],
        ];

        $path = tempnam(sys_get_temp_dir(), 'str');
        try {
            foreach ($modes as $mode => [$readable, $writable]) {
                $stream = new Stream(fopen($path, (string) $mode));
                $this->assertSame($readable, $stream->isReadable(), $mode.' readable');
                $this->assertSame($writable, $stream->isWritable(), $mode.' writable');
                $stream->close();
            }
        } finally {
            unlink($path);
        }
    }
}
