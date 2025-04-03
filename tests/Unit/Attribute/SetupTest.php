<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use Patchlevel\EventSourcing\Attribute\Setup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

#[CoversClass(Setup::class)]
final class SetupTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function testInstantiate(): void
    {
        $attribute = new Setup();
    }
}
