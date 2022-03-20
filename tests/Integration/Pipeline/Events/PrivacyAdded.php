<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Pipeline\Events;

use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcing\Tests\Integration\Pipeline\EventNormalizer\ProfileIdNormalizer;
use Patchlevel\EventSourcing\Tests\Integration\Pipeline\ProfileId;

final class PrivacyAdded
{
    public function __construct(
        #[Normalize(ProfileIdNormalizer::class)]
        public ProfileId $profileId
    ) {
    }
}
