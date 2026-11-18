<?php

namespace HemiFrame\Tests;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * Data-provider-free check so the suite runs on every supported PHPUnit version.
     */
    protected function assertInvalidArgument(callable $callback, string $case = ''): void
    {
        try {
            $callback();
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('Expected InvalidArgumentException'.('' !== $case ? ' for '.$case : ''));
    }
}
