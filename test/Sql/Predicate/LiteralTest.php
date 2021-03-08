<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Predicate;

use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Sql\Predicate\Literal;
use P3\Db\Sql\Driver;
use PHPUnit\Framework\TestCase;
use P3\Db\Exception\RuntimeException;

class LiteralTest extends TestCase
{
    use \P3\DbTest\DiscloseTrait;

    public function setUp(): void
    {
    }

    public function tearDown()
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
