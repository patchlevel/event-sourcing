<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription;

use Patchlevel\EventSourcing\Identifier\CustomId;
use Patchlevel\EventSourcing\Subscription\ThrowableToErrorContextTransformer;
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
        self::assertNotFalse($resource);

        $result = ThrowableToErrorContextTransformer::transform(
            $this->createException(
                'test',
                new CustomId('test'),
                $resource,
                ['test' => [1, 2, 3]],
                static fn () => null,
            ),
        );
        fclose($resource);

        self::assertCount(1, $result);
        $error = $result[0];

        self::assertSame(RuntimeException::class, $error['class']);
        self::assertSame('test', $error['message']);
        self::assertSame(0, $error['code']);
        self::assertSame(__FILE__, $error['file']);
        self::assertGreaterThan(0, count($error['trace']));
        self::assertArrayHasKey(0, $error['trace']);

        $firstTrace = $error['trace'][0];

        self::assertArrayHasKey('file', $firstTrace);
        self::assertSame(__FILE__, $firstTrace['file'] ?? null);
        self::assertArrayHasKey('line', $firstTrace);
        self::assertSame('createException', $firstTrace['function'] ?? null);
        self::assertArrayHasKey('class', $firstTrace);
        self::assertSame(self::class, $firstTrace['class'] ?? null);
        self::assertArrayHasKey('type', $firstTrace);
        self::assertSame('->', $firstTrace['type'] ?? null);
        self::assertArrayHasKey('args', $firstTrace);
        self::assertSame([
            ['string', 'test'],
            ['object', 'Patchlevel\EventSourcing\Identifier\CustomId'],
            ['resource', 'stream'],
            [
                'array',
                [
                    'test' => [
                        'array',
                        [
                            ['integer', 1],
                            ['integer', 2],
                            ['integer', 3],
                        ],
                    ],
                ],
            ],
            ['object', 'Closure'],
        ], $firstTrace['args'] ?? null);
    }

    /**
     * @param resource                $resource
     * @param array<array-key, mixed> $array
     */
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
