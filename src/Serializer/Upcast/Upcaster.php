<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Upcast;

interface Upcaster
{
    public function __invoke(Upcast $upcast): Upcast;
}
