<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation;

use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Aggregate\Profile;
use PHPUnit\Framework\TestCase;

final class BasicIntegrationTest extends TestCase
{
    public function testSuccessful(): void
    {



        $profile = Profile::create('1');

    }
}
