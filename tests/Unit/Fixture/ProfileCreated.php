<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

/**
 * @template-extends AggregateChanged<array{profileId: string, email: string}>
 */
class ProfileCreated extends AggregateChanged
{
    public static function raise(
        ProfileId $id,
        Email $email
    ): self {
        return new self(
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
