<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Event\AliasFixture;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('event_with_alias', aliases: ['event_alias'])]
final class EventWithAlias
{
}
