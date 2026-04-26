<?php

declare(strict_types=1);

use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\LegacyWrapperSubscriptionEngine;

require __DIR__ . '/../vendor/autoload.php';

class_alias(LegacyWrapperSubscriptionEngine::class, DefaultSubscriptionEngine::class);
