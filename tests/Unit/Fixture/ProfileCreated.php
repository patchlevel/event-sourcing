<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

final class ProfileCreated extends AggregateChanged
{
    public static function raise(
        ProfileId $id,
        Email $email
    ): self {
        return self::occur(
            $id->toString(),
            [
                'profileId' => $id->toString(),
                'email' => $email->toString(),
            ]
        );
    }

    public function profileId(): ProfileId
    {
        return ProfileId::fromString($this->aggregateId);
    }

    public function email(): Email
    {
        return Email::fromString($this->payload['email']);
    }
}
