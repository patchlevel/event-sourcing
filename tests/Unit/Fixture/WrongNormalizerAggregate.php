<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;

#[Aggregate('wrong_normalizer')]
final class WrongNormalizerAggregate extends AggregateRoot
{
    #[EmailNormalizer]
    public bool $email = true;

    public function aggregateRootId(): string
    {
        return '1';
    }
}
