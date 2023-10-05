<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace pine3ree\DbTest\Sql\Predicate;

use pine3ree\Db\Exception\InvalidArgumentException;
use pine3ree\Db\Sql\Predicate\Expression;
use pine3ree\Db\Sql\Alias;
use pine3ree\Db\Sql\Identifier;
use PHPUnit\Framework\TestCase;
use pine3ree\Db\Exception\RuntimeException;

class ExpressionTest extends TestCase
{
    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    public function testExpressionConstructorWithEmptyStringExpressionRaisesException()
    {
        $this->expectException(InvalidArgumentException::class);
        new Expression('');
    }

    public function testExpressionConstructorWithEmptyTrimmedStringExpressionRaisesException()
    {
        $this->expectException(InvalidArgumentException::class);
        new Expression('  ');
    }

    public function testExpressionConstructorWithUnmatchedPlaceholderRaisesException()
    {
        $this->expectException(InvalidArgumentException::class);
        new Expression("id > {id}", ['published' => true]);
    }

    public function testExpressionWithInvalidSubstitutionValueTypeRaisesException()
    {
        $this->expectException(InvalidArgumentException::class);
        new Expression("id > {id}", ['id' => new \stdClass()]);
    }

    /**
     * @dataProvider provideExpressions
     */
    public function testGetSQL(string $expression, $substs, string $expectedSQL)
    {
        $expression = new Expression($expression, $substs);
        self::assertStringMatchesFormat($expectedSQL, $sql = $expression->getSQL());
        // cached SQL
        self::assertSame($sql, $expression->getSQL());
    }

    public function provideExpressions(): array
    {
        return [
            ["id > {id}", ['id' => 42], "id > :expr%d"],
            ["id > 42", [], "id > 42"],
            ["id > {categoryId}", ['categoryId' => new Identifier('c.id')], 'id > "c"."id"'],
            ["id > {minPrice}", ['minPrice' => new Alias('minPrice')], 'id > "minPrice"'],
        ];
    }

    public function testMagicGetter()
    {
        $expressionArg = "CONCAT({prefix}, 'name', {suffix})";
        $substitutions = [
            'prefix' => 'pre-',
            'suffix' => '-post',
        ];

        $expressionObj = new Expression($expressionArg, $substitutions);

        self::assertSame($expressionArg, $expressionObj->expression);
        self::assertSame($substitutions, $expressionObj->substitutions);

        $this->expectException(RuntimeException::class);
        $expressionObj->nonexistentProperty;
    }

    public function testCallNoOpMethods()
    {
        $expressionArg = "id > {id}";
        $substitutions = ['id' => 42];
        $expressionObj = new Expression($expressionArg, $substitutions);

        $expressionObj = clone $expressionObj;

        self::assertSame($expressionArg, $expressionObj->expression);
        self::assertSame($substitutions, $expressionObj->substitutions);
    }
}
