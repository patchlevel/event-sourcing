<?php

declare(strict_types=1);

/**
 * Holds the row lock of a subscription like a worker that is processing it, for WaitingClaimTest.
 * Arguments: subscription id, seconds, ready file. The database comes from DB_URL.
 */

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;

require __DIR__ . '/../../../vendor/autoload.php';

$id = $argv[1] ?? throw new RuntimeException('missing subscription id');
$seconds = (float)($argv[2] ?? 1);
$readyFile = $argv[3] ?? throw new RuntimeException('missing ready file');
$dbUrl = getenv('DB_URL');

if (!is_string($dbUrl)) {
    throw new RuntimeException('missing DB_URL env');
}

$connection = DriverManager::getConnection((new DsnParser())->parse($dbUrl));
$connection->beginTransaction();
$connection->fetchAllAssociative('SELECT id FROM subscriptions WHERE id = ? FOR UPDATE', [$id]);

touch($readyFile);
usleep((int)($seconds * 1_000_000));

$connection->commit();
