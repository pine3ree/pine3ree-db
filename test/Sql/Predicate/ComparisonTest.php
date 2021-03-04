<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Predicate;

use ArrayObject;
use InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Predicate;
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

    public function testContructorWithUnsupportedValueRaisesException()
    {
        foreach ([
            new stdClass(),
            new ArrayObject()
        ] as $value) {
            $this->expectException(InvalidArgumentException::class);
            $predicate = new Predicate\Comparison('tb.column', '=', $value);
        }
    }

    public function testContructorWithUnsupportedOperatorRaisesException()
    {
        foreach (['+', '?', '1', 'N-O-T', 'I-s', '*'] as $operator) {
            $this->expectException(InvalidArgumentException::class);
            $predicate = new Predicate\Comparison('tb.column', $operator, null);
        }
    }

    public function testGetSqlUsingNotSupportedOperatorWithNullValueRaisesException()
    {
        foreach (['!=', '<>', '<', '<=', '>=', '>'] as $operator) {
            $predicate = new Predicate\Comparison('tb.column', $operator, null);
            $this->expectException(InvalidArgumentException::class);
            $predicate->getSQL();
        }
    }

    public function testThatLiteralValuesAreSupported()
    {
        $literal = "CONCAT('A', 'B')";
        $predicate = new Predicate\Comparison('tb.column', '=', new Sql\Literal($literal));
        self::assertSame('"tb"."column" = ' . $literal, $predicate->getSQL());
    }

    public function testThatIdentifierValuesAreSupportedAndProperlyQuoted()
    {
        $predicate = new Predicate\Comparison('t0.column', '=', new Sql\Identifier("t1.column"));
        self::assertSame('"t0"."column" = "t1"."column"', $predicate->getSQL());

        $predicate = new Predicate\Comparison('t0.column1', '=', new Sql\Identifier("column2"));
        self::assertSame('"t0"."column1" = "column2"', $predicate->getSQL());
    }

    public function testThatAliasValuesAreSupportedAndProperlyQuoted()
    {
        $predicate = new Predicate\Comparison('tb.column', '=', new Sql\Alias("some.alias"));
        self::assertSame('"tb"."column" = "some.alias"', $predicate->getSQL());
    }

    public function testThatStringIdentifiersAreQuotedAsIdentifiers()
    {
        $predicate = new Predicate\Comparison('tb.id', '=', 42);
        self::assertStringStartsWith('"tb"."id" = :', $predicate->getSQL());
    }

    public function testThatLiteralIdentifiersAreSupportedAndNotQuoted()
    {
        $literal = "tb.column";
        $identifier = new Sql\Literal($literal);
        $predicate = new Predicate\Comparison($identifier, '=', 42);
        self::assertStringStartsWith($literal . ' = :', $predicate->getSQL());
    }

    public function testThatSqlIdentifiersAreSupportedAndQuotedProperly()
    {
        $identifier = "tb.column";
        $identifier = new Sql\Identifier($identifier);
        $predicate = new Predicate\Comparison($identifier, '=', 42);
        self::assertStringStartsWith('"tb"."column" = :', $predicate->getSQL());
    }

    public function testThatAliasIdentifiersAreSupportedAndQuotedProperly()
    {
        $alias = "some.coolALias";
        $alias = new Sql\Alias($alias);
        $predicate = new Predicate\Comparison($alias, '=', 42);
        self::assertStringStartsWith('"some.coolALias" = :', $predicate->getSQL());
    }

    public function testNullValue()
    {
        $predicate = new Predicate\Comparison('tb.column', '=', null);
        self::assertSame('"tb"."column" IS NULL', $predicate->getSQL());

        $predicate = new Predicate\Comparison('tb.column', '!=', null);
        self::assertSame('"tb"."column" IS NOT NULL', $predicate->getSQL());

        $predicate = new Predicate\Comparison('tb.column', '<>', null);
        self::assertSame('"tb"."column" IS NOT NULL', $predicate->getSQL());
    }

    /**
     * @dataProvider provideScalarValues
     */
    public function testEqualWithScalarValue($value)
    {
        $predicate = new Predicate\Comparison("tb.column", '=', $value);
        self::assertRegExp('/^\"tb\"\.\"column\" = \:eq[1-9][0-9]*$/', $predicate->getSQL());
    }

    /**
     * @dataProvider provideScalarValues
     */
    public function testNotEqualWithScalarValue($value)
    {
        $predicate = new Predicate\Comparison("tb.column", '!=', $value);
        self::assertRegExp('/^\"tb\"\.\"column\" \!= \:neq[1-9][0-9]*$/', $predicate->getSQL());

        $predicate = new Predicate\Comparison("tb.column", '<>', $value);
        self::assertRegExp('/^\"tb\"\.\"column\" <> \:ne[1-9][0-9]*$/', $predicate->getSQL());
    }

    /**
     * @dataProvider provideScalarValues
     */
    public function testLessThanWithScalarValue($value)
    {
        $predicate = new Predicate\Comparison("tb.column", '<', $value);
        self::assertRegExp('/^\"tb\"\.\"column\" < \:lt[1-9][0-9]*$/', $predicate->getSQL());
    }

    /**
     * @dataProvider provideScalarValues
     */
    public function testLessThanEqualWithScalarValue($value)
    {
        $predicate = new Predicate\Comparison("tb.column", '<=', $value);
        self::assertRegExp('/^\"tb\"\.\"column\" <= \:lte[1-9][0-9]*$/', $predicate->getSQL());
    }

    /**
     * @dataProvider provideScalarValues
     */
    public function testGreaterThanEqualWithScalarValue($value)
    {
        $predicate = new Predicate\Comparison("tb.column", '>=', $value);
        self::assertRegExp('/^\"tb\"\.\"column\" >= \:gte[1-9][0-9]*$/', $predicate->getSQL());
    }

    /**
     * @dataProvider provideScalarValues
     */
    public function testGreaterThanWithScalarValue($value)
    {
        $predicate = new Predicate\Comparison("tb.column", '>', $value);
        self::assertRegExp('/^\"tb\"\.\"column\" > \:gt[1-9][0-9]*$/', $predicate->getSQL());
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
}
