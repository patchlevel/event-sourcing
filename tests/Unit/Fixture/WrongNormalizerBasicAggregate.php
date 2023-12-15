<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\AggregateId;

#[Aggregate('wrong_normalizer')]
final class WrongNormalizerBasicAggregate extends BasicAggregateRoot
{
    #[AggregateId]
    private ProfileId $id;

    #[EmailNormalizer]
    public bool $email = true;
}
