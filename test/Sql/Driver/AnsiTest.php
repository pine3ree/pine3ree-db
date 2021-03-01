<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Driver;

use PHPUnit\Framework\TestCase;
use P3\Db\Sql\Driver;

class AnsiTest extends TestCase
{
    /** @var Driver\Ansi */
    private $driver;

    public function setUp(): void
    {
        $this->driver = new Driver\Ansi();
    }

    public function tearDown()
    {
        $this->driver = null;
    }

    /**
     * @dataProvider provideIdentifiers
     */
    public function testQuoteIdentifier(string $identifier, ?string $expected)
    {
        self::assertEquals($expected, $this->driver->quoteIdentifier($identifier));
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
        self::assertEquals($expected, $this->driver->quoteAlias($alias));
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
     * @dataProvider provideTestValues
     */
    public function testQuoteValue($value, string $expected)
    {
        self::assertEquals($expected, $this->driver->quoteValue($value));
    }

    public function provideTestValues(): array
    {
        return [
            [null, 'NULL'],
            [false, '0'],
            [true, '1'],
            [42, '42'],
            [12.345, '12.345'],
            ["abc", "'abc'"],
            ["ab\\c", "'ab\\\\c'"],
            ["ab'c", "'ab\\'c'"],
            ["ab\nc", "'ab\\nc'"],
            ["ab\"c", "'ab\\\"c'"],
        ];
    }
}
