<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Driver;

use P3\Db\Sql;
use P3\Db\Sql\Driver;
use PDO;
use PHPUnit\Framework\TestCase;
use P3\Db\Exception\RuntimeException;

class SqliteTest extends TestCase
{
    /** @var PDO */
    private $pdo;

    /** @var Driver\Sqlite */
    private $driver;

    public function setUp(): void
    {
        $sqlite_dsn = "sqlite::memory:";

        $this->pdo = new PDO($sqlite_dsn);
        $this->driver = new Driver\Sqlite();
    }

    public function tearDown()
    {
        $this->driver = null;
        $this->pdo = null;
    }

    /**
     * @dataProvider provideTestIdentifiers
     */
    public function testQuoteIdentifier(string $identifier, string $expected)
    {
        self::assertSame($expected, $this->driver->quoteIdentifier($identifier));
    }

    public function provideTestIdentifiers(): array
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
     * @dataProvider provideTestAliases
     */
    public function testQuoteAlias(string $alias, string $expected)
    {
        self::assertSame($expected, $this->driver->quoteAlias($alias));
    }

    public function provideTestAliases(): array
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
}
