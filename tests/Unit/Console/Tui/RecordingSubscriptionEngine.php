<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Tui;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\Subscription;

final class RecordingSubscriptionEngine implements SubscriptionEngine
{
    /** @var list<Command> */
    public array $commands = [];

    /** @var list<SubscriptionEngineCriteria|null> */
    public array $criteria = [];

    /** @param list<Subscription> $subscriptions */
    public function __construct(
        public array $subscriptions = [],
        public Result $result = new Result(),
    ) {
    }

    public function execute(Command $command): Result
    {
        $this->commands[] = $command;

        return $this->result;
    }

    /** @return list<Subscription> */
    public function subscriptions(SubscriptionEngineCriteria|null $criteria = null): array
    {
        $this->criteria[] = $criteria;

        return $this->subscriptions;
    }
}
