<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Driver;

use PDO;
use PHPUnit\Framework\TestCase;
use P3\Db\Sql\Driver;
use RuntimeException;

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

        $pgsql_dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        $this->pdo = new PDO(
            $pgsql_dsn,
            getenv('TEST_P3_DB_PGSQL_USERNAME'),
            getenv('TEST_P3_DB_PGSQL_PASSWD')
        );

        $this->driver = new Driver\PgSql();
    }

    public function tearDown()
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
            [null, 'NULL'],
            [false, '0'],
            [true, '1'],
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
}
