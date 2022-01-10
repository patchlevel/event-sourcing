<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

/**
 * @template-extends AggregateChanged<array{profileId: string, email: string}>
 */
class ProfileCreatedWithCustomRecordedOn extends AggregateChanged
{
    public static function raise(
        ProfileId $id,
        Email $email
    ): static {
        return new static(
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

    protected function createRecordDate(): DateTimeImmutable
    {
        return new DateTimeImmutable('1.1.2022 10:00:00');
    }
}
