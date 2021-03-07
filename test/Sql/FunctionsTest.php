<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql;

use InvalidArgumentException;
use P3\Db\Sql\Alias;
use P3\Db\Sql\Expression;
use P3\Db\Sql\Identifier;
use P3\Db\Sql\Literal;
use PHPUnit\Framework\TestCase;

use function P3\Db\Sql\alias;
use function P3\Db\Sql\expr;
use function P3\Db\Sql\expression;
use function P3\Db\Sql\identifier;
use function P3\Db\Sql\literal;

class FunctionsTest extends TestCase
{
    public function setUp(): void
    {
    }

    public function tearDown()
    {
    }

    public function testCreateInvalidAliasRaisesException()
    {
        $this->expectException(InvalidArgumentException::class);
        alias("?");
    }

    public function testCreateValidAlias()
    {
        $alias = alias('totPrice');
        self::assertInstanceOf(Alias::class, $alias);
        self::assertSame('"totPrice"', $alias->getSQL());
    }

    public function testCreateExpression()
    {
        $expression = expression("SUM(price) >= {minPrice}", [
            'minPrice' => 123.45,
        ]);
        self::assertInstanceOf(Expression::class, $expression);
        self::assertStringStartsWith('SUM(price) >= :expr', $expression->getSQL());

        $expr = expr("SUM(price) <= {maxPrice}", [
            'maxPrice' => 543.21,
        ]);
        self::assertInstanceOf(Expression::class, $expr);
        self::assertStringStartsWith('SUM(price) <= :expr', $expr->getSQL());
    }

    public function testCreateInvalidIdentifierRaisesException()
    {
        $this->expectException(InvalidArgumentException::class);
        identifier("?");
    }

    public function testCreateValidIdentifier()
    {
        $identifier = identifier('t.id');
        self::assertInstanceOf(Identifier::class, $identifier);
        self::assertSame('"t"."id"', $identifier->getSQL());
    }

    public function testCreateLiteral()
    {
        $literal = literal("(TRUE IS NOT FALSE)");
        self::assertInstanceOf(Literal::class, $literal);
        self::assertSame('(TRUE IS NOT FALSE)', $literal->getSQL());
    }
}
