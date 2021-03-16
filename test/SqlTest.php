<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest;

use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Expression;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Params;
use P3\Db\Sql\Statement\Delete;
use P3\Db\Sql\Statement\Insert;
use P3\Db\Sql\Statement\Select;
use P3\Db\Sql\Statement\Update;
use PHPUnit\Framework\TestCase;

/**
 * Class SqlTest
 */
class SqlTest extends TestCase
{
    public function setUp(): void
    {
    }

    public function tearDown()
    {
    }

    public function testStatementCreationMethodsCreateCorrectSqlStatements()
    {
        self::assertInstanceOf(Delete::class, Sql::delete());
        self::assertInstanceOf(Insert::class, Sql::insert());
        self::assertInstanceOf(Select::class, Sql::select());
        self::assertInstanceOf(Update::class, Sql::update());
    }

    public function testExpressionCreationMethods()
    {
        self::assertInstanceOf(Expression::class, Sql::expression("A != B"));
        self::assertInstanceOf(Expression::class, Sql::expr("C != D"));
        self::assertInstanceOf(Literal::class, Sql::literal("E = E"));
    }

    /**
     * @dataProvider provideInvalidJoinTypes
     */
    public function testIsValidJoinWithInvalidJoinType(string $join)
    {
        self::assertFalse(Sql::isValidJoin($join));
    }

    public function provideInvalidJoinTypes(): array
    {
        return [
            ['A'],
            ['B'],
            ['INNER OUTER'],
            ['EXTREME'],
            ['GLUE'],
        ];
    }

    /**
     * @dataProvider provideValidJoinTypes
     */
    public function testIsValidJoinWithValidJoinType(string $join)
    {
        self::assertTrue(Sql::isValidJoin($join));
    }

    public function provideValidJoinTypes(): array
    {
        $types = [];
        foreach (Sql::JOIN_TYPES as $type) {
            $types[] = [$type];
        }

        return $types;
    }

    /**
     * @dataProvider provideSupportedOperators
     */
    public function testSupportedOperatorsCheck(string $operator)
    {
        self::assertTrue(Sql::isSupportedOperator($operator));
    }

    public function provideSupportedOperators(): array
    {
        $values = [];
        foreach (Sql::OPERATORS as $operator) {
            $values[] = [$operator];
        }

        return $values;
    }

    /**
     * @dataProvider provideUnsupportedOperatorStrings
     */
    public function testUnsupportedOperatorsCheck(string $operator)
    {
        self::assertFalse(Sql::isSupportedOperator($operator));
    }

    public function provideUnsupportedOperatorStrings(): array
    {
        return [['^'], ['"'], ['~'], [';'], ['HELLO']];
    }

    public function testCreateInvalidAliasRaisesException()
    {
        $this->expectException(InvalidArgumentException::class);
        $alias = Sql::alias("?");
    }

    public function testCreateValidAlias()
    {
        $alias = Sql::alias('totPrice');
        self::assertSame('"totPrice"', $alias->getSQL());
    }

    public function testCreateInvalidIdentifierRaisesException()
    {
        $this->expectException(InvalidArgumentException::class);
        $identifier = Sql::identifier("?");
    }

    public function testCreateValidIdentifier()
    {
        $identifier = Sql::identifier('t.id');
        self::assertSame('"t"."id"', $identifier->getSQL());
    }

    public function testCreateLiteral()
    {
        $literal = Sql::literal("(TRUE IS NOT FALSE)");
        self::assertSame('(TRUE IS NOT FALSE)', $literal->getSQL());
    }

    public function testCreateExpression()
    {
        $expr1 = Sql::expression("SUM(price) >= {minPrice}", [
            'minPrice' => 123.45,
        ]);
        self::assertSame('SUM(price) >= :expr1', $expr1->getSQL());
        self::assertSame('SUM(price) >= ?', $expr1->getSQL(null, new Params(Params::MODE_POSITIONAL)));

        $expr2 = Sql::expr("SUM(price) <= {maxPrice}", [
            'maxPrice' => 543.21,
        ]);
        self::assertSame('SUM(price) <= :expr1', $expr2->getSQL());
        self::assertSame('SUM(price) <= ?', $expr2->getSQL(null, new Params(Params::MODE_POSITIONAL)));
    }
}
