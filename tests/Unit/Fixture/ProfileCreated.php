<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Normalize;

class ProfileCreated
{
    public function __construct(
        #[Normalize(ProfileIdNormalizer::class)]
        public ProfileId $profileId,
        #[Normalize(EmailNormalizer::class)]
        public Email $email
    ) {
    }
}
