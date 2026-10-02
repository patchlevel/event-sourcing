<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Message\Fixture;

use Patchlevel\EventSourcing\Attribute\Header;

#[Header('streamName')]
final class ReservedNameHeader
{
}
