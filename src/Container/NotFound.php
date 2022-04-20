<?php

namespace Patchlevel\EventSourcing\Container;

use Psr\Container\NotFoundExceptionInterface;

class NotFound extends ContainerException implements NotFoundExceptionInterface
{
}