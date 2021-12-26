<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Driver;

use PDO;
use PHPUnit\Framework\TestCase;
use P3\Db\Sql;
use P3\Db\Sql\Driver;
use P3\Db\Exception\RuntimeException;

use function getenv;

class MySqlTest extends TestCase
{
    /** @var PDO */
    private $pdo;

    /** @var Driver\MySql */
    private $driver;

    public function setUp(): void
    {
        if (! getenv('TEST_P3_DB_MYSQL')) {
            $this->markTestSkipped('pdo-mysql test is not enabled!');
        }

        $host    = getenv('TEST_P3_DB_MYSQL_HOST');
        $port    = getenv('TEST_P3_DB_MYSQL_PORT');
        $dbname  = getenv('TEST_P3_DB_MYSQL_DBNAME');
        $charset = getenv('TEST_P3_DB_MYSQL_CHARSET');

        $mysql_dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        $this->pdo = new PDO(
            $mysql_dsn,
            getenv('TEST_P3_DB_MYSQL_USERNAME'),
            getenv('TEST_P3_DB_MYSQL_PASSWD')
        );

        $this->driver = new Driver\MySql();
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
            ['username', '`username`'],
            ['`username`', '`username`'],
            ['u.username', '`u`.`username`'],
            ['`u`.`username`', '`u`.`username`'],
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
            ['u', '`u`'],
            ['`u`', '`u`'],
            ['some.alias', '`some.alias`'],
            ['`some.other.alias`', '`some.other.alias`'],
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
}
