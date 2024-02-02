<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projection;

use Patchlevel\EventSourcing\Aggregate\CustomId;
use Patchlevel\EventSourcing\Projection\Projection\Store\ErrorContext;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function count;
use function fclose;
use function fopen;

final class ErrorContextTest extends TestCase
{
    public function testErrorContext(): void
    {
        $resource = fopen('php://memory', 'r');
        $result = ErrorContext::fromThrowable(
            $this->createException(
                'test',
                new CustomId('test'),
                $resource,
                ['test' => [1, 2, 3]],
                static fn () => null,
            ),
        );
        fclose($resource);

        $this->assertCount(1, $result);
        $error = $result[0];

        $this->assertSame(RuntimeException::class, $error['class']);
        $this->assertSame('test', $error['message']);
        $this->assertSame(0, $error['code']);
        $this->assertSame(__FILE__, $error['file']);
        $this->assertGreaterThan(0, count($error['trace']));
        $this->assertArrayHasKey(0, $error['trace']);

        $firstTrace = $error['trace'][0];

        $this->assertArrayHasKey('file', $firstTrace);
        $this->assertSame(__FILE__, $firstTrace['file'] ?? null);
        $this->assertArrayHasKey('line', $firstTrace);
        $this->assertSame('createException', $firstTrace['function'] ?? null);
        $this->assertArrayHasKey('class', $firstTrace);
        $this->assertSame(self::class, $firstTrace['class'] ?? null);
        $this->assertArrayHasKey('type', $firstTrace);
        $this->assertSame('->', $firstTrace['type'] ?? null);
        $this->assertArrayHasKey('args', $firstTrace);
        $this->assertSame([
            'test',
            'object(Patchlevel\EventSourcing\Aggregate\CustomId)',
            'resource(stream)',
            ['test' => [1, 2, 3]],
            'object(Closure)',
        ], $firstTrace['args'] ?? null);
    }

    /** @param resource $resource */
    private function createException(
        string $message,
        CustomId $id,
        $resource,
        array $array,
        callable $callable,
    ): RuntimeException {
        return new RuntimeException($message);
    }
}
