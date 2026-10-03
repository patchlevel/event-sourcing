<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Subscriber;

use Patchlevel\EventSourcing\Subscription\Subscriber\NoSuitableResolver;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(NoSuitableResolver::class)]
final class NoSuitableResolverTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new NoSuitableResolver(Profile::class, 'onProfileCreated', 'event');

        self::assertSame(
            sprintf('No suitable resolver found for argument "event" in method "onProfileCreated" of class "%s"', Profile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
