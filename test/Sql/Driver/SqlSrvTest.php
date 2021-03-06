<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Driver;

use PHPUnit\Framework\TestCase;
use P3\Db\Sql\Driver;

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
}
