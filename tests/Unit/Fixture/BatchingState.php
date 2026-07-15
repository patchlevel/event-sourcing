<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Message\Message;

final class BatchingState
{
    /** @var list<Message> */
    public array $messages = [];
}
