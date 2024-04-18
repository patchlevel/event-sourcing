<?php

declare(strict_types=1);

use Doctrine\DBAL\Driver\PDO\SQLite\Driver;
use Doctrine\DBAL\DriverManager;
use Patchlevel\EventSourcing\Repository\DefaultRepository;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Profile;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\ProfileId;

require_once __DIR__ . '/../../vendor/autoload.php';

const DB_PATH = __DIR__ . '/BasicImplementation/data/db.sqlite3';

if (file_exists(DB_PATH)) {
    unlink(DB_PATH);
}

$connection = DriverManager::getConnection([
    'driverClass' => Driver::class,
    'path' => DB_PATH,
]);

$store = new DoctrineDbalStore(
    $connection,
    DefaultEventSerializer::createFromPaths([__DIR__ . '/BasicImplementation/Events']),
);

$repository = new DefaultRepository($store, Profile::metadata());

$schemaDirector = new DoctrineSchemaDirector(
    $connection,
    $store,
);

$schemaDirector->create();

$store->transactional(static function () use ($repository): void {
    for ($i = 0; $i < 10_000; $i++) {
        $id = ProfileId::v7();
        $profile = Profile::create($id, 'Peter');

        for ($j = 0; $j < 10; $j++) {
            $profile->changeName('Peter ' . $j);
        }

        $repository->save($profile);
    }
});
