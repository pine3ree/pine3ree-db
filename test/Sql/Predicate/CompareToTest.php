<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Predicate;

use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Predicate\CompareTo;
use P3\Db\Sql\Statement\Select;
use PHPUnit\Framework\TestCase;

class CompareToTest extends TestCase
{
    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    private function createInstance($identifier, string $operator, Select $select): CompareTo
    {
        return new class ($identifier, $operator, $select) extends CompareTo {
            public static $quantifier = 'FEW';
        };
    }

    /**
     * @dataProvider provideUnsupportedOperators
     */
    public function testContructorWithUnsupportedValueRaisesException($operator)
    {
        $select = Sql::select('*', 'product');

        $this->expectException(InvalidArgumentException::class);
        $predicate = $this->createInstance('tb.column', $operator, $select);
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

    public function testGetCachedSqlCall()
    {
        $select = Sql::select('cart_id', 'cart_to_product');

        $predicate = $this->createInstance('c.id', '>', $select);

        self::assertSame(
            '"c"."id" > FEW (SELECT "cart_id" FROM "cart_to_product")',
            $sql = $predicate->getSQL()
        );

        self::assertSame($sql, $predicate->getSQL());
    }

    public function testThatSetSelectParentAndClonesItIfALreadyHas()
    {
        $select0 = Sql::select('id', 'product');

        $predicate1 = $this->createInstance('c.id', '>', $select0);
        $predicate2 = $this->createInstance('c.id', '>', $select0);

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

        $predicate1 = $this->createInstance('c.id', '>', $select0);
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
