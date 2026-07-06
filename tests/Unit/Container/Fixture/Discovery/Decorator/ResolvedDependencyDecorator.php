<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Container\Fixture\Discovery\Decorator;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator;
use Patchlevel\EventSourcing\Tests\Unit\Container\Fixture\Discovery\Support\DiscoveryHeader;
use Patchlevel\EventSourcing\Tests\Unit\Container\Fixture\Discovery\Support\TaggedDependency;

final class ResolvedDependencyDecorator implements MessageDecorator
{
    public function __construct(
        private readonly TaggedDependency $dependency,
    ) {
    }

    public function __invoke(Message $message): Message
    {
        return $message->withHeader(new DiscoveryHeader($this->dependency->tag));
    }
}
