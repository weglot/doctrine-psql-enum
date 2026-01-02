<?php

declare(strict_types=1);

namespace Weglot\DoctrinePostgresEnum\Doctrine\Driver;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Weglot\DoctrinePostgresEnum\Doctrine\Platform\EnumMetaDataProvider;

#[AsMiddleware]
class EnumColumnMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly EnumMetaDataProvider $metaDataProvider,
    ) {
    }

    public function wrap(DriverInterface $driver): DriverInterface
    {
        return new Driver($driver, $this->metaDataProvider);
    }
}
