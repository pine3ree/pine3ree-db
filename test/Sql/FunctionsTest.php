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
use pine3ree\Db\Sql\Alias;
use pine3ree\Db\Sql\Expression;
use pine3ree\Db\Sql\Identifier;
use pine3ree\Db\Sql\Literal;

use function pine3ree\Db\Sql\alias;
use function pine3ree\Db\Sql\expression;
use function pine3ree\Db\Sql\identifier;
use function pine3ree\Db\Sql\literal;

class FunctionsTest extends TestCase
{
    public function setUp(): void
    {
    }

    public function tearDown(): void
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
