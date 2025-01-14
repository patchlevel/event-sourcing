<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

final class StoreIsReadOnly extends StoreException
{
    public function __construct()
    {
        parent::__construct('Store is in read only mode');
    }
}
