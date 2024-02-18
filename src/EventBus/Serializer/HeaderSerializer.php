<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus\Serializer;

use Patchlevel\EventSourcing\EventBus\Header;

interface HeaderSerializer
{
    public function serialize(Header $header): string;

    public function deserialize(string $content): Header;
}
