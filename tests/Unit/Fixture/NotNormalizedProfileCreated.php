<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

class NotNormalizedProfileCreated
{
    public function __construct(
        public ProfileId $profileId,
        public Email $email
    ) {
    }
}
