<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql;

use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Sql\Alias;
use P3\Db\Sql\Driver;
use PHPUnit\Framework\TestCase;
use P3\Db\Exception\RuntimeException;

class AliasTest extends TestCase
{
    public function setUp(): void
    {
    }

    public function tearDown()
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
