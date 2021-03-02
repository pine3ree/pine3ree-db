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
     * @dataProvider provideInvalidAliases
     */
    public function testAliasConstructorRisesExceptionWIthInnvalidAliases($alias)
    {
        $this->expectException(InvalidArgumentException::class);
        $aliasObj = new Alias($alias);
    }

    /**
     * @dataProvider provideAliases
     */
    public function testAlias(string $alias, string $expected)
    {
        self::assertEquals($expected, (new Alias($alias))->getSQL(Driver::ansi()));
    }

    public function provideAliases(): array
    {
        return [
            ['t0', '"t0"'],
            ['_t', '"_t"'],
            ['some.alias', '"some.alias"'],
        ];
    }

    public function provideInvalidAliases(): array
    {
        return [
            ['"t0"'],
            ['4t'],
            ['"some.alias"'],
        ];
    }
}
