<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

/**
 * @template-extends AggregateChanged<array{profileId: ProfileId, email: Email}>
 */
class NotNormalizedProfileCreated extends AggregateChanged
{
    public static function raise(
        ProfileId $id,
        Email $email
    ): static {
        return new static(
            [
                'profileId' => $id,
                'email' => $email,
            ]
        );
    }

    public function profileId(): ProfileId
    {
        return $this->payload['profileId'];
    }

    public function email(): Email
    {
        return $this->payload['email'];
    }
}
