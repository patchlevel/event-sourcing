<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\AutoInitialize;
use Patchlevel\EventSourcing\Attribute\Id;
use stdClass;

#[Aggregate('broken_auto_initializable_profile')]
final class BrokenAutoInitializableProfile extends BasicAggregateRoot
{
    #[Id]
    private ProfileId $id;

    #[AutoInitialize]
    public static function initialize(ProfileId $id): object
    {
        return new stdClass();
    }
}
