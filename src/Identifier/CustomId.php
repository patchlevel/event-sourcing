<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Identifier;

final class CustomId implements Identifier
{
    use CustomIdBehaviour;
}
