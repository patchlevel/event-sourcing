<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Event\DuplicateAliasFixture;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('aliased_event_2', aliases: ['duplicate_alias'])]
final class AliasedEvent2
{
}
