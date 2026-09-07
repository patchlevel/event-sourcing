<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\Subscriber\MixedTeardownAndCleanupMethods;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(MixedTeardownAndCleanupMethods::class)]
final class MixedTeardownAndCleanupMethodsTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new MixedTeardownAndCleanupMethods(Profile::class, 'teardownA', 'cleanupB');

        self::assertSame(
            sprintf('The subscriber "%s" has a "teardown" method "%s" and a "cleanup" method "%s". Only one of them can be defined.', Profile::class, 'teardownA', 'cleanupB'),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
