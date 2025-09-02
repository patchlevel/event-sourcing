<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Identifier;

final class Uuid implements Identifier
{
    use RamseyUuidV7Behaviour;
}
