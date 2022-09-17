<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

interface Projectionist
{
    public function boot(): void;

    public function run(?int $limit = null): void;

    public function teardown(): void;

    public function destroy(): void;
}
