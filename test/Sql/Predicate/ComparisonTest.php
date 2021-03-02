<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Predicate;

use InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\Identifier;
use P3\Db\Sql\Alias;
use P3\Db\Sql\Predicate\Literal;
use PHPUnit\Framework\TestCase;

class ComparisonTest extends TestCase
{
    public function setUp(): void
    {
    }

    public function tearDown()
    {
    }

    public function testContructorWithUnsupportedValueRisesException()
    {
        foreach ([
            new \stdClass(),
            new \ArrayObject()
        ] as $value) {
            $this->expectException(InvalidArgumentException::class);
            $predicate = new Predicate\Comparison('tb.column', '=', $value);
        }
    }

    public function testContructorWithUnsupportedOperatorRisesException()
    {
        foreach (['+', '?', '1', 'N-O-T', 'I-s', '*'] as $operator) {
            $this->expectException(InvalidArgumentException::class);
            $predicate = new Predicate\Comparison('tb.column', $operator, null);
        }
    }

    public function testGetSqlUsingNotSupportedOperatorWithNullValueRisesException()
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
        self::assertEquals('"tb"."column" = ' . $literal, $predicate->getSQL());
    }

    public function testThatIdentifierValuesAreSupportedAndProperlyQuoted()
    {
        $predicate = new Predicate\Comparison('t0.column', '=', new Sql\Identifier("t1.column"));
        self::assertEquals('"t0"."column" = "t1"."column"', $predicate->getSQL());

        $predicate = new Predicate\Comparison('t0.column1', '=', new Sql\Identifier("column2"));
        self::assertEquals('"t0"."column1" = "column2"', $predicate->getSQL());
    }

    public function testThatAliasValuesAreSupportedAndProperlyQuoted()
    {
        $predicate = new Predicate\Comparison('tb.column', '=', new Sql\Alias("some.alias"));
        self::assertEquals('"tb"."column" = "some.alias"', $predicate->getSQL());
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
        self::assertEquals('"tb"."column" IS NULL', $predicate->getSQL());

        $predicate = new Predicate\Comparison('tb.column', '!=', null);
        self::assertEquals('"tb"."column" IS NOT NULL', $predicate->getSQL());

        $predicate = new Predicate\Comparison('tb.column', '<>', null);
        self::assertEquals('"tb"."column" IS NOT NULL', $predicate->getSQL());
    }

    public function testEqualWithScalarValues()
    {
        foreach ([
            42,
            false,
            true,
            'A',
        ] as $value) {
            $predicate = new Predicate\Comparison("tb.column", '=', $value);
            self::assertRegExp('/^\"tb\"\.\"column\" = \:eq[1-9][0-9]*$/', $predicate->getSQL());
        }
    }

    public function testNotEqualWithScalarValues()
    {
        foreach ([
            42,
            false,
            true,
            'A',
        ] as $value) {
            $predicate = new Predicate\Comparison("tb.column", '!=', $value);
            self::assertRegExp('/^\"tb\"\.\"column\" \!= \:neq[1-9][0-9]*$/', $predicate->getSQL());

            $predicate = new Predicate\Comparison("tb.column", '<>', $value);
            self::assertRegExp('/^\"tb\"\.\"column\" <> \:ne[1-9][0-9]*$/', $predicate->getSQL());
        }
    }

    public function testLessThanWithScalarValues()
    {
        foreach ([
            42,
            false,
            true,
            'A',
        ] as $value) {
            $predicate = new Predicate\Comparison("tb.column", '<', $value);
            self::assertRegExp('/^\"tb\"\.\"column\" < \:lt[1-9][0-9]*$/', $predicate->getSQL());
        }
    }

    public function testLessThanEqualWithScalarValues()
    {
        foreach ([
            42,
            false,
            true,
            'A',
        ] as $value) {
            $predicate = new Predicate\Comparison("tb.column", '<=', $value);
            self::assertRegExp('/^\"tb\"\.\"column\" <= \:lte[1-9][0-9]*$/', $predicate->getSQL());
        }
    }

    public function testGreaterThanEqualWithScalarValues()
    {
        foreach ([
            42,
            false,
            true,
            'A',
        ] as $value) {
            $predicate = new Predicate\Comparison("tb.column", '>=', $value);
            self::assertRegExp('/^\"tb\"\.\"column\" >= \:gte[1-9][0-9]*$/', $predicate->getSQL());
        }
    }

    public function testGreaterThanWithScalarValues()
    {
        foreach ([
            42,
            false,
            true,
            'A',
        ] as $value) {
            $predicate = new Predicate\Comparison("tb.column", '>', $value);
            self::assertRegExp('/^\"tb\"\.\"column\" > \:gt[1-9][0-9]*$/', $predicate->getSQL());
        }
    }
}
