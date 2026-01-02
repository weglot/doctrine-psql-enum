<?php

declare(strict_types=1);

namespace Weglot\DoctrinePostgresEnum\Doctrine\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Comparator;

class EnumComparator extends Comparator
{
    protected function columnsEqual(Column $column1, Column $column2): bool
    {
        $parent = parent::columnsEqual($column1, $column2);
        if (!$parent) {
            return false;
        }

        // If the column is an enum, we still want to check it.
        return !$column1->hasPlatformOption('enumType')
            && !$column2->hasPlatformOption('enumType');
    }
}
