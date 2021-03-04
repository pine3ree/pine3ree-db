<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Driver;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use P3\Db\Sql\Alias;
use P3\Db\Sql\Driver;

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
    public function testAliasConstructorRaisesExceptionWIthInnvalidAliases($alias)
    {
        $this->expectException(InvalidArgumentException::class);
        $aliasObj = new Alias($alias);
    }

    /**
     * @dataProvider provideAliases
     */
    public function testAlias(string $alias, string $expected)
    {
        self::assertSame($expected, (new Alias($alias))->getSQL(Driver::ansi()));
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
        $alias = new Alias($alias);
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

    /**
     * @dataProvider provideInvalidAliases
     */
    public function testGetNonExistentPropertyRaisesException(string $alias)
    {
        $this->expectException(InvalidArgumentException::class);
        $alias = new Alias($alias);
    }
}
