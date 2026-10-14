<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionNotFound;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Subscription\SubscriptionError;
use Patchlevel\EventSourcing\Subscription\SubscriptionType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function array_keys;
use function array_map;
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
                'The subscription to display more information about',
            )
            ->addOption(
                'partitions',
                null,
                InputOption::VALUE_NONE,
                'list the partitions of partitioned subscriptions',
            )
            ->addOption(
                'partition',
                null,
                InputOption::VALUE_REQUIRED,
                'The partition key of the subscription to display more information about',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new OutputStyle($input, $output);

        $id = InputHelper::nullableString($input->getArgument('id'));
        $showPartitions = InputHelper::bool($input->getOption('partitions'));

        if ($id === null) {
            $subscriptions = $this->engine->subscriptions($this->subscriptionEngineCriteria($input));
            $rows = [];

            foreach ($subscriptions as $subscription) {
                if ($subscription->type() === SubscriptionType::Partition) {
                    continue;
                }

                $partitions = $this->partitions($subscriptions, $subscription);
                $summary = $partitions === [] ? null : $this->summary($partitions);

                $rows[] = [
                    $subscription->id(),
                    $subscription->group(),
                    $subscription->runMode()->value,
                    $subscription->position(),
                    $subscription->status()->value,
                    $summary,
                    $subscription->subscriptionError()?->errorMessage,
                ];

                if (!$showPartitions) {
                    continue;
                }

                foreach ($partitions as $partition) {
                    $rows[] = [
                        '  └ ' . $partition->partitionKey(),
                        $partition->group(),
                        $partition->runMode()->value,
                        $partition->position(),
                        $partition->status()->value,
                        $partition->shard() !== null ? sprintf('shard %d', $partition->shard()) : null,
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
            InputHelper::nullableString($input->getOption('partition')),
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
     * @param list<Subscription> $subscriptions
     *
     * @return list<Subscription>
     */
    private function partitions(array $subscriptions, Subscription $root): array
    {
        $partitions = [];

        foreach ($subscriptions as $subscription) {
            if ($subscription->type() !== SubscriptionType::Partition || $subscription->subscriberId() !== $root->subscriberId()) {
                continue;
            }

            $partitions[] = $subscription;
        }

        return $partitions;
    }

    /** @param list<Subscription> $partitions */
    private function summary(array $partitions): string
    {
        $statusCounts = [];

        foreach ($partitions as $partition) {
            if ($partition->isActive()) {
                continue;
            }

            $status = $partition->status()->value;
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        }

        if ($statusCounts === []) {
            return (string)count($partitions);
        }

        return sprintf(
            '%d (%s)',
            count($partitions),
            implode(', ', array_map(
                static fn (string $status, int $count): string => sprintf('%s: %d', $status, $count),
                array_keys($statusCounts),
                $statusCounts,
            )),
        );
    }

    /** @param list<Subscription> $subscriptions */
    private function findSubscription(array $subscriptions, string $subscriberId, string|null $partitionKey): Subscription
    {
        foreach ($subscriptions as $subscription) {
            if ($subscription->subscriberId() === $subscriberId && $subscription->partitionKey() === $partitionKey) {
                return $subscription;
            }
        }

        if ($partitionKey !== null) {
            throw SubscriptionNotFound::forPartition($subscriberId, $partitionKey);
        }

        throw new SubscriptionNotFound($subscriberId);
    }
}
