<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

final class WrongQueryResult extends StoreException
{
    public function __construct()
    {
        parent::__construct('the type of the query result is wrong');
    }
}
