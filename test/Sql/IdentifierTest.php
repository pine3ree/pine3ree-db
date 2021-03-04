<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Driver;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use P3\Db\Sql\Identifier;
use P3\Db\Sql\Driver;

class IdentifierTest extends TestCase
{
    public function setUp(): void
    {
    }

    public function tearDown()
    {
    }

    /**
     * @dataProvider provideInvalidIdentifiers
     */
    public function testIdentifierConstructorRisesExceptionWIthInnvalidIdentifiers($alias)
    {
        $this->expectException(InvalidArgumentException::class);
        $aliasObj = new Identifier($alias);
    }

    /**
     * @dataProvider provideIdentifiers
     */
    public function testIdentifier(string $alias, string $expected)
    {
        self::assertSame($expected, (new Identifier($alias))->getSQL(Driver::ansi()));
    }

    public function provideIdentifiers(): array
    {
        return [
            ['t0', '"t0"'],
            ['_t', '"_t"'],
            ['tb.column', '"tb"."column"'],
        ];
    }

    public function provideInvalidIdentifiers(): array
    {
        return [
            ['"t0"'],
            ['4t'],
            ['"some.column"'],
        ];
    }
}
