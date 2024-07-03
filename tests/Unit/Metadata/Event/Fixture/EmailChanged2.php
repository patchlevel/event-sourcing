<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Event\Fixture;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('email_changed')]
final class EmailChanged2
{
    public function __construct(
        public string $id,
        public string $email,
    ) {
    }
}
