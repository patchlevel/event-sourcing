<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use JsonSerializable;
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Serializer\Normalizer\IdNormalizer;

#[Event('profile_created')]
final class ProfileCreated implements JsonSerializable
{
    public function __construct(
        #[IdNormalizer]
        public ProfileId $profileId,
        #[EmailNormalizer]
        public Email $email,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'profileId' => $this->profileId->toString(),
            'email' => $this->email->toString(),
        ];
    }
}
