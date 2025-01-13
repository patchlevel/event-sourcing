<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Lookup;

use RuntimeException;

final class MessageNotFound extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Message not found');
    }
}
