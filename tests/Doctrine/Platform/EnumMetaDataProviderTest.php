<?php

declare(strict_types=1);

namespace Weglot\DoctrinePostgresEnum\Tests\Doctrine\Platform;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Mapping\FieldMapping;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Weglot\DoctrinePostgresEnum\Doctrine\Platform\EnumMetaDataProvider;

class EnumMetaDataProviderTest extends TestCase
{
    private EntityManagerInterface&Stub $entityManager;
    private EnumMetaDataProvider $enumMetaDataProvider;

    protected function setUp(): void
    {
        $this->entityManager = static::createStub(EntityManagerInterface::class);

        $this->enumMetaDataProvider = new EnumMetaDataProvider(
            $this->entityManager,
        );
    }

    public function testAddEnumCheckConstraintSQL(): void
    {
        static::assertSame(
            "ALTER TABLE table_foo ADD CONSTRAINT table_foo_column_bar_check CHECK (quoted_column_bar IN ('foo', 'bar', 42, 100))",
            $this->enumMetaDataProvider->addEnumCheckConstraintSQL(
                'table_foo',
                'column_bar',
                'quoted_column_bar',
                ['foo', 'bar', 42, 100],
            )
        );
    }

    public function testDropEnumCheckConstraintSQL(): void
    {
        static::assertSame(
            'ALTER TABLE table_foo DROP CONSTRAINT IF EXISTS table_foo_column_bar_check',
            $this->enumMetaDataProvider->dropEnumCheckConstraintSQL(
                'table_foo',
                'column_bar',
            )
        );
    }

    /**
     * @param array<int|string> $currentValues
     * @param array<int|string> $newValues
     */
    #[DataProvider('enumValuesHaveChangedDataProvider')]
    public function testEnumValuesHaveChanged(array $currentValues, array $newValues, bool $expected): void
    {
        static::assertSame($expected, $this->enumMetaDataProvider->enumValuesHaveChanged($currentValues, $newValues));
    }

    /**
     * @return iterable<array{array<int|string>, array<int|string>, bool}>
     */
    public static function enumValuesHaveChangedDataProvider(): iterable
    {
        yield [[], [], false];
        yield [[1], [1], false];
        yield [['1'], ['1'], false];
        yield [[2, 1], [1, 2], false];

        yield [[], [1], true];
        yield [[], ['1'], true];
        yield [[1], ['1'], true];
        yield [[2, 1], [2], true];
    }

    /**
     * @param array<int|string> $expected
     */
    #[DataProvider('getDatabaseEnumValuesDataProvider')]
    public function testGetDatabaseEnumValues(mixed $checkClause, array $expected): void
    {
        $connection = static::createStub(Connection::class);
        $this->entityManager->method('getConnection')->willReturn($connection);

        $statement = static::createStub(Statement::class);
        $connection->method('prepare')->willReturn($statement);

        $result = static::createStub(Result::class);
        $statement->method('executeQuery')->willReturn($result);

        $result->method('fetchOne')->willReturn($checkClause);

        static::assertSame($expected, $this->enumMetaDataProvider->getDatabaseEnumValues('foo', 'bar'));
    }

    /**
     * @return iterable<array{mixed, array<int|string>}>
     */
    public static function getDatabaseEnumValuesDataProvider(): iterable
    {
        yield ["(status IN ('pending', 'approved', 'rejected'))", [
            'pending',
            'approved',
            'rejected',
        ]];
        yield ["status = ANY (ARRAY['pending', 'approved', 'rejected'])", [
            'pending',
            'approved',
            'rejected',
        ]];
        yield ["(tone IN ('neutral'::text, 'informal'::text, 'formal'::text))", [
            'neutral',
            'informal',
            'formal',
        ]];
        yield ["((tone)::text = ANY ((ARRAY['neutral'::character varying, 'informal'::character varying, 'formal'::character varying])::text[]))", [
            'neutral',
            'informal',
            'formal',
        ]];
        yield ['(order IN (1, 2, 3, 4, 5))', [
            1,
            2,
            3,
            4,
            5,
        ]];
        yield ['(order = ANY (ARRAY[1, 2, 3, 4, 5]))', [
            1,
            2,
            3,
            4,
            5,
        ]];
        yield ['((order)::integer = ANY ((ARRAY[1, 2, 3, 4, 5])::integer[]))', [
            1,
            2,
            3,
            4,
            5,
        ]];
    }

    public function testGetPhpEnumValues(): void
    {
        $tableName = 'table';

        $metadataFactory = static::createStub(ClassMetadataFactory::class);
        $this->entityManager->method('getMetadataFactory')->willReturn($metadataFactory);

        $classMetadata = static::createStub(ClassMetadata::class);
        $classMetadata->method('getTableName')->willReturn($tableName);

        $classMetadata->method('getFieldNames')->willReturn([
            'foo',
            'bar',
            'baz',
        ]);
        $classMetadata->method('getFieldMapping')->willReturnMap([
            ['foo', FieldMapping::fromMappingArray(['type' => 'string', 'fieldName' => 'field1', 'columnName' => 'foo', 'enumType' => Foo::class])],
            ['bar', FieldMapping::fromMappingArray(['type' => 'string', 'fieldName' => 'field2', 'columnName' => 'bar', 'enumType' => Bar::class])],
            ['baz', FieldMapping::fromMappingArray(['type' => 'string', 'fieldName' => 'field3', 'columnName' => 'foo'])],
            ['unknown', FieldMapping::fromMappingArray(['type' => 'string', 'fieldName' => 'field3', 'columnName' => 'foo', 'enumType' => 'NotEnum'])],
        ]);

        $metadataFactory->method('getAllMetadata')->willReturn([$classMetadata]);

        static::assertSame([1, 2], $this->enumMetaDataProvider->getPhpEnumValues($tableName, 'foo'));
        static::assertSame(['a', 'b'], $this->enumMetaDataProvider->getPhpEnumValues($tableName, 'bar'));
        static::assertNull($this->enumMetaDataProvider->getPhpEnumValues($tableName, 'baz'));
        static::assertNull($this->enumMetaDataProvider->getPhpEnumValues($tableName, 'unknown'));
    }
}

enum Foo: int
{
    case A = 1;
    case B = 2;
}

enum Bar: string
{
    case A = 'a';
    case B = 'b';
}
