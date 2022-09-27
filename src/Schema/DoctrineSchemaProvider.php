<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Schema;

use Doctrine\DBAL\Schema\Schema;

interface DoctrineSchemaProvider
{
    public function schema(): Schema;
}
