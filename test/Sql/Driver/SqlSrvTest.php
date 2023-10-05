<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace pine3ree\DbTest\Sql\Driver;

use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Statement\Select;
use PHPUnit\Framework\TestCase;
use pine3ree\Db\Exception\RuntimeException;

class SqlSrvTest extends TestCase
{
    /** @var Driver\SqlSrv */
    private $driver;

    public function setUp(): void
    {
        $this->driver = new Driver\SqlSrv();
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
            ['username', '[username]'],
            ['[username]', '[username]'],
            ['u.username', '[u].[username]'],
            ['[u].[username]', '[u].[username]'],
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
            ['u', '[u]'],
            ['[u]', '[u]'],
            ['some.alias', '[some.alias]'],
            ['[some.other.alias]', '[some.other.alias]'],
        ];
    }

    public function testGetLimitSQL()
    {
        $select = new Select();
        $params = new Params();

        self::assertSame('', $this->driver->getLimitSQL($select, $params));

        $select = new Select();
        $select->from('product')->orderBy('price');
        $select->limit(10);
        self::assertStringMatchesFormat(
            "OFFSET 0 ROWS%wFETCH FIRST :fetch%d ROWS ONLY",
            $this->driver->getLimitSQL($select, $params)
        );

        $select = new Select();
        $select->from('product')->orderBy('price');
        $select->limit(10)->offset(100);
        self::assertStringMatchesFormat(
            "OFFSET :offset%d ROWS%wFETCH NEXT :fetch%d ROWS ONLY",
            $this->driver->getLimitSQL($select, $params)
        );

        $select = new Select();
        $select->orderBy('price');
        $select->offset(100);
        self::assertStringMatchesFormat(
            "OFFSET :offset%d ROWS",
            $this->driver->getLimitSQL($select, $params)
        );
    }

    public function testGetLimitSqlWithoutOrderByRaisesException()
    {
        $select = new Select();
        $select->from('product')->limit(10);
        $this->expectException(RuntimeException::class);
        $select->getSQL($this->driver);
    }

    public function testZeroLimitRaisesException()
    {
        $select = new Select();
        $select->from('product')->orderBy('price');
        $select->limit(0);
        $this->expectException(RuntimeException::class);
        $select->getSQL($this->driver);
    }
}
