<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription;

enum SubscriptionType: string
{
    case Main = 'main';
    case Partition = 'partition';
}
