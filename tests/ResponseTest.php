<?php

namespace HemiFrame\Tests;

use HemiFrame\Lib\Http\Message\Response;

class ResponseTest extends TestCase
{
    public function testWithStatusUsesDefaultReasonPhrase(): void
    {
        $response = (new Response())->withStatus(404);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Not Found', $response->getReasonPhrase());
    }

    public function testWithStatusKeepsCustomReasonPhrase(): void
    {
        $this->assertSame('Custom', (new Response())->withStatus(200, 'Custom')->getReasonPhrase());
    }

    public function testWithStatusDoesNotModifyOriginal(): void
    {
        $original = new Response();
        $original->withStatus(500);

        $this->assertSame(200, $original->getStatusCode());
    }

    public function testWithStatusRejectsInvalidCode(): void
    {
        foreach ([99, 600] as $code) {
            $this->assertInvalidArgument(fn () => (new Response())->withStatus($code), (string) $code);
        }
    }

    public function testConstructorRejectsInvalidCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Response(42);
    }

    public function testSendOutputsBody(): void
    {
        $this->expectOutputString('body');

        (new Response(200, '', ['X-A' => ['1', '2']], 'body'))->send();
    }

    public function testSendThrowsWhenHeadersAreAlreadySent(): void
    {
        // Runs in a separate process because the test runner buffers output, so headers are never sent here.
        $script = 'require '.var_export(dirname(__DIR__).'/vendor/autoload.php', true).';'
            .'echo "x"; flush();'
            .'try { (new HemiFrame\Lib\Http\Message\Response())->send(); } catch (RuntimeException $e) { echo get_class($e); }';

        $output = shell_exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($script).' 2>&1');

        $this->assertSame('xRuntimeException', $output);
    }

    public function testConstructorUsesDefaultReasonPhrase(): void
    {
        $this->assertSame('OK', (new Response())->getReasonPhrase());
        $this->assertSame('Not Found', (new Response(404))->getReasonPhrase());
        $this->assertSame('', (new Response(299))->getReasonPhrase());
        $this->assertSame('Custom', (new Response(404, 'Custom'))->getReasonPhrase());
        $this->assertSame('Permanent Redirect', (new Response(308))->getReasonPhrase());
        $this->assertSame('Too Early', (new Response(425))->getReasonPhrase());
    }

    public function testWithStatusRejectsReasonPhraseWithLineBreak(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Response())->withStatus(200, "OK\r\nX-Injected: 1");
    }
}
