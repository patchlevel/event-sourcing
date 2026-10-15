<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionNotFound;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStats;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Subscription\SubscriptionError;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function implode;
use function is_array;
use function sprintf;

/** @phpstan-import-type Context from SubscriptionError */
#[AsCommand(
    'event-sourcing:subscription:status',
    'View the current status of the subscriptions',
)]
final class SubscriptionStatusCommand extends SubscriptionCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument(
                'id',
                InputArgument::OPTIONAL,
                'The subscription to display more information about, a partition has the id "{subscriber id}#{partition key}"',
            )
            ->addOption(
                'partitions',
                null,
                InputOption::VALUE_NONE,
                'list the partitions of partitioned subscribers',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new OutputStyle($input, $output);

        $id = InputHelper::nullableString($input->getArgument('id'));

        if ($id === null) {
            $criteria = $this->subscriptionEngineCriteria($input);
            $showPartitions = InputHelper::bool($input->getOption('partitions'));

            $subscriptions = $this->engine->subscriptions(
                new SubscriptionEngineCriteria($criteria->ids, $criteria->groups, partitions: false),
            );

            // a subscriber can have many partitions, they are only loaded to list them
            $partitionList = $showPartitions && $subscriptions !== []
                ? $this->engine->subscriptions(
                    new SubscriptionEngineCriteria($criteria->ids, $criteria->groups, partitions: true),
                )
                : [];

            /** @var array<string, list<Subscription>> $partitions */
            $partitions = [];

            foreach ($partitionList as $partition) {
                $partitions[$partition->subscriberId()][] = $partition;
            }

            $rows = [];

            foreach ($subscriptions as $subscription) {
                $rows[] = [
                    $subscription->id(),
                    $subscription->group(),
                    $subscription->runMode()->value,
                    $subscription->position(),
                    $subscription->status()->value,
                    self::partitionSummary($this->engine->stats(
                        new SubscriptionEngineCriteria([$subscription->id()], partitions: true),
                    )),
                    $subscription->subscriptionError()?->errorMessage,
                ];

                if (!$showPartitions) {
                    continue;
                }

                foreach ($partitions[$subscription->id()] ?? [] as $partition) {
                    $rows[] = [
                        '  └ ' . $partition->partitionKey(),
                        $partition->group(),
                        $partition->runMode()->value,
                        $partition->position(),
                        $partition->status()->value,
                        null,
                        $partition->subscriptionError()?->errorMessage,
                    ];
                }
            }

            $io->table(
                [
                    'id',
                    'group',
                    'run mode',
                    'position',
                    'status',
                    'partitions',
                    'error message',
                ],
                $rows,
            );

            return 0;
        }

        // the id of a subscriber also matches all its partitions, so the main subscription is looked up on its own
        $subscription = $this->findSubscription(
            $this->engine->subscriptions(new SubscriptionEngineCriteria([$id], partitions: false))
                ?: $this->engine->subscriptions(new SubscriptionEngineCriteria([$id], partitions: true)),
            $id,
        );

        $io->horizontalTable(
            [
                'id',
                'group',
                'run mode',
                'position',
                'status',
                'error message',
            ],
            [
                [
                    $subscription->id(),
                    $subscription->group(),
                    $subscription->runMode()->value,
                    $subscription->position(),
                    $subscription->status()->value,
                    $subscription->subscriptionError()?->errorMessage,
                ],
            ],
        );

        $contexts = $subscription->subscriptionError()?->errorContext;

        if (is_array($contexts)) {
            foreach ($contexts as $context) {
                $this->displayError($io, $context);
            }
        }

        return 0;
    }

    /** @param Context $context */
    private function displayError(OutputStyle $io, array $context): void
    {
        $io->error($context['message']);

        foreach ($context['trace'] as $trace) {
            $io->writeln(sprintf('%s: %s', $trace['file'] ?? '#unknown', $trace['line'] ?? '#unknown'));
        }
    }

    /** The number of partitions and how many of them are not active, e.g. "1204 (error: 2, booting: 5)". */
    private static function partitionSummary(SubscriptionStats $stats): string|null
    {
        $total = $stats->total();

        if ($total === 0) {
            return null;
        }

        $parts = [];

        foreach ($stats->status as $status => $count) {
            if ($status === Status::Active->value) {
                continue;
            }

            $parts[] = sprintf('%s: %d', $status, $count);
        }

        if ($parts === []) {
            return (string)$total;
        }

        return sprintf('%d (%s)', $total, implode(', ', $parts));
    }

    /** @param list<Subscription> $subscriptions */
    private function findSubscription(array $subscriptions, string $id): Subscription
    {
        foreach ($subscriptions as $subscription) {
            if ($subscription->id() === $id) {
                return $subscription;
            }
        }

        throw new SubscriptionNotFound($id);
    }
}
