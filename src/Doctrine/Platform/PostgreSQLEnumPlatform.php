<?php

declare(strict_types=1);

namespace Weglot\DoctrinePostgresEnum\Doctrine\Platform;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\TableDiff;
use Weglot\DoctrinePostgresEnum\Doctrine\Schema\PostgreSQLEnumSchemaManager;

class PostgreSQLEnumPlatform extends PostgreSQLPlatform
{
    /** @var array<string, bool> */
    private array $upIsExecuted = [];

    public function __construct(
        private readonly EnumMetaDataProvider $metaDataProvider,
    ) {
        parent::__construct();
    }

    public function _getCreateTableSQL(string $name, array $columns, array $options = []): array
    {
        $sql = parent::_getCreateTableSQL($name, $columns, $options);

        foreach ($columns as $column) {
            $quotedColumnName = $column['name'];
            $columnName = $this->unquoteSingleIdentifier($quotedColumnName);

            $enumValues = $this->metaDataProvider->getPhpEnumValues($name, $columnName);
            if (null !== $enumValues && [] !== $enumValues) {
                $sql[] = $this->metaDataProvider->addEnumCheckConstraintSQL($name, $columnName, $quotedColumnName, $enumValues);
            }
        }

        return $sql;
    }

    public function getAlterTableSQL(TableDiff $diff): array
    {
        $sql = parent::getAlterTableSQL($diff);

        $table = $diff->getOldTable();
        $tableName = $table->getObjectName()->toString();

        $columnsToProcess = [];
        $isAdded = [];
        $isDropped = [];

        foreach ($diff->getAddedColumns() as $column) {
            $columnName = $column->getObjectName()->toString();
            $isAdded[$columnName] = true;
            $columnsToProcess[$columnName] = $column;
        }
        foreach ($diff->getDroppedColumns() as $column) {
            $columnName = $column->getObjectName()->toString();
            $isDropped[$columnName] = true;
        }

        foreach ($diff->getChangedColumns() as $columnDiff) {
            $newColumn = $columnDiff->getNewColumn();
            $columnName = $newColumn->getObjectName()->toString();
            $columnsToProcess[$columnName] = $newColumn;
        }
        foreach ($table->getColumns() as $column) {
            $columnName = $column->getObjectName()->toString();
            if (!isset($columnsToProcess[$columnName]) && !isset($isDropped[$columnName])) {
                $columnsToProcess[$columnName] = $column;
            }
        }

        foreach ($columnsToProcess as $columnName => $column) {
            $phpEnumValues = $this->metaDataProvider->getPhpEnumValues($tableName, $columnName);

            if (null !== $phpEnumValues && [] !== $phpEnumValues) {
                $databaseEnumValues = $this->metaDataProvider->getDatabaseEnumValues($tableName, $columnName);

                if (isset($isAdded[$columnName])) {
                    // When the column is new there is obviously no existing constraint in database.
                    // Setting this manually is especially useful when generating the down migration.
                    $oldEnumValues = [];
                } else {
                    $oldEnumValues = $this->isUpExecuted($tableName) ? $phpEnumValues : $databaseEnumValues;
                }
                $newEnumValues = $this->isUpExecuted($tableName) ? $databaseEnumValues : $phpEnumValues;

                if ($this->metaDataProvider->enumValuesHaveChanged($oldEnumValues, $newEnumValues)) {
                    if ([] !== $oldEnumValues) {
                        $sql[] = $this->metaDataProvider->dropEnumCheckConstraintSQL($tableName, $columnName);
                    }
                    if ([] !== $newEnumValues) {
                        $quotedColumnName = $column->getObjectName()->toSQL($this);
                        $sql[] = $this->metaDataProvider->addEnumCheckConstraintSQL($tableName, $columnName, $quotedColumnName, $newEnumValues);
                    }
                }
            }
        }

        $this->toggleUpExecuted($tableName);

        return $sql;
    }

    public function unquoteSingleIdentifier(string $str): string
    {
        if (\strlen($str) >= 2 && str_starts_with($str, '"') && str_ends_with($str, '"')) {
            return str_replace('""', '"', substr($str, 1, -1));
        }

        return $str;
    }

    public function createSchemaManager(Connection $connection): PostgreSQLEnumSchemaManager
    {
        return new PostgreSQLEnumSchemaManager($connection, $this);
    }

    private function isUpExecuted(string $tableName): bool
    {
        return $this->upIsExecuted[$tableName] ?? false;
    }

    public function toggleUpExecuted(string $tableName): void
    {
        $this->upIsExecuted[$tableName] = !$this->isUpExecuted($tableName);
    }
}
