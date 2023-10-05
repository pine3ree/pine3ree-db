<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\DbTest\Sql\Driver;

use PDO;
use PHPUnit\Framework\TestCase;
use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\Statement\Select;
use pine3ree\DbTest\DiscloseTrait;
use Prophecy\PhpUnit\ProphecyTrait;

// @codingStandardsIgnoreStart
if (trait_exists(ProphecyTrait::class)) {
    abstract class AnsiTestBase extends TestCase
    {
       use ProphecyTrait;
    }
} else {
    abstract class AnsiTestBase extends TestCase
    {
    }
}
// @codingStandardsIgnoreEnd

// @codingStandardsIgnoreLine
class AnsiTest extends \pine3ree\DbTest\Sql\Driver\AnsiTestBase
{
    use DiscloseTrait;

    /** @var Driver\Ansi */
    private $driver;

    public function setUp(): void
    {
        $this->driver = new Driver\Ansi();
    }

    public function tearDown(): void
    {
        $this->driver = null;
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
     * @dataProvider provideTestValues
     */
    public function testQuoteValue($value, string $expected)
    {
        self::assertSame($expected, $this->driver->quoteValue($value));
    }

    public function provideTestValues(): array
    {
        return [
            [null, Sql::NULL],
            [false, Sql::FALSE],
            [true, Sql::TRUE],
            [42, '42'],
            [12.345, '12.345'],
            ["abc", "'abc'"],
            ["ab\\c", "'ab\\\\c'"],
            ["ab'c", "'ab\\'c'"],
            ["ab\nc", "'ab\\nc'"],
            ["ab\"c", "'ab\\\"c'"],
        ];
    }

    public function testSetPdoIsNoOp()
    {
        $pdo = $this->prophesize(PDO::class);
        $this->driver->setPDO($pdo->reveal());
        self::assertNull($this->getPropertyValue($this->driver, 'pdo'));
    }

    /**
     * @dataProvider provideStringValues
     */
    public function testQuoteStringValue(string $string, string $expected)
    {
        self::assertSame($expected, $this->driver->quoteStringValue($string));
    }

    public function provideStringValues(): array
    {
        return [
            ["ab'c", "'ab\'c'"],
            ['ab"c', "'ab\\\"c'"],
        ];
    }

    public function testGetLimitSQL()
    {
        $params = $this->prophesize(Sql\Params::class)->reveal();

        $select = new Select('*', 'product');
        self::assertSame('', $this->driver->getLimitSQL($select, $params));

        $select = new Select('*', 'product');
        $select->limit(10);
        self::assertSame('[LIMIT 10]', $this->driver->getLimitSQL($select, $params));

        $select = new Select('*', 'product');
        $select->limit(10)->offset(100);
        self::assertSame('[LIMIT 10 OFFSET 100]', $this->driver->getLimitSQL($select, $params));

        $select = new Select('*', 'product');
        $select->offset(100);
        self::assertSame("[LIMIT " . PHP_INT_MAX .  " OFFSET 100]", $this->driver->getLimitSQL($select, $params));
    }
}
