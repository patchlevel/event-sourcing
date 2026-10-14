<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
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
            $subscriptions = $this->engine->subscriptions($this->subscriptionEngineCriteria($input));
            $showPartitions = InputHelper::bool($input->getOption('partitions'));

            /** @var array<string, list<Subscription>> $partitions */
            $partitions = [];

            foreach ($subscriptions as $subscription) {
                if (!$subscription->isPartition()) {
                    continue;
                }

                $partitions[$subscription->subscriberId()][] = $subscription;
            }

            $rows = [];

            foreach ($subscriptions as $subscription) {
                if ($subscription->isPartition()) {
                    continue;
                }

                $rows[] = [
                    $subscription->id(),
                    $subscription->group(),
                    $subscription->runMode()->value,
                    $subscription->position(),
                    $subscription->status()->value,
                    self::partitionSummary($partitions[$subscription->id()] ?? []),
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

        $subscription = $this->findSubscription(
            $this->engine->subscriptions(new SubscriptionEngineCriteria([$id])),
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
     * The number of partitions and how many of them are not active, e.g. "12 (error: 1)".
     *
     * @param list<Subscription> $partitions
     */
    private static function partitionSummary(array $partitions): string|null
    {
        if ($partitions === []) {
            return null;
        }

        $notActive = [];

        foreach ($partitions as $partition) {
            if ($partition->isActive()) {
                continue;
            }

            $status = $partition->status()->value;
            $notActive[$status] = ($notActive[$status] ?? 0) + 1;
        }

        if ($notActive === []) {
            return (string)count($partitions);
        }

        $parts = [];

        foreach ($notActive as $status => $count) {
            $parts[] = sprintf('%s: %d', $status, $count);
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
