<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest;

//use PDO;
use PHPUnit\Framework\TestCase;
use P3\Db\Sql;
use P3\Db\Sql\Expression;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Statement\Delete;
use P3\Db\Sql\Statement\Insert;
use P3\Db\Sql\Statement\Select;
use P3\Db\Sql\Statement\Update;

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
     * @dataProvider provideSqls
     */
    public function testIsEmptySQL($sql, bool $expected)
    {
        self::assertEquals($expected, Sql::isEmptySQL($sql));
    }

    public function provideSqls(): array
    {
        return [
            [null, true],
            ['', true],
            [42, true],
            [new \stdClass(), true],
        ];
    }

    /**
     * @dataProvider providePredicates
     */
    public function testIsEmptyPredicate($predicate, bool $checkEmptySet, bool $expected)
    {
        self::assertEquals($expected, Sql::isEmptyPredicate($predicate, $checkEmptySet));
    }

    public function providePredicates(): array
    {
        return [
            [null, false, true],
            ['', false, true],
            [42, false, true],
            [new \stdClass(), false, true],
            [new Sql\Predicate\IsFalse('enabled'), false, false],
            [new Sql\Predicate\Between('id', 1, 42), false, false],
            [new Sql\Predicate\Set(), false, false],
            [new Sql\Predicate\Set(), true, true],
            [new Sql\Predicate\Set("enabled IS TRUE"), true, false],
            [new Sql\Predicate\Set(["enabled IS FALSE"]), true, false],
        ];
    }
}
