<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\Subscriber\ClassIsNotASubscriber;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(ClassIsNotASubscriber::class)]
final class ClassIsNotASubscriberTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new ClassIsNotASubscriber(Profile::class);

        self::assertSame(
            sprintf('Class "%s" is not a subscriber', Profile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
