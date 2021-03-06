<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Driver;

use PHPUnit\Framework\TestCase;
use P3\Db\Sql;
use P3\Db\Sql\Driver;
use RuntimeException;

class DriverTest extends TestCase
{
    /**
     * @var Driver
     */
    protected $driver;

    public function setUp(): void
    {
        $this->driver = new class (null, "`", "`", "'") extends Driver {
        };
    }

    public function tearDown()
    {
    }

    private function getInstance(): Driver
    {
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
}
