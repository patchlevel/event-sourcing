<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Worker;

use Patchlevel\EventSourcing\Console\Worker\DefaultWorker;
use Patchlevel\EventSourcing\Console\Worker\Event\WorkerRunningEvent;
use Patchlevel\EventSourcing\Console\Worker\Event\WorkerStartedEvent;
use Patchlevel\EventSourcing\Console\Worker\Event\WorkerStoppedEvent;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class DefaultWorkerTest extends TestCase
{
    use ProphecyTrait;

    public function testRunWorker(): void
    {
        $evenDispatcher = $this->prophesize(EventDispatcherInterface::class);
        $evenDispatcher->dispatch(Argument::type(WorkerStartedEvent::class))->shouldBeCalledTimes(1);
        $evenDispatcher->dispatch(Argument::type(WorkerRunningEvent::class))->shouldBeCalledTimes(1)->will(
            /** @param array{WorkerRunningEvent} $args */
            static function (array $args) {
                $args[0]->worker->stop();

                return $args[0];
            }
        );
        $evenDispatcher->dispatch(Argument::type(WorkerStoppedEvent::class))->shouldBeCalledTimes(1);

        $logger = $this->prophesize(LoggerInterface::class);
        $logger->debug('Worker starting')->shouldBeCalledTimes(1);
        $logger->debug('Worker starting job run')->shouldBeCalledTimes(1);
        $logger->debug('Worker finished job run ({ranTime}ms)', Argument::any())->shouldBeCalledTimes(1);
        $logger->debug('Worker received stop signal')->shouldBeCalledTimes(1);
        $logger->debug('Worker stopped')->shouldBeCalledTimes(1);
        $logger->debug('Worker terminated')->shouldBeCalledTimes(1);

        $worker = new DefaultWorker(static fn () => null, $evenDispatcher->reveal(), $logger->reveal());
        $worker->run(200);
    }
}
