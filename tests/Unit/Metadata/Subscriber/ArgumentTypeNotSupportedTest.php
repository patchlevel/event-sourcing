<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentTypeNotSupported;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(ArgumentTypeNotSupported::class)]
final class ArgumentTypeNotSupportedTest extends TestCase
{
    public function testMissingType(): void
    {
        $exception = ArgumentTypeNotSupported::missingType(Profile::class, 'onProfileCreated', 'event');

        self::assertSame(
            sprintf('Argument type for method "onProfileCreated" in class "%s" is not supported. Argument "event" must have a type.', Profile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
