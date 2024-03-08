<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionNotFound;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Subscription\SubscriptionError;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_map;
use function is_array;
use function sprintf;

/** @psalm-import-type Context from SubscriptionError */
#[AsCommand(
    'event-sourcing:subscription:status',
    'View the current status of the subscriptions',
)]
final class SubscriptionStatusCommand extends SubscriptionCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this->addArgument(
            'id',
            InputArgument::OPTIONAL,
            'The subscription to display more information about',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new OutputStyle($input, $output);

        $id = InputHelper::nullableString($input->getArgument('id'));
        $subscriptions = $this->engine->subscriptions();

        if ($id === null) {
            $io->table(
                [
                    'id',
                    'position',
                    'status',
                    'error message',
                ],
                array_map(
                    static fn (Subscription $subscription) => [
                        $subscription->id(),
                        $subscription->position(),
                        $subscription->status()->value,
                        $subscription->subscriptionError()?->errorMessage,
                    ],
                    $subscriptions,
                ),
            );

            return 0;
        }

        $subscription = $this->findSubscription($subscriptions, $id);

        $io->horizontalTable(
            [
                'id',
                'position',
                'status',
                'error message',
            ],
            [
                [
                    $subscription->id(),
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
