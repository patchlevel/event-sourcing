<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function array_keys;
use function array_values;
use function json_encode;
use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

final class OutputStyle extends SymfonyStyle
{
    public function message(
        EventSerializer $eventSerializer,
        Message $message,
    ): void {
        $event = $message->event();

        try {
            $data = $eventSerializer->serialize($event, [Encoder::OPTION_PRETTY_PRINT => true]);
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

        foreach ($message->headers() as $header) {
            $headers[$header::name()] = json_encode($header, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        }

        $this->title($data->name);
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
