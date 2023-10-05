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
use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\Identifier;

class IdentifierTest extends TestCase
{
    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    /**
     * @dataProvider provideInvalidIdentifiers
     */
    public function testIdentifierConstructorWithInvalidIdentifierRaisesException($identifier)
    {
        $this->expectException(InvalidArgumentException::class);
        new Identifier($identifier);
    }

    /**
     * @dataProvider provideIdentifiers
     */
    public function testIdentifier(string $identifier, string $expected)
    {
        $identifier = new Identifier($identifier);
        self::assertSame($expected, $sql = ($identifier)->getSQL(Driver::ansi()));
        self::assertSame($sql, $identifier->getSQL(Driver::ansi()));
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
        new Identifier($identifier);
    }

    public function provideInvalidIdentifiers(): array
    {
        return [
            [''],
            ['12cart'],
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
