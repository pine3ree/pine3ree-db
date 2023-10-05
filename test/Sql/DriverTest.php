<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\DbTest\Sql;

use PDO;
use PHPUnit\Framework\TestCase;
use pine3ree\Db\Exception\InvalidArgumentException;
use pine3ree\Db\Exception\RuntimeException;
use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Driver;
use pine3ree\DbTest\DiscloseTrait;
use Prophecy\PhpUnit\ProphecyTrait;
use stdClass;

use function setlocale;
use function trait_exists;

use const LC_NUMERIC;

// @codingStandardsIgnoreStart
if (trait_exists(ProphecyTrait::class)) {
    class DriverTestBase extends TestCase
    {
       use ProphecyTrait;
    }
} else {
    class DriverTestBase extends TestCase
    {
    }
}
// @codingStandardsIgnoreEnd

// @codingStandardsIgnoreLine
class DriverTest extends \pine3ree\DbTest\Sql\DriverTestBase
{
    use DiscloseTrait;

    /**
     * @var Driver
     */
    protected $driver;

    public function setUp(): void
    {
        $this->driver = $this->createInstance();
    }

    public function tearDown(): void
    {
    }

    private function createInstance(PDO $pdo = null, string $ql = "`", string $qr = "`", string $qv = "'"): Driver
    {
        return new class ($pdo, $ql, $qr, $qv) extends Driver {
            public function getName(): string
            {
                if (isset($this->name)) {
                    return $this->name;
                }

                parent::getName();
                return $this->name = 'driver';
            }
        };
    }

    /**
     * @dataProvider provideInvalidQuotingChars
     */
    public function testInvalidQuotingCharsRaisesException(string $ql, string $qr, string $v)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->createInstance(null, $ql, $qr, $v);
    }

    public function provideInvalidQuotingChars(): array
    {
        return [
            ["", "", ""],
            ["`", "", ""],
            ["", "`", ""],
            ["", "", "'"],
            ["\\", "", ""],
            ["", "\\", ""],
            ["", "\\", "\\"],
        ];
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
     * @dataProvider provideQuotedValues
     */
    public function testUnquote(string $quoted, string $expected)
    {
        self::assertSame($expected, $this->driver->unquote($quoted));
    }

    public function provideQuotedValues(): array
    {
        return [
            ['`t0`', 't0'],
            ['`t0`.`id`', 't0.id'],
            ['`some.alias`', 'some.alias'],
        ];
    }

    /**
     * @dataProvider provideNonStringTestValues
     */
    public function testQuoteNonStringValueWithoutConnectionWorks($value, string $expected)
    {
        self::assertSame($expected, $this->driver->quoteValue($value));
    }

    public function testQuoteFloatValueIsNotLocaleDependent()
    {
        $lc_numeric = setlocale(LC_NUMERIC, 0);

        setlocale(LC_NUMERIC, 'C');
        self::assertSame('1.23', $this->driver->quoteValue(1.23));

        setlocale(LC_NUMERIC, 'it_IT.UTF-8');
        self::assertSame('1.23', $this->driver->quoteValue(1.23));

        setlocale(LC_NUMERIC, 'it_CH.UTF-8');
        self::assertSame('1.23', $this->driver->quoteValue(1.23));

        setlocale(LC_NUMERIC, 'de_DE.UTF-8');
        self::assertSame('1.23', $this->driver->quoteValue(1.23));

        setlocale(LC_NUMERIC, 'fr_FR.UTF-8');
        self::assertSame('1.23', $this->driver->quoteValue(1.23));

        setlocale(LC_NUMERIC, $lc_numeric);
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

    /**
     * @dataProvider provideUnsupportedValues
     */
    public function testQuoteUnsupportedValueTypeRaisesException($value)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->driver->quoteValue($value);
    }

    public function provideUnsupportedValues(): array
    {
        return [
            [new stdClass()],
            [[1, 2, 3]],
        ];
    }

    public function testQuoteStringValueWithConnectionRaisesExceptionIfPdoFails()
    {
        $str = '?#?';

        $pdo = $this->prophesize(PDO::class);
        $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)->willReturn('fake');
        $pdo->quote($str, PDO::PARAM_STR)->willReturn(false);

        $driver = $this->createInstance($pdo->reveal());

        $this->expectException(RuntimeException::class);
        $driver->quoteValue($str);
    }

    public function testQuoteStringValueWithConnectionUsesPdoQuote()
    {
        $str = 'UNQUOTED';

        $pdo = $this->prophesize(PDO::class);
        $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)->willReturn('fake');
        $pdo->quote($str, PDO::PARAM_STR)->willReturn('QUOTED');

        $driver = $this->createInstance($pdo->reveal());

        self::assertSame('QUOTED', $driver->quoteValue($str));
    }

    public function testThatNonExistentElementMethodCallRaisesExceptionAndGetsCached()
    {
        $driver = $this->createInstance();
        $literal = new Sql\Literal("CONCAT('prefix-', `name`, '-suffix')");
        $this->expectException(RuntimeException::class);
        $this->invokeMethod($driver, 'call', $literal, 'doSomething');
    }

    public function testMagicGetter()
    {
        $driver = $this->createInstance(null, '#', '+', '`');

        self::assertSame('#', $driver->ql);
        self::assertSame('+', $driver->qr);
        self::assertSame('`', $driver->qv);
        self::assertSame('driver', $driver->name);

        $this->expectException(RuntimeException::class);
        $driver->nonExistentProperty;
    }
}
