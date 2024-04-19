<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream;

use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\EventSourcing\Aggregate\RamseyUuidV7Behaviour;

final class AccountId implements AggregateRootId
{
    use RamseyUuidV7Behaviour;
}
