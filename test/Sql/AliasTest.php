<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\DbTest\Sql;

use PHPUnit\Framework\TestCase;
use pine3ree\Db\Exception\InvalidArgumentException;
use pine3ree\Db\Exception\RuntimeException;
use pine3ree\Db\Sql\Alias;
use pine3ree\Db\Sql\Driver;

class AliasTest extends TestCase
{
    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    /**
     * @dataProvider provideInvalidAliases
     */
    public function testAliasConstructorWithInvalidAliasRaisesException($alias)
    {
        $this->expectException(InvalidArgumentException::class);
        new Alias($alias);
    }

    /**
     * @dataProvider provideAliases
     */
    public function testAlias(string $alias, string $expected)
    {
        $alias = new Alias($alias);
        self::assertSame($expected, $sql = ($alias)->getSQL(Driver::ansi()));
        self::assertSame($sql, $alias->getSQL(Driver::ansi()));
    }

    public function provideAliases(): array
    {
        return [
            ['t0', '"t0"'],
            ['_t', '"_t"'],
            ['some.alias', '"some.alias"'],
        ];
    }

    /**
     * @dataProvider provideInvalidAliases
     */
    public function testInvalidAliasRaisesException(string $alias)
    {
        $this->expectException(InvalidArgumentException::class);
        new Alias($alias);
    }

    public function provideInvalidAliases(): array
    {
        return [
            [''],
            ['"t0"'],
            ['4t'],
            ['"some.alias"'],
        ];
    }

    public function testMagicGetter()
    {
        $aliasArg = 't';
        $aliasObj = new Alias($aliasArg);

        self::assertSame($aliasArg, $aliasObj->alias);

        $this->expectException(RuntimeException::class);
        $aliasObj->nonExistentProperty;
    }
}
