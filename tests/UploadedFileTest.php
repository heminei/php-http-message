<?php

namespace HemiFrame\Tests;

use HemiFrame\Lib\Http\Message\UploadedFile;
use PHPUnit\Framework\TestCase;

class UploadedFileTest extends TestCase
{
    public function testExposesClientMetadata(): void
    {
        $resource = tmpfile();
        fwrite($resource, 'content');

        $file = new UploadedFile($resource, 'a.txt', 'text/plain');

        $this->assertSame('a.txt', $file->getClientFilename());
        $this->assertSame('text/plain', $file->getClientMediaType());
        $this->assertSame(7, $file->getSize());
        $this->assertSame(UPLOAD_ERR_OK, $file->getError());
    }

    public function testMoveToWritesContent(): void
    {
        $resource = tmpfile();
        fwrite($resource, 'content');
        $target = tempnam(sys_get_temp_dir(), 'upl');

        try {
            (new UploadedFile($resource, 'a.txt', 'text/plain'))->moveTo($target);

            $this->assertSame('content', file_get_contents($target));
        } finally {
            unlink($target);
        }
    }

    public function testMoveToRejectsEmptyPath(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new UploadedFile(tmpfile()))->moveTo('');
    }

    public function testRejectsInvalidSource(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // @phpstan-ignore argument.type (invalid input is the subject of this test)
        new UploadedFile(123);
    }

    public function testClientMetadataIsNullWhenNotProvided(): void
    {
        $file = new UploadedFile(fopen('php://memory', 'r+'));

        $this->assertNull($file->getClientFilename());
        $this->assertNull($file->getClientMediaType());
    }

    public function testMoveToMovesFileFromPath(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($source, 'content');
        $target = $source.'.moved';

        try {
            $file = new UploadedFile($source, 'a.txt', 'text/plain');
            $this->assertSame(7, $file->getSize());

            $file->moveTo($target);

            $this->assertFileDoesNotExist($source);
            $this->assertSame('content', file_get_contents($target));
        } finally {
            @unlink($source);
            @unlink($target);
        }
    }

    public function testGetStreamReadsFileFromPath(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($source, 'content');

        try {
            $this->assertSame('content', (string) (new UploadedFile($source))->getStream());
        } finally {
            unlink($source);
        }
    }

    public function testCannotMoveTwice(): void
    {
        $resource = tmpfile();
        fwrite($resource, 'x');
        $file = new UploadedFile($resource);
        $target = tempnam(sys_get_temp_dir(), 'upl');

        try {
            $file->moveTo($target);

            $this->expectException(\RuntimeException::class);
            $file->moveTo($target);
        } finally {
            unlink($target);
        }
    }

    public function testGetStreamThrowsAfterMove(): void
    {
        $resource = tmpfile();
        $file = new UploadedFile($resource);
        $target = tempnam(sys_get_temp_dir(), 'upl');

        try {
            $file->moveTo($target);

            $this->expectException(\RuntimeException::class);
            $file->getStream();
        } finally {
            unlink($target);
        }
    }

    public function testUploadErrorPreventsMoveAndStream(): void
    {
        $file = new UploadedFile('', 'a.txt', null, UPLOAD_ERR_NO_FILE);

        $this->assertSame(UPLOAD_ERR_NO_FILE, $file->getError());
        $this->assertNull($file->getSize());

        try {
            $file->getStream();
            $this->fail('getStream() must throw on upload error');
        } catch (\RuntimeException) {
        }

        $this->expectException(\RuntimeException::class);
        $file->moveTo(sys_get_temp_dir().'/never-written');
    }

    public function testToStringReturnsTargetPathAfterMove(): void
    {
        $resource = tmpfile();
        fwrite($resource, 'x');
        $file = new UploadedFile($resource);
        $target = tempnam(sys_get_temp_dir(), 'upl');

        try {
            $file->moveTo($target);

            $this->assertSame($target, (string) $file);
        } finally {
            unlink($target);
        }
    }

    public function testMoveToRejectsDirectory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not a directory');

        (new UploadedFile(tmpfile()))->moveTo(sys_get_temp_dir());
    }

    public function testRejectsInvalidErrorStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UploadedFile(tmpfile(), null, null, 99);
    }
}
