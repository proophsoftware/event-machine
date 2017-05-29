<?php
declare(strict_types = 1);

namespace Prooph\EventMachine\Container;

use Psr\Container\NotFoundExceptionInterface;

final class ServiceNotFound extends \RuntimeException implements NotFoundExceptionInterface
{
    public static function withServiceId(string $id): ServiceNotFound
    {
        return new self("Service not found with id: " . $id);
    }
}
