<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber;

final class BatchProfileState
{
    /** @var list<string> */
    public array $insertedIds = [];
}
