<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\DbTest\Sql\Clause;

use PHPUnit\Framework\TestCase;
use pine3ree\Db\Exception\InvalidArgumentException;
use pine3ree\Db\Exception\RuntimeException;
use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Clause\Join;
use pine3ree\Db\Sql\Clause\On;
use pine3ree\Db\Sql\Predicate;
use pine3ree\DbTest\DiscloseTrait;

class JoinTest extends TestCase
{
    use DiscloseTrait;

    public function setUp(): void
    {
    }

    public function tearDown(): void
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

    public function testGetSqlWithIdentifierSpecification()
    {
        $using = new Sql\Identifier("p.category_id");
        $join = new Join(Sql::JOIN_LEFT, 'category', 'c', $using);

        self::assertSame('LEFT JOIN "category" "c" USING("p"."category_id")', $sql = $join->getSQL());
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

    /**
     * @dataProvider provideCtorArgs
     */
    public function testAccessors(string $type, string $table, string $alias, $specification)
    {
        $join = new Join($type, $table, $alias, $specification);

        self::assertSame($type, $join->getType());
        self::assertSame($table, $join->getTable());
        self::assertSame($alias, $join->getAlias());

        if ($specification instanceof Sql\Identifier) {
            self::assertInstanceOf(Sql\Identifier::class, $join->getSpecification());
        } elseif ($specification instanceof Predicate\Literal) {
            self::assertInstanceOf(Predicate\Literal::class, $join->getSpecification());
        } else {
            self::assertInstanceOf(On::class, $join->getSpecification());
        }
    }

    public function provideCtorArgs()
    {
        return [
            [Sql::JOIN_LEFT, 'category', 'c', 'c.id = p.category_id'],
            [Sql::JOIN_LEFT, 'role', 'r', new Predicate\Literal('ON r.id = u2r.role_id')],
            [Sql::JOIN_INNER , 'tag', 't', new Predicate\Set('t.id = p.category_id')],
        ];
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
