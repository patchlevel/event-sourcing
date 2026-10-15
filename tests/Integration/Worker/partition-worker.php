<?php

declare(strict_types=1);

/**
 * A subscription worker for ConcurrentWorkerTest: runs the engine until the stop file exists.
 * Arguments: worker name, stop file, projector (plain, batch or emitting). The database comes from DB_URL.
 */

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\Dbal\PostgreSQLPlatformMiddleware;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\EventFilteredStoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use Patchlevel\EventSourcing\Subscription\Store\DoctrineSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\EventEmitterResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\ConcurrentBatchProfileProjection;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\ConcurrentEmittingProfileProjection;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\ConcurrentProfileProjection;

require __DIR__ . '/../../../vendor/autoload.php';

$worker = $argv[1] ?? 'worker';
$stopFile = $argv[2] ?? throw new RuntimeException('missing stop file');
$projector = $argv[3] ?? 'plain';
$dbUrl = getenv('DB_URL');

if (!is_string($dbUrl)) {
    throw new RuntimeException('missing DB_URL env');
}

$connection = DriverManager::getConnection(
    (new DsnParser())->parse($dbUrl),
    (new Configuration())->setMiddlewares([new PostgreSQLPlatformMiddleware()]),
);

$store = new StreamDoctrineDbalStore($connection, DefaultEventSerializer::createFromPaths([__DIR__ . '/../Subscription/Events']));
$subscriberRepository = new MetadataSubscriberAccessorRepository([
    match ($projector) {
        'plain' => new ConcurrentProfileProjection($connection, $worker),
        'batch' => new ConcurrentBatchProfileProjection($connection, $worker),
        'emitting' => new ConcurrentEmittingProfileProjection($connection, $worker),
        default => throw new RuntimeException('unknown projector ' . $projector),
    },
]);

$engine = new DefaultSubscriptionEngine(
    new EventFilteredStoreMessageLoader($store, new AttributeEventMetadataFactory(), $subscriberRepository),
    new DoctrineSubscriptionStore($connection),
    $subscriberRepository,
    argumentResolvers: [new EventEmitterResolver($store)],
);

$runs = 0;
$errors = 0;

while (!file_exists($stopFile)) {
    // small claims, so the workers get in each others way as often as possible
    $result = $engine->execute(new Run(limit: 5, partitionsPerClaim: 3));
    $runs++;

    foreach ($result->errors as $error) {
        $errors++;
        fwrite(STDERR, sprintf("%s: %s: %s\n", $worker, $error->subscriptionId, $error->message));
    }

    if (!$result instanceof ProcessedResult || !$result->finished) {
        continue;
    }

    usleep(10_000);
}

echo json_encode(['runs' => $runs, 'errors' => $errors]);

exit($errors === 0 ? 0 : 1);
