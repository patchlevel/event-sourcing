<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('wrong_normalizer')]
final class WrongNormalizerEvent
{
    public function __construct(
        #[EmailNormalizer]
        public bool $email
    ) {
    }
}
