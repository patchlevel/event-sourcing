<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\Subscriber\IncompleteBatchMethods;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(IncompleteBatchMethods::class)]
final class IncompleteBatchMethodsTest extends TestCase
{
    public function testMissingFlushMethod(): void
    {
        $exception = IncompleteBatchMethods::missingFlushMethod(Profile::class);

        self::assertSame(
            sprintf('The subscriber "%s" uses batching but does not define a method marked with the #[BatchFlush] attribute.', Profile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
