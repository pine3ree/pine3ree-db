<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace pine3ree\DbTest\Sql\Predicate;

use pine3ree\Db\Exception\InvalidArgumentException;
use pine3ree\Db\Sql\Predicate\Literal;
use pine3ree\Db\Sql\Driver;
use pine3ree\DbTest\DiscloseTrait;
use PHPUnit\Framework\TestCase;
use pine3ree\Db\Exception\RuntimeException;

class LiteralTest extends TestCase
{
    use DiscloseTrait;

    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    /**
     * @dataProvider provideInvalidLiterals
     */
    public function testLiteralConstructorWithInvalidLiteralRaisesException($literal)
    {
        $this->expectException(InvalidArgumentException::class);
        $literalObj = new Literal($literal);
    }

    /**
     * @dataProvider provideLiterals
     */
    public function testLiteral(string $literal, string $expected)
    {
        self::assertSame($expected, (new Literal($literal))->getSQL(Driver::ansi()));
    }

    public function provideLiterals(): array
    {
        return [
            ["CONCAT(`firstname`, ' ', 'lastname')", "CONCAT(`firstname`, ' ', 'lastname')"],
            ["COUNT(*) AS total", "COUNT(*) AS total"],
        ];
    }

    /**
     * @dataProvider provideInvalidLiterals
     */
    public function testInvalidLiteralRaisesException(string $literal)
    {
        $this->expectException(InvalidArgumentException::class);
        $literalObj = new Literal($literal);
    }

    public function provideInvalidLiterals(): array
    {
        return [
            [''],
            ["   "],
        ];
    }

    public function testMagicGetter()
    {
        $literalArg = "COUNT(*)";
        $literalObj = new Literal($literalArg);

        self::assertSame($literalArg, $literalObj->literal);

        $this->expectException(RuntimeException::class);
        $literalObj->nonexistentProperty;
    }

    public function testCallNoOpMethods()
    {
        $literalArg = "COUNT(*)";
        $literalObj = new Literal($literalArg);

        $literalObj = clone $literalObj;
        $this->invokeMethod($literalObj, 'clearSQL');

        self::assertSame($literalArg, $literalObj->literal);
    }
}
