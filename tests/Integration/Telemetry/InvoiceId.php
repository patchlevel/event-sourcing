<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Telemetry;

use Patchlevel\EventSourcing\Identifier\Identifier;
use Patchlevel\EventSourcing\Identifier\RamseyUuidV7Behaviour;

final class InvoiceId implements Identifier
{
    use RamseyUuidV7Behaviour;
}
