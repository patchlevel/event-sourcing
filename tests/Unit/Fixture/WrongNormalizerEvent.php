<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\Normalize;

#[Event('wrong_normalizer')]
class WrongNormalizerEvent
{
    public function __construct(
        #[Normalize(EmailNormalizer::class)]
        public bool $email
    ) {
    }
}
