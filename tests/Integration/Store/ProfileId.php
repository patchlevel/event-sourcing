<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Store;

use Patchlevel\EventSourcing\Identifier\Identifier;
use Patchlevel\EventSourcing\Identifier\RamseyUuidV7Behaviour;

final class ProfileId implements Identifier
{
    use RamseyUuidV7Behaviour;
}
