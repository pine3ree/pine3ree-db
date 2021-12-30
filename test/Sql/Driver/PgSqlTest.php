<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Driver;

use P3\Db\Sql;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Statement\Select;
use PDO;
use PHPUnit\Framework\TestCase;
use P3\Db\Exception\RuntimeException;

use function getenv;

class PgSqlTest extends TestCase
{
    /** @var PDO */
    private $pdo;

    /** @var Driver\PgSql */
    private $driver;

    public function setUp(): void
    {
        if (! getenv('TEST_P3_DB_PGSQL')) {
            $this->markTestSkipped('pdo-pgsql test is not enabled!');
        }

        $host    = getenv('TEST_P3_DB_PGSQL_HOST');
        $port    = getenv('TEST_P3_DB_PGSQL_PORT');
        $dbname  = getenv('TEST_P3_DB_PGSQL_DBNAME');
        $charset = getenv('TEST_P3_DB_PGSQL_CHARSET');

        $pgsql_dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

        $this->pdo = new PDO(
            $pgsql_dsn,
            getenv('TEST_P3_DB_PGSQL_USERNAME'),
            getenv('TEST_P3_DB_PGSQL_PASSWD')
        );

        $this->pdo->query("SET NAMES '{$charset}'");

        $this->driver = new Driver\PgSql();
    }

    public function tearDown(): void
    {
        $this->driver = null;
        $this->pdo = null;
    }

    /**
     * @dataProvider provideIdentifiers
     */
    public function testQuoteIdentifier(string $identifier, ?string $expected)
    {
        self::assertSame($expected, $this->driver->quoteIdentifier($identifier));
    }

    public function provideIdentifiers(): array
    {
        return [
            ['*', '*'],
            ['username', '"username"'],
            ['"username"', '"username"'],
            ['u.username', '"u"."username"'],
            ['"u"."username"', '"u"."username"'],
        ];
    }

    /**
     * @dataProvider provideAliases
     */
    public function testQuoteAlias(string $alias, string $expected)
    {
        self::assertSame($expected, $this->driver->quoteAlias($alias));
    }

    public function provideAliases(): array
    {
        return [
            ['u', '"u"'],
            ['"u"', '"u"'],
            ['some.alias', '"some.alias"'],
            ['"some.other.alias"', '"some.other.alias"'],
        ];
    }

    /**
     * @dataProvider provideNonStringTestValues
     */
    public function testQuoteNonStringValueWithoutConnectionWorks($value, string $expected)
    {
        self::assertSame($expected, $this->driver->quoteValue($value));
    }

    public function testQuoteStringValueWithoutConnectionRaisesException()
    {
        $this->expectException(RuntimeException::class);
        $this->driver->quoteValue("Quote me!");
    }

    /**
     * @dataProvider provideTestValues
     */
    public function testPdoQuoteAnyValueWithConnection($value, string $expected)
    {
        $this->driver->setPDO($this->pdo);
        self::assertSame($expected, $this->driver->quoteValue($value));
    }

    public function provideNonStringTestValues(): array
    {
        return [
            [null, Sql::NULL],
            [false, Sql::FALSE],
            [true, Sql::TRUE],
            [42, '42'],
            [12.345, '12.345'],
        ];
    }

    public function provideTestValues(): array
    {
        return $this->provideNonStringTestValues() + [
            ["abc", "'abc'"],
            ["ab\\c", "'ab\\c'"],
            ["ab'c", "'ab''c'"],
        ];
    }

    public function testGetLimitSQL()
    {
        $select = new Select();
        $params = new Sql\Params();

        self::assertSame('', $this->driver->getLimitSQL($select, $params));

        $select = new Select();
        $select->limit(10);
        self::assertStringMatchesFormat(
            'LIMIT :limit%d',
            $this->driver->getLimitSQL($select, $params)
        );

        $select = new Select();
        $select->limit(10)->offset(100);
        self::assertStringMatchesFormat(
            'LIMIT :limit%d OFFSET :offset%d',
            $this->driver->getLimitSQL($select, $params)
        );

        $select = new Select();
        $select->offset(100);
        self::assertStringMatchesFormat(
            'OFFSET :offset%d',
            $this->driver->getLimitSQL($select, $params)
        );
    }
}
