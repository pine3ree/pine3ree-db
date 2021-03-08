<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Predicate;

use ArrayObject;
use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Predicate\Comparison;
use PHPUnit\Framework\TestCase;
use stdClass;

class ComparisonTest extends TestCase
{
    public function setUp(): void
    {
    }

    public function tearDown()
    {
    }

    /**
     * @dataProvider provideUnsupportedCtorValues
     */
    public function testContructorWithUnsupportedValueRaisesException($value)
    {
        $this->expectException(InvalidArgumentException::class);
        $predicate = new Comparison('tb.column', '=', $value);
    }

    public function provideUnsupportedCtorValues(): array
    {
        return [
            [new stdClass()],
            [new ArrayObject()],
        ];
    }

    /**
     * @dataProvider provideUnsupportedOperators
     */
    public function testContructorWithUnsupportedOperatorRaisesException($operator)
    {
        $this->expectException(InvalidArgumentException::class);
        $predicate = new Comparison('tb.column', $operator, null);
    }

    public function provideUnsupportedOperators(): array
    {
        return [
            ['+'],
            ['?'],
            ['1'],
            ['N-O-T'],
            ['I-s'],
            ['*'],
        ];
    }

    /**
     * @dataProvider provideUnsupportedOperatorsForNull
     */
    public function testGetSqlUsingUnsupportedOperatorForNullValueRaisesException($operator)
    {
        $predicate = new Comparison('tb.column', $operator, null);
        $this->expectException(InvalidArgumentException::class);
        $predicate->getSQL();
    }

    public function provideUnsupportedOperatorsForNull(): array
    {
        return [
            ['<'],
            ['<='],
            ['>='],
            ['>'],
        ];
    }

    public function testThatLiteralValuesAreSupported()
    {
        $literal = "CONCAT('A', 'B')";
        $predicate = new Comparison('tb.column', '=', new Sql\Literal($literal));
        self::assertSame('"tb"."column" = ' . $literal, $predicate->getSQL());
    }

    public function testThatIdentifierValuesAreSupportedAndProperlyQuoted()
    {
        $predicate = new Comparison('t0.column', '=', new Sql\Identifier("t1.column"));
        self::assertSame('"t0"."column" = "t1"."column"', $predicate->getSQL());

        $predicate = new Comparison('t0.column1', '=', new Sql\Identifier("column2"));
        self::assertSame('"t0"."column1" = "column2"', $predicate->getSQL());
    }

    public function testThatAliasValuesAreSupportedAndProperlyQuoted()
    {
        $predicate = new Comparison('tb.column', '=', new Sql\Alias("some.alias"));
        self::assertSame('"tb"."column" = "some.alias"', $predicate->getSQL());
    }

    public function testNullValue()
    {
        $predicate = new Comparison('tb.column', '=', null);
        self::assertSame('"tb"."column" IS NULL', $predicate->getSQL());

        $predicate = new Comparison('tb.column', '!=', null);
        self::assertSame('"tb"."column" IS NOT NULL', $predicate->getSQL());

        $predicate = new Comparison('tb.column', '<>', null);
        self::assertSame('"tb"."column" IS NOT NULL', $predicate->getSQL());
    }

    /**
     * @dataProvider provideScalarValues
     */
    public function testEqualWithScalarValue($value)
    {
        $predicate = new Comparison("tb.column", '=', $value);
        self::assertStringMatchesFormat('"tb"."column" = :eq%x', $predicate->getSQL());
    }

    /**
     * @dataProvider provideScalarValues
     */
    public function testNotEqualWithScalarValue($value)
    {
        $predicate = new Comparison("tb.column", '!=', $value);
        self::assertStringMatchesFormat('"tb"."column" != :neq%x', $predicate->getSQL());

        $predicate = new Comparison("tb.column", '<>', $value);
        self::assertStringMatchesFormat('"tb"."column" <> :ne%x', $predicate->getSQL());
    }

    /**
     * @dataProvider provideScalarValues
     */
    public function testLessThanWithScalarValue($value)
    {
        $predicate = new Comparison("tb.column", '<', $value);
        self::assertStringMatchesFormat('"tb"."column" < :lt%x', $predicate->getSQL());
    }

    /**
     * @dataProvider provideScalarValues
     */
    public function testLessThanEqualWithScalarValue($value)
    {
        $predicate = new Comparison("tb.column", '<=', $value);
        self::assertStringMatchesFormat('"tb"."column" <= :lte%x', $predicate->getSQL());
    }

    /**
     * @dataProvider provideScalarValues
     */
    public function testGreaterThanEqualWithScalarValue($value)
    {
        $predicate = new Comparison("tb.column", '>=', $value);
        self::assertStringMatchesFormat('"tb"."column" >= :gte%x', $predicate->getSQL());
    }

    /**
     * @dataProvider provideScalarValues
     */
    public function testGreaterThanWithScalarValue($value)
    {
        $predicate = new Comparison("tb.column", '>', $value);
        self::assertStringMatchesFormat('"tb"."column" > :gt%x', $predicate->getSQL());
    }

    public function provideScalarValues(): array
    {
        return[
            [42],
            [false],
            [true],
            ['A'],
        ];
    }

    public function testGetCachedSqlCall()
    {
        $predicate = new Comparison('tb.column', '<>', null);
        $sql = $predicate->getSQL();
        self::assertSame($sql, $predicate->getSQL());
    }
}
