<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionNotFound;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Subscription\SubscriptionError;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function implode;
use function in_array;
use function is_array;
use function sprintf;

/** @phpstan-import-type Context from SubscriptionError */
#[AsCommand(
    'event-sourcing:subscription:status',
    'View the current status of the subscriptions',
)]
final class SubscriptionStatusCommand extends SubscriptionCommand
{
    /** Partitions in these states are shown by default, active and finished ones only with --partitions. */
    private const ATTENTION = [
        Status::New,
        Status::Booting,
        Status::Paused,
        Status::Detached,
        Status::Error,
        Status::Failed,
    ];

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

            // a subscriber can have many partitions, without the option only the ones that need attention are loaded
            $partitionList = $subscriptions === [] ? [] : $this->engine->subscriptions(new SubscriptionEngineCriteria(
                $criteria->ids,
                $criteria->groups,
                partitions: true,
                status: $showPartitions ? null : self::ATTENTION,
            ));

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
                    self::partitionSummary($partitions[$subscription->id()] ?? [], $showPartitions),
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

    /**
     * With all partitions their number and how many of them need attention, e.g. "12 (error: 1)".
     * Otherwise only the ones that need attention, e.g. "error: 1".
     *
     * @param list<Subscription> $partitions
     */
    private static function partitionSummary(array $partitions, bool $all): string|null
    {
        $attention = [];

        foreach ($partitions as $partition) {
            if (!in_array($partition->status(), self::ATTENTION, true)) {
                continue;
            }

            $status = $partition->status()->value;
            $attention[$status] = ($attention[$status] ?? 0) + 1;
        }

        $parts = [];

        foreach ($attention as $status => $count) {
            $parts[] = sprintf('%s: %d', $status, $count);
        }

        if (!$all) {
            return $parts === [] ? null : implode(', ', $parts);
        }

        if ($partitions === []) {
            return null;
        }

        if ($parts === []) {
            return (string)count($partitions);
        }

        return sprintf('%d (%s)', count($partitions), implode(', ', $parts));
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
