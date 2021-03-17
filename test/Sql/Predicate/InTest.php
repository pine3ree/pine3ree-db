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
use P3\Db\Sql\Predicate;
use PHPUnit\Framework\TestCase;
use stdClass;

class InTest extends TestCase
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
        new Predicate\In('id', $value);
    }

    public function provideUnsupportedCtorValues(): array
    {
        return [
            [new stdClass()],
            [new ArrayObject()],
            ['VALUES'],
        ];
    }

    public function testInWithArrayOfValues()
    {
        $predicate = new Predicate\In('id', [1, 2, 3]);

        self::assertStringMatchesFormat('"id" IN (:in%d, :in%d, :in%d)', $sql = $predicate->getSQL());
        // test cached sql
        self::assertSame($sql, $predicate->getSQL());
    }

    public function testInWithArrayOfValuesIncludingNull()
    {
        $predicate = new Predicate\In('id', [1, 2, 3, null]);

        self::assertStringMatchesFormat(
            '("id" IN (:in%d, :in%d, :in%d) OR "id" IS NULL)',
            $sql = $predicate->getSQL()
        );
    }

    public function testNotInWithArrayOfValuesIncludingNull()
    {
        $predicate = new Predicate\NotIn('id', [1, 2, 3, null]);

        self::assertStringMatchesFormat(
            '("id" NOT IN (:in%d, :in%d, :in%d) AND "id" IS NOT NULL)',
            $sql = $predicate->getSQL()
        );
    }

    public function testInWithSelect()
    {
        $select = Sql::select('id', 'product');
        $predicate = new Predicate\In('id', $select);

        self::assertSame(
            '"id" IN (SELECT "id" FROM "product")',
            $predicate->getSQL()
        );
    }

    public function testThatSetSelectParentAndClonesItIfALreadyHas()
    {
        $valueList0 = Sql::select('id', 'product');

        $predicate1 = new Predicate\In('id', $valueList0);
        $predicate2 = new Predicate\In('id', $valueList0);

        $valueList1 = $predicate1->valueList;
        $valueList2 = $predicate2->valueList;

        self::assertSame($predicate1, $valueList1->parent);
        self::assertSame($predicate2, $valueList2->parent);

        self::assertSame($valueList0, $valueList1);
        self::assertEquals($valueList1, $valueList2);
        self::assertNotSame($valueList1, $valueList2);
    }

    public function testCloningWithSelectAlsoClonesSelectAndSetParent()
    {
        $valueList0 = Sql::select('id', 'product');

        $predicate1 = new Predicate\In('id', $valueList0);
        $predicate2 = clone $predicate1;

        self::assertNull($predicate1->parent);
        self::assertNull($predicate2->parent);

        $valueList1 = $predicate1->valueList;
        $valueList2 = $predicate2->valueList;

        self::assertSame($predicate1, $valueList1->parent);
        self::assertSame($predicate2, $valueList2->parent);

        self::assertSame($valueList0, $valueList1);
        self::assertEquals($valueList1, $valueList2);
        self::assertNotSame($valueList1, $valueList2);
    }
}
