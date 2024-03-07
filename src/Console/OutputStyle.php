<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\EventBus\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function array_map;
use function sprintf;

final class OutputStyle extends SymfonyStyle
{
    public function message(
        EventSerializer $eventSerializer,
        HeadersSerializer $headersSerializer,
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

        try {
            $headers = $headersSerializer->serialize($message->headers(), [Encoder::OPTION_PRETTY_PRINT => true]);
        } catch (Throwable $error) {
            $this->error(
                sprintf(
                    'Error while serializing headers: %s',
                    $error->getMessage(),
                ),
            );

            if ($this->isVeryVerbose()) {
                $this->throwable($error);
            }

            return;
        }

        $this->title($data->name);

        $this->horizontalTable(
            array_map(
                static fn (array $serializedHeader) => $serializedHeader['name'],
                $headers,
            ),
            array_map(
                static fn (array $serializedHeader) => [$serializedHeader['payload']],
                $headers,
            ),
        );

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
