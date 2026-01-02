<?php

declare(strict_types=1);

namespace Weglot\DoctrinePostgresEnum\Tests\Doctrine\Platform;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\StringType;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Weglot\DoctrinePostgresEnum\Doctrine\Platform\EnumMetaDataProvider;
use Weglot\DoctrinePostgresEnum\Doctrine\Platform\PostgreSQLEnumPlatform;

class PostgreSQLEnumPlatformTest extends TestCase
{
    private EnumMetaDataProvider&Stub $enumMetaDataProvider;
    private PostgreSQLEnumPlatform $postgreSQLEnumPlatform;

    protected function setUp(): void
    {
        $this->enumMetaDataProvider = static::createStub(EnumMetaDataProvider::class);

        $this->postgreSQLEnumPlatform = new PostgreSQLEnumPlatform(
            $this->enumMetaDataProvider,
        );
    }

    public function testGetCreateTableSQL(): void
    {
        $this->enumMetaDataProvider
            ->method('getPhpEnumValues')
            ->willReturn(['A', 'B']);

        $constraint = "ALTER TABLE myTable ADD CONSTRAINT table_column_check CHECK (column IN ('A', 'B', 'C'))";
        $this->enumMetaDataProvider
            ->method('addEnumCheckConstraintSQL')
            ->willReturn($constraint);

        static::assertSame(
            [
                'CREATE TABLE myTable (column VARCHAR DEFAULT NULL)',
                $constraint,
            ],
            $this->postgreSQLEnumPlatform->_getCreateTableSQL('myTable', [
                [
                    'name' => 'column',
                    'type' => new StringType(),
                    'default' => null,
                    'autoincrement' => false,
                    'columnDefinition' => null,
                    'comment' => '',
                ],
            ])
        );
    }

    public function testGetAlterTableSQL(): void
    {
        $table = new Table('myTable', [
            new Column('changedColumn', new StringType()),
            new Column('notChangedColumn', new StringType()),
            new Column('droppedColumn', new StringType()),
        ]);

        $this->enumMetaDataProvider->method('getPhpEnumValues')->willReturnMap([
            ['myTable', 'newColumn', ['newA', 'newB']],
            ['myTable', 'changedColumn', ['changedA', 'changedB']],
            ['myTable', 'notChangedColumn', ['notChangedA', 'notChangedB']],
            ['myTable', 'droppedColumn', ['dropA', 'dropB']],
        ]);
        $this->enumMetaDataProvider->method('getDatabaseEnumValues')->willReturnMap([
            ['myTable', 'newColumn', []],
            ['myTable', 'changedColumn', ['oldA', 'oldB']],
            ['myTable', 'notChangedColumn', ['notChangedA', 'notChangedB']],
            ['myTable', 'droppedColumn', ['dropA', 'dropB']],
        ]);
        $this->enumMetaDataProvider->method('enumValuesHaveChanged')->willReturnCallback(
            static fn (array $a, array $b): bool => $a !== $b,
        );
        $this->enumMetaDataProvider->method('dropEnumCheckConstraintSQL')->willReturnCallback(
            static fn (string $tableName, string $columnName): string => \sprintf('Drop constraint %s_%s', $tableName, $columnName),
        );
        $this->enumMetaDataProvider->method('addEnumCheckConstraintSQL')->willReturnCallback(
            static fn (string $tableName, string $columnName, string $_, array $newEnumValues): string => \sprintf(
                'Add constraint %s_%s (%s)',
                $tableName,
                $columnName,
                implode(' ', $newEnumValues),
            ),
        );

        // UP
        static::assertSame(
            [
                'ALTER TABLE myTable ADD newColumn VARCHAR NOT NULL',
                'ALTER TABLE myTable DROP droppedColumn',
                'Add constraint myTable_newColumn (newA newB)',
                'Drop constraint myTable_changedColumn',
                'Add constraint myTable_changedColumn (changedA changedB)',
            ],
            $this->postgreSQLEnumPlatform->getAlterTableSQL(new TableDiff(
                $table,
                [
                    new Column('newColumn', new StringType()),
                ],
                [
                    'changedColumn' => new ColumnDiff(
                        new Column('changedColumn', new StringType()),
                        new Column('changedColumn', new StringType()),
                    ),
                ],
                [
                    new Column('droppedColumn', new StringType()),
                ],
            )),
        );

        // DOWN
        static::assertSame(
            [
                'ALTER TABLE myTable ADD droppedColumn VARCHAR NOT NULL',
                'ALTER TABLE myTable DROP newColumn',
                'Add constraint myTable_droppedColumn (dropA dropB)',
                'Drop constraint myTable_changedColumn',
                'Add constraint myTable_changedColumn (oldA oldB)',
            ],
            $this->postgreSQLEnumPlatform->getAlterTableSQL(new TableDiff(
                $table,
                [
                    new Column('droppedColumn', new StringType()),
                ],
                [
                    'changedColumn' => new ColumnDiff(
                        new Column('changedColumn', new StringType()),
                        new Column('changedColumn', new StringType()),
                    ),
                ],
                [
                    new Column('newColumn', new StringType()),
                ],
            )),
        );
    }

    public function testUnquoteSingleIdentifier(): void
    {
        static::assertSame('columnName', $this->postgreSQLEnumPlatform->unquoteSingleIdentifier(
            $this->postgreSQLEnumPlatform->quoteSingleIdentifier('columnName')
        ));
        static::assertSame('foo"foo', $this->postgreSQLEnumPlatform->unquoteSingleIdentifier(
            $this->postgreSQLEnumPlatform->quoteSingleIdentifier('foo"foo')
        ));

        static::assertSame('', $this->postgreSQLEnumPlatform->unquoteSingleIdentifier(''));
        static::assertSame('"', $this->postgreSQLEnumPlatform->unquoteSingleIdentifier('"'));
    }
}
