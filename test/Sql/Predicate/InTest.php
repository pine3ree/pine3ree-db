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
        $predicate = new Predicate\In('id', $value);
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

        self::assertStringMatchesFormat(
            '"id" IN (SELECT "id" FROM "product")',
            $predicate->getSQL()
        );
    }
}
