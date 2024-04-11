<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('email_changed')]
final class EmailChanged
{
    public function __construct(
        public string $id,
        public string $email,
    ) {
    }
}
