<?php

declare(strict_types=1);

namespace Weglot\DoctrinePostgresEnum\Doctrine\Schema;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\PostgreSQLSchemaManager;

class PostgreSQLEnumSchemaManager extends PostgreSQLSchemaManager
{
    public function createComparator(): Comparator
    {
        return new EnumComparator($this->platform);
    }
}
