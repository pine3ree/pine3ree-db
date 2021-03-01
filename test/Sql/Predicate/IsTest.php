<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Predicate;

use InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Predicate\Is;
use PHPUnit\Framework\TestCase;

class IsTest extends TestCase
{
    public function setUp(): void
    {
    }

    public function tearDown()
    {
    }

    public function testIsNull()
    {
        $predicate = new Is('id', null);
        self::assertEquals('"id" IS NULL', $predicate->getSQL());
    }

    public function testIsNullWithString()
    {
        $predicate = new Is('id', 'NULL');
        self::assertEquals('"id" IS NULL', $predicate->getSQL());
    }

    public function testIsNullWithStringIsCaseInsensitive()
    {
        $predicate = new Is('id', 'null');
        self::assertEquals('"id" IS NULL', $predicate->getSQL());
    }

    public function testIsTrue()
    {
        $predicate = new Is('id', true);
        self::assertEquals('"id" IS TRUE', $predicate->getSQL());
    }

    public function testIsTrueWithString()
    {
        $predicate = new Is('id', 'TRUE');
        self::assertEquals('"id" IS TRUE', $predicate->getSQL());
    }

    public function testIsTrueWithStringIsCaseInsensitive()
    {
        $predicate = new Is('id', 'true');
        self::assertEquals('"id" IS TRUE', $predicate->getSQL());
    }

    public function testIsFalse()
    {
        $predicate = new Is('id', false);
        self::assertEquals('"id" IS FALSE', $predicate->getSQL());
    }

    public function testIsFalseWithString()
    {
        $predicate = new Is('id', 'FALSE');
        self::assertEquals('"id" IS FALSE', $predicate->getSQL());
    }

    public function testIsFalseWithStringIsCaseInsensitive()
    {
        $predicate = new Is('id', 'false');
        self::assertEquals('"id" IS FALSE', $predicate->getSQL());
    }

    public function testIsUnknown()
    {
        $predicate = new Is('id', 'UNKNOWN');
        self::assertEquals('"id" IS UNKNOWN', $predicate->getSQL());
    }

    public function testIsUnknownIsCaseInsensitive()
    {
        $predicate = new Is('id', 'unKnowN');
        self::assertEquals('"id" IS UNKNOWN', $predicate->getSQL());
    }

    public function testInvalidValueRaiseException()
    {
        $this->expectException(InvalidArgumentException::class);
        $predicate = new Is('id', 'invalid-value');
    }

    public function testWhenIdentifierIsLiteral()
    {
        $predicate = new Is(Sql::literal('(1 = 1) AS OneEqualsOne'), null);
        self::assertEquals('(1 = 1) AS OneEqualsOne IS NULL', $predicate->getSQL());
    }

    public function testWhenIdentifierIsAlias()
    {
        $predicate = new Is(Sql::alias('customer.isEnabled'), true);
        self::assertEquals('"customer.isEnabled" IS TRUE', $predicate->getSQL());

        $predicate = new Is(Sql::alias('some.alias'), null);
        self::assertEquals('"some.alias" IS NULL', $predicate->getSQL());
    }

    public function testWhenIdentifierHasTableOrAliasPrefix()
    {
        $predicate = new Is('u.id', null);
        self::assertEquals('"u"."id" IS NULL', $predicate->getSQL());

        $predicate = new Is('user.id', null);
        self::assertEquals('"user"."id" IS NULL', $predicate->getSQL());
    }
}
