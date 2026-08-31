<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Telemetry;

use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\Subscription;

use function count;
use function implode;
use function strrchr;
use function strtolower;
use function substr;

final class TraceableSubscriptionEngine implements SubscriptionEngine
{
    private readonly Instrumentation $instrumentation;

    public function __construct(
        private readonly SubscriptionEngine $subscriptionEngine,
        TracerProviderInterface|null $tracerProvider = null,
    ) {
        $this->instrumentation = new Instrumentation($tracerProvider);
    }

    public function execute(Command $command): Result
    {
        return $this->instrumentation->span(
            'event_sourcing.subscription.' . self::commandName($command),
            function () use ($command): Result {
                $result = $this->subscriptionEngine->execute($command);

                $span = Span::getCurrent();
                $span->setAttribute(TraceAttributes::ERROR_COUNT, count($result->errors));

                if ($result instanceof ProcessedResult) {
                    $span->setAttribute(
                        TraceAttributes::SUBSCRIPTION_PROCESSED_MESSAGES,
                        $result->processedMessages,
                    );
                    $span->setAttribute(TraceAttributes::SUBSCRIPTION_FINISHED, $result->finished);
                }

                return $result;
            },
            attributes: [
                TraceAttributes::SUBSCRIPTION_ID => $command->ids === null ? null : implode(',', $command->ids),
                TraceAttributes::SUBSCRIPTION_GROUP => $command->groups === null
                    ? null
                    : implode(',', $command->groups),
            ],
        );
    }

    /** @return list<Subscription> */
    public function subscriptions(SubscriptionEngineCriteria|null $criteria = null): array
    {
        return $this->instrumentation->span(
            'event_sourcing.subscription.subscriptions',
            fn (): array => $this->subscriptionEngine->subscriptions($criteria),
        );
    }

    private static function commandName(Command $command): string
    {
        $shortName = strrchr($command::class, '\\');

        return strtolower($shortName === false ? $command::class : substr($shortName, 1));
    }
}
