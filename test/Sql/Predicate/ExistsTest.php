<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Predicate;

use P3\Db\Sql;
use P3\Db\Sql\Predicate\Exists;
use P3\Db\Sql\Predicate\NotExists;
use PHPUnit\Framework\TestCase;

class ExistsTest extends TestCase
{
    public function setUp(): void
    {
    }

    public function tearDown()
    {
    }

    public function testGetSql()
    {
        $select = Sql::select('*', 'product');
        $predicate = new Exists($select);

        self::assertSame('EXISTS (SELECT * FROM "product")', $sql = $predicate->getSQL());
        // test cached sql
        self::assertSame($sql, $predicate->getSQL());
    }

    public function testNotExistsSql()
    {
        $select = Sql::select('*', 'product');
        $predicate = new NotExists($select);

        self::assertSame('NOT EXISTS (SELECT * FROM "product")', $predicate->getSQL());
    }

    public function testThatSetSelectParentAndClonesItIfALreadyHas()
    {
        $select0 = Sql::select('id', 'product');

        $predicate1 = new Exists($select0);
        $predicate2 = new Exists($select0);

        $select1 = $predicate1->select;
        $select2 = $predicate2->select;

        self::assertSame($predicate1, $select1->parent);
        self::assertSame($predicate2, $select2->parent);

        self::assertSame($select0, $select1);
        self::assertEquals($select1, $select2);
        self::assertNotSame($select1, $select2);
    }

    public function testCloningAlsoClonesSelectAndSetParent()
    {
        $select0 = Sql::select('id', 'product');

        $predicate1 = new Exists($select0);
        $predicate2 = clone $predicate1;

        self::assertNull($predicate1->parent);
        self::assertNull($predicate2->parent);

        $select1 = $predicate1->select;
        $select2 = $predicate2->select;

        self::assertSame($predicate1, $select1->parent);
        self::assertSame($predicate2, $select2->parent);

        self::assertSame($select0, $select1);
        self::assertEquals($select1, $select2);
        self::assertNotSame($select1, $select2);
    }
}
