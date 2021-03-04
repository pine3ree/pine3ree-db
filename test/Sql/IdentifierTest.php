<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql;

use InvalidArgumentException;
use P3\Db\Sql\Identifier;
use P3\Db\Sql\Driver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
    public function testIdentifierConstructorWithInvalidIdentifierRaisesException($identifier)
    {
        $this->expectException(InvalidArgumentException::class);
        $identifierObj = new Identifier($identifier);
    }

    /**
     * @dataProvider provideIdentifiers
     */
    public function testIdentifier(string $identifier, string $expected)
    {
        self::assertSame($expected, (new Identifier($identifier))->getSQL(Driver::ansi()));
    }

    public function provideIdentifiers(): array
    {
        return [
            ['cart', '"cart"'],
            ['_cart_to_product', '"_cart_to_product"'],
            ['product.price', '"product"."price"'],
        ];
    }

    /**
     * @dataProvider provideInvalidIdentifiers
     */
    public function testInvalidIdentifierRaisesException(string $identifier)
    {
        $this->expectException(InvalidArgumentException::class);
        $identifierObj = new Identifier($identifier);
    }

    public function provideInvalidIdentifiers(): array
    {
        return [
            [''],
            ['"cart"'],
            ['`cart`'],
            ['"product.id"'],
        ];
    }

    public function testMagicGetter()
    {
        $identifierArg = 't0.id';
        $identifierObj = new Identifier($identifierArg);

        self::assertSame($identifierArg, $identifierObj->identifier);

        $this->expectException(RuntimeException::class);
        $identifierObj->nonexistentProperty;
    }
}
