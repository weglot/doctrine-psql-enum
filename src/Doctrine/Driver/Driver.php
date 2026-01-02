<?php

declare(strict_types=1);

namespace Weglot\DoctrinePostgresEnum\Doctrine\Driver;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\ServerVersionProvider;
use Weglot\DoctrinePostgresEnum\Doctrine\Platform\EnumMetaDataProvider;
use Weglot\DoctrinePostgresEnum\Doctrine\Platform\PostgreSQLEnumPlatform;

class Driver extends AbstractDriverMiddleware
{
    public function __construct(
        DriverInterface $driver,
        private readonly EnumMetaDataProvider $metaDataProvider,
    ) {
        parent::__construct($driver);
    }

    public function getDatabasePlatform(ServerVersionProvider $versionProvider): AbstractPlatform
    {
        $platform = parent::getDatabasePlatform($versionProvider);

        if (!$platform instanceof PostgreSQLPlatform) {
            return $platform;
        }

        return new PostgreSQLEnumPlatform($this->metaDataProvider);
    }
}
