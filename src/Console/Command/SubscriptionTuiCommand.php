<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Tui\Event\InputEvent;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\EditorWidget;
use Symfony\Component\Tui\Widget\TextWidget;

#[AsCommand(
    'event-sourcing:subscription',
    'Interactive subscription terminal',
)]
final class SubscriptionTuiCommand extends SubscriptionCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $criteria = $this->subscriptionEngineCriteria($input);

        $tui = new Tui();

        $keys = new Keybindings(['quit' => ['ctrl+c']]);
        $tui->addListener(
            static function (InputEvent $event) use ($tui, $keys): void {
                if ($keys->matches($event->getData(), 'quit')) {
                    $tui->stop();
                }
            }
        );

        $editor = new EditorWidget();
        $editor->onSubmit(fn() => $tui->stop());

        $tui->add(new TextWidget('Type your message:'));
        $tui->add($editor);
        $tui->setFocus($editor);

        $tui->run();

        if ($editor->wasSubmitted()) {
            $output->writeln('You wrote:');
            $output->writeln($editor->getText());
        }

        return Command::SUCCESS;
    }
}
