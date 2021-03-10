<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql;

use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Clause\Join;
use P3\Db\Sql\Clause\On;
use P3\Db\Sql\Predicate;
use P3\DbTest\DiscloseTrait;
use PHPUnit\Framework\TestCase;
use P3\Db\Exception\RuntimeException;

class JoinTest extends TestCase
{
    use DiscloseTrait;

    public function setUp(): void
    {
    }

    public function tearDown()
    {
    }

    /**
     * @dataProvider provideInvalidJoinTypes
     */
    public function testInvalidJoinTypeRaisesException(string $type)
    {
        $this->expectException(InvalidArgumentException::class);
        $join = new Join($type, 'category', 'c');
    }

    public function provideInvalidJoinTypes(): array
    {
        return [
            ['JOIN ME'],
            ['ABRACADABRA'],
        ];
    }

    public function testGetSqlWithoutSpecification()
    {
        $join = new Join(Sql::JOIN_LEFT, 'category', 'c');

        self::assertSame('LEFT JOIN "category" "c"', $sql = $join->getSQL());
        self::assertSame($sql, $join->getSQL());
        self::assertNull($join->specification);
    }

    public function testGetSqlWithLiteralSpecification()
    {
        $using = new Predicate\Literal("USING(p.category_id)");
        $join = new Join(Sql::JOIN_LEFT, 'category', 'c', $using);

        self::assertSame('LEFT JOIN "category" "c" USING(p.category_id)', $sql = $join->getSQL());
        self::assertSame($sql, $join->getSQL());
        self::assertNull($join->on);
    }

    public function testGetSqlWithOnAddedLater()
    {
        $on = new On();
        $on->eq('c.id', new Sql\Identifier('p.category_id'));

        $join = new Join(Sql::JOIN_LEFT, 'category', 'c', $on);

        self::assertSame('LEFT JOIN "category" "c" ON ("c"."id" = "p"."category_id")', $sql = $join->getSQL());
        self::assertSame($sql, $join->getSQL());
        self::assertSame($join->on, $join->specification);
    }

    public function testGetSqlWithOn()
    {
        $join = new Join(Sql::JOIN_LEFT, 'category', 'c');
        $join->on->eq('c.id', new Sql\Identifier('p.category_id'));

        self::assertSame('LEFT JOIN "category" "c" ON ("c"."id" = "p"."category_id")', $sql = $join->getSQL());
        self::assertSame($sql, $join->getSQL());
        self::assertSame($join->on, $join->specification);
    }

    public function testMagicGetter()
    {
        $on = new On();
        $on->eq('c.id', new Sql\Literal('p.category_id'));

        $join = new Join(Sql::JOIN_LEFT, 'category', 'c', $on);

        self::assertSame("LEFT JOIN", $name = $this->invokeMethod($join, 'getName'));
        self::assertSame($name, $this->invokeMethod($join, 'getName'));
        self::assertSame("LEFT JOIN", $join->name);
        self::assertSame(Sql::JOIN_LEFT, $join->type);
        self::assertSame('category', $join->table);
        self::assertSame('c', $join->alias);
        self::assertSame($on, $join->on);

        $using = new Predicate\Literal("USING(p.category_id)");
        $join = new Join(Sql::JOIN_LEFT, 'category', 'c', $using);

        self::assertSame($using, $join->specification);
        self::assertNull($join->on);

        $this->expectException(RuntimeException::class);
        $join->nonexistentProperty;
    }

    public function testThatCloningAlsoClonesSpecification()
    {
        $join1 = new Join(Sql::JOIN_LEFT, 'category', 'c');
        $join1->on->eq('c.id', new Sql\Identifier('p.category_id'));

        $join2 = clone $join1;

        self::assertEquals($join1, $join2);
        self::assertNotSame($join1, $join2);

        self::assertEquals($join1->specification, $join2->specification);
        self::assertNotSame($join1->specification, $join2->specification);

        self::assertEquals($join1->on, $join2->on);
        self::assertNotSame($join1->on, $join2->on);

        self::assertEquals($join1->on->searchCondition, $join2->on->searchCondition);
        self::assertNotSame($join1->on->searchCondition, $join2->on->searchCondition);
    }
}
