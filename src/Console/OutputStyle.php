<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console;

use DateTimeInterface;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function array_keys;
use function array_values;
use function sprintf;

final class OutputStyle extends SymfonyStyle
{
    public function message(EventSerializer $serializer, Message $message): void
    {
        $event = $message->event();

        try {
            $data = $serializer->serialize($event, [Encoder::OPTION_PRETTY_PRINT => true]);
        } catch (Throwable $error) {
            $this->error(
                sprintf(
                    'Error while serializing event "%s": %s',
                    $message->event()::class,
                    $error->getMessage(),
                ),
            );

            if ($this->isVeryVerbose()) {
                $this->throwable($error);
            }

            return;
        }

        $this->title($data->name);

        $headers = $message->headers();

        if (isset($headers['recordedOn']) && $headers['recordedOn'] instanceof DateTimeInterface) {
            $headers['recordedOn'] = $headers['recordedOn']->format(DateTimeInterface::ATOM);
        }

        $this->horizontalTable(array_keys($headers), [array_values($headers)]);

        $this->block($data->payload);
    }

    public function throwable(Throwable $error): void
    {
        $number = 1;

        do {
            $this->error(sprintf('%d) %s', $number, $error->getMessage()));
            $this->block($error->getTraceAsString());

            $number++;
            $error = $error->getPrevious();
        } while ($error !== null);
    }
}
