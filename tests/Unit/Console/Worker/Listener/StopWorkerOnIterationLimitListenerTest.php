<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Worker\Listener;

use Patchlevel\EventSourcing\Console\Worker\Event\WorkerRunningEvent;
use Patchlevel\EventSourcing\Console\Worker\Listener\StopWorkerOnIterationLimitListener;
use Patchlevel\EventSourcing\Console\Worker\Worker;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

final class StopWorkerOnIterationLimitListenerTest extends TestCase
{
    use ProphecyTrait;

    public function testShouldNotStop(): void
    {
        $workerMock = $this->prophesize(Worker::class);
        $workerMock->stop()->shouldNotBeCalled();
        $worker = $workerMock->reveal();

        $listener = new StopWorkerOnIterationLimitListener(10);
        $listener->onWorkerRunning(new WorkerRunningEvent($worker));
    }

    public function testShouldStop(): void
    {
        $workerMock = $this->prophesize(Worker::class);
        $workerMock->stop()->shouldBeCalled();
        $worker = $workerMock->reveal();

        $listener = new StopWorkerOnIterationLimitListener(1);
        $listener->onWorkerRunning(new WorkerRunningEvent($worker));
    }
}
