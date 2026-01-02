<?php

declare(strict_types=1);

namespace Weglot\DoctrinePostgresEnum\Doctrine\Platform;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\FieldMapping;

class EnumMetaDataProvider
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<int|string>|null
     */
    public function getPhpEnumValues(string $tableName, string $columnName): ?array
    {
        $metadata = $this->findMetadataForTable($tableName);
        if (null === $metadata) {
            return null;
        }

        $fieldMapping = $this->findFieldMappingForColumn($metadata, $columnName);
        if (null === $fieldMapping) {
            return null;
        }

        return $this->getEnumValuesFromField($fieldMapping);
    }

    /**
     * @return ClassMetadata<object>|null
     */
    private function findMetadataForTable(string $tableName): ?ClassMetadata
    {
        $metadataFactory = $this->entityManager->getMetadataFactory();
        foreach ($metadataFactory->getAllMetadata() as $metadata) {
            if ($metadata->getTableName() === $tableName) {
                return $metadata;
            }
        }

        return null;
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function findFieldMappingForColumn(ClassMetadata $metadata, string $columnName): ?FieldMapping
    {
        foreach ($metadata->getFieldNames() as $fieldName) {
            $mapping = $metadata->getFieldMapping($fieldName);
            if ($mapping->columnName === $columnName) {
                return $mapping;
            }
        }

        return null;
    }

    /**
     * @return array<int|string>|null
     */
    private function getEnumValuesFromField(FieldMapping $mapping): ?array
    {
        $enumClass = $mapping->enumType;
        if (null === $enumClass || !enum_exists($enumClass)) {
            return null;
        }

        $cases = $enumClass::cases();
        $values = [];
        foreach ($cases as $case) {
            $values[] = $case->value;
        }

        return $values;
    }

    /**
     * @return array<int|string>
     */
    public function getDatabaseEnumValues(string $tableName, string $columnName): array
    {
        $constraintName = $this->generateEnumCheckConstraintName($tableName, $columnName);

        $sql = <<<SQL
            SELECT cc.check_clause
            FROM information_schema.check_constraints cc
            JOIN information_schema.constraint_column_usage ccu ON cc.constraint_name = ccu.constraint_name
            WHERE cc.constraint_name = :constraintName
            SQL;

        $stmt = $this->entityManager->getConnection()->prepare($sql);
        $stmt->bindValue('constraintName', $constraintName);
        $result = $stmt->executeQuery();
        $checkClause = $result->fetchOne();

        if (!\is_string($checkClause)) {
            return [];
        }

        return $this->extractEnumValuesFromCheckClause($checkClause);
    }

    private function generateEnumCheckConstraintName(string $tableName, string $columnName): string
    {
        return \sprintf('%s_%s_check', $tableName, $columnName);
    }

    /**
     * @return array<int|string>
     */
    private function extractEnumValuesFromCheckClause(string $checkClause): array
    {
        if (1 !== preg_match('/(?:ARRAY\[(.*?)\]|IN \((.*)\))/', $checkClause, $matches)) {
            return [];
        }

        if ('' !== $matches[1]) {
            $valuesString = $matches[1];
        } elseif (isset($matches[2])) {
            $valuesString = $matches[2];
        } else {
            return [];
        }

        $valuesParts = explode(',', $valuesString);

        $values = [];
        foreach ($valuesParts as $part) {
            if (1 === preg_match("/'(.*?)'/", $part, $valueMatch)) {
                $values[] = $valueMatch[1];
            } elseif (1 === preg_match('/\d+/', $part)) {
                $values[] = (int) $part;
            }
        }

        return $values;
    }

    /**
     * @param array<int|string> $enumValues
     */
    public function addEnumCheckConstraintSQL(string $tableName, string $columnName, string $quotedColumnName, array $enumValues): string
    {
        return \sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s %s',
            $tableName,
            $this->generateEnumCheckConstraintName($tableName, $columnName),
            $this->getEnumCheckConstraintSQL($quotedColumnName, $enumValues)
        );
    }

    /**
     * @param array<int|string> $enumValues
     */
    private function getEnumCheckConstraintSQL(string $columnName, array $enumValues): string
    {
        $quotedValues = array_map(
            fn (int|string $value) => \is_string($value) ? $this->quoteStringLiteral($value) : $value,
            $enumValues
        );

        return \sprintf('CHECK (%s IN (%s))', $columnName, implode(', ', $quotedValues));
    }

    private function quoteStringLiteral(string $str): string
    {
        return "'".str_replace("'", "''", $str)."'";
    }

    public function dropEnumCheckConstraintSQL(string $tableName, string $columnName): string
    {
        return \sprintf(
            'ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s',
            $tableName,
            $this->generateEnumCheckConstraintName($tableName, $columnName)
        );
    }

    /**
     * @param array<int|string> $currentValues
     * @param array<int|string> $newValues
     */
    public function enumValuesHaveChanged(array $currentValues, array $newValues): bool
    {
        if (\count($currentValues) !== \count($newValues)) {
            return true;
        }

        sort($currentValues);
        sort($newValues);

        return $currentValues !== $newValues;
    }
}
