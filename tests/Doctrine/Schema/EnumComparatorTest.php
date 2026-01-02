<?php

declare(strict_types=1);

namespace Weglot\DoctrinePostgresEnum\Tests\Doctrine\Schema;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\StringType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Weglot\DoctrinePostgresEnum\Doctrine\Schema\EnumComparator;

class EnumComparatorTest extends TestCase
{
    #[DataProvider('compareTableDataProvider')]
    public function testCompareTable(Table $table1, Table $table2, bool $isEqual): void
    {
        $comparator = new EnumComparator(new PostgreSQLPlatform());
        static::assertSame($isEqual, $comparator->compareTables($table1, $table2)->isEmpty());
    }

    /**
     * @return iterable<array{Table, Table, bool}>
     */
    public static function compareTableDataProvider(): iterable
    {
        yield [
            new Table('table', [new Column('column', new StringType())]),
            new Table('table', []),
            false,
        ];
        yield [
            new Table('table', []),
            new Table('table', [new Column('column', new StringType())]),
            false,
        ];
        yield [
            new Table('table', [new Column('column', new StringType())]),
            new Table('table', [new Column('column', new StringType())]),
            true,
        ];
        yield [
            new Table('table', [new Column('column', new StringType(), ['platformOptions' => ['enumType' => FooEnum::class]])]),
            new Table('table', [new Column('column', new StringType())]),
            false,
        ];
        yield [
            new Table('table', [new Column('column', new StringType())]),
            new Table('table', [new Column('column', new StringType(), ['platformOptions' => ['enumType' => FooEnum::class]])]),
            false,
        ];
        yield [
            new Table('table', [new Column('column', new StringType(), ['platformOptions' => ['enumType' => FooEnum::class]])]),
            new Table('table', [new Column('column', new StringType(), ['platformOptions' => ['enumType' => FooEnum::class]])]),
            false,
        ];
    }
}

enum FooEnum
{
}
