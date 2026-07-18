<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Dbal;

use Doctrine\DBAL\Connection;

interface ProvideDbalConnection
{
    public function connection(): Connection;
}
