<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Telemetry;

use OpenTelemetry\API\Trace\StatusCode;
use Patchlevel\EventSourcing\Repository\AggregateNotFound;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Telemetry\TraceableRepository;
use Patchlevel\EventSourcing\Telemetry\TraceAttributes;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TraceableRepository::class)]
final class TraceableRepositoryTest extends TestCase
{
    use InMemoryTracer;

    public function testLoad(): void
    {
        $id = ProfileId::fromString('1');
        $profile = Profile::createProfile($id, Email::fromString('info@patchlevel.de'));

        $repository = $this->createMock(Repository::class);
        $repository
            ->expects($this->once())
            ->method('load')
            ->with($id)
            ->willReturn($profile);

        $traceableRepository = new TraceableRepository($repository, 'profile', $this->createTracerProvider());

        self::assertSame($profile, $traceableRepository->load($id));

        $span = $this->span();

        self::assertSame('event_sourcing.repository.load', $span->getName());
        self::assertSame('profile', $span->getAttributes()->get(TraceAttributes::AGGREGATE_NAME));
        self::assertSame('1', $span->getAttributes()->get(TraceAttributes::AGGREGATE_ID));
    }

    public function testHas(): void
    {
        $id = ProfileId::fromString('1');

        $repository = $this->createMock(Repository::class);
        $repository
            ->expects($this->once())
            ->method('has')
            ->with($id)
            ->willReturn(true);

        $traceableRepository = new TraceableRepository($repository, 'profile', $this->createTracerProvider());

        self::assertTrue($traceableRepository->has($id));
        self::assertSame('event_sourcing.repository.has', $this->span()->getName());
    }

    public function testSave(): void
    {
        $profile = Profile::createProfile(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de'));

        $repository = $this->createMock(Repository::class);
        $repository
            ->expects($this->once())
            ->method('save')
            ->with($profile);

        $traceableRepository = new TraceableRepository($repository, 'profile', $this->createTracerProvider());
        $traceableRepository->save($profile);

        self::assertSame('event_sourcing.repository.save', $this->span()->getName());
    }

    public function testRecordsExceptionOnSpan(): void
    {
        $id = ProfileId::fromString('1');

        $repository = $this->createMock(Repository::class);
        $repository
            ->expects($this->once())
            ->method('load')
            ->with($id)
            ->willThrowException(new AggregateNotFound(Profile::class, $id));

        $traceableRepository = new TraceableRepository($repository, 'profile', $this->createTracerProvider());

        $this->expectException(AggregateNotFound::class);

        try {
            $traceableRepository->load($id);
        } finally {
            self::assertSame(StatusCode::STATUS_ERROR, $this->span()->getStatus()->getCode());
        }
    }
}
