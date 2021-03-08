<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Driver;

use P3\Db\Sql\Driver;
use P3\Db\Sql\Statement\Select;
use PHPUnit\Framework\TestCase;
use P3\Db\Exception\RuntimeException;

class SqlSrvTest extends TestCase
{
    /** @var Driver\SqlSrv */
    private $driver;

    public function setUp(): void
    {
        $this->driver = new Driver\SqlSrv();
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
        self::assertSame('', $this->driver->getLimitSQL($select));

        $select = new Select();
        $select->from('product')->orderBy('price');
        $select->limit(10);
        self::assertStringMatchesFormat(
            "OFFSET (0) ROWS FETCH FIRST (:fetch%x) ROWS ONLY",
            $this->driver->getLimitSQL($select)
        );

        $select = new Select();
        $select->from('product')->orderBy('price');
        $select->limit(10)->offset(100);
        self::assertStringMatchesFormat(
            "OFFSET (:offset%x) ROWS FETCH NEXT (:fetch%x) ROWS ONLY",
            $this->driver->getLimitSQL($select)
        );

        $select = new Select();
        $select->orderBy('price');
        $select->offset(100);
        self::assertStringMatchesFormat("OFFSET (:offset%x) ROWS", $this->driver->getLimitSQL($select));
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
