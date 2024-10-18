<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Source;

use Patchlevel\EventSourcing\Message\Message;

interface Source
{
    /** @return iterable<Message> */
    public function load(): iterable;
}