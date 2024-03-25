<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\PersonalData\Events;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('profile.personal_data_removed')]
final class PersonalDataRemoved
{
}
