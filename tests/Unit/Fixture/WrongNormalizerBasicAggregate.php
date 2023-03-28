<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;

#[Aggregate('wrong_normalizer')]
final class WrongNormalizerBasicAggregate extends BasicAggregateRoot
{
    #[EmailNormalizer]
    public bool $email = true;

    public function aggregateRootId(): string
    {
        return '1';
    }
}
