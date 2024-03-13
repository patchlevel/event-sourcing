<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber;

use Closure;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\RunMode;

interface SubscriberAccessor
{
    public function id(): string;

    public function group(): string;

    public function runMode(): RunMode;

    public function setupMethod(): Closure|null;

    public function teardownMethod(): Closure|null;

    /**
     * @param class-string $eventClass
     *
     * @return list<Closure(Message):void>
     */
    public function subscribeMethods(string $eventClass): array;
}
