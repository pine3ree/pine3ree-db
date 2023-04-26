<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Statement;

use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Params;
use P3\Db\Sql\Statement\Insert;
use P3\Db\Sql\Statement\Select;
use P3\DbTest\DiscloseTrait;
use PHPUnit\Framework\TestCase;
use P3\Db\Exception\RuntimeException;

use function array_keys;
use function array_values;

class InsertTest extends TestCase
{
    use DiscloseTrait;

    /** @var Driver\MySql */
    private $driver;

    public function setUp(): void
    {
        $this->driver = new Driver\MySql();
    }

    public function tearDown(): void
    {
        $this->driver = null;
    }

    public function testFluentInterface()
    {
        $insert = new Insert();

        self::assertSame($insert, $insert->into('product'));
        self::assertSame($insert, $insert->ignore());
        self::assertSame($insert, $insert->row(['price' => 123.45]));
        self::assertSame($insert, $insert->rows([
            ['price' => 123.45],
            ['price' => 678.90],
        ]));
    }

    public function testInsertWithoutTableRaisesExceptionOnGetSQL()
    {
        $insert = new Insert();

        $this->expectException(RuntimeException::class);
        $insert->getSQL($this->driver);
    }

    public function testInsertWithoutValuesRaisesExceptionOnGetSQL()
    {
        $insert = new Insert('product');

        $this->expectException(RuntimeException::class);
        $insert->getSQL($this->driver);
    }

    /**
     * @dataProvider provideInvalidValues
     */
    public function testInsertInvalidValuesRaisesException($values)
    {
        $insert = new Insert();
        $insert->into('product');

        $this->expectException(InvalidArgumentException::class);
        $insert->values($values);
    }

    public function provideInvalidValues(): array
    {
        return [
            [[]],
            [['price' => new \stdClass()]],
        ];
    }

    public function testInsertEmptyRowRaisesException()
    {
        $insert = new Insert();
        $insert->into('product');

        $this->expectException(InvalidArgumentException::class);
        $insert->row([]);
    }

    public function testInsertRowWithDefaultAddFlagFalse()
    {
        $insert = new Insert();
        $insert->into('product');

        $insert->row(['price' => 1.23, 'enabled' => false]);
        $insert->row(['price' => 4.56, 'enabled' => false], true);

        $row = ['price' => 7.89, 'stock' => 42];
        $insert->row($row, false);
        self::assertSame(array_keys($row), $insert->columns);
        self::assertSame([array_values($row)], $insert->values);
    }

    public function testInsertRowsAddParam()
    {
        $insert = new Insert();
        $insert->into('product');

        $row1 = ['price' => 111.11, 'stock' => 111];
        $row2 = ['price' => 222.22, 'stock' => 222];
        $row3 = ['price' => 333.33, 'stock' => 333];
        $row4 = ['price' => 444.44, 'stock' => 444];
        $row5 = ['price' => 555.55, 'stock' => 555];

        $insert->rows([$row1, $row2, $row3]);
        $insert->rows([$row4, $row5]); // this will reset previous 4 sets of values
        self::assertStringMatchesFormat(
            "INSERT INTO `product`"
            . " (`price`, `stock`)"
            . " VALUES"
            . " (:val%d, :val%d),"
            . " (:val%d, :val%d)",
            $insert->getSQL($this->driver)
        );

        $insert->rows([$row1, $row2, $row3]);
        $insert->rows([$row4, $row5], true); // this will not reset previous 4 sets of values
        self::assertStringMatchesFormat(
            "INSERT INTO `product`"
            . " (`price`, `stock`)"
            . " VALUES"
            . " (:val%d, :val%d),"
            . " (:val%d, :val%d),"
            . " (:val%d, :val%d),"
            . " (:val%d, :val%d),"
            . " (:val%d, :val%d)",
            $insert->getSQL($this->driver)
        );
    }

    public function testInsertRowsWithDifferentColumnsRaisesException()
    {
        $insert = new Insert();
        $insert->into('product');

        $insert->row(['price' => 123.45]);
        $this->expectException(RuntimeException::class);
        $insert->row(['price' => 123.45, 'enabled' => false], true);
    }

    public function testInsertValuesWithUnmatchingColumnsRaisesException()
    {
        $insert = new Insert('product');
        $insert->columns(['price', 'stock']);

        $this->expectException(InvalidArgumentException::class);
        $insert->values([123.45]);
    }

    public function testInsertEmptyMultipleValuesRaisesException()
    {
        $insert = new Insert('product');

        $this->expectException(InvalidArgumentException::class);
        $insert->multipleValues([]);
    }

    public function testInsertEmptyMultipleValuesContainingEmptyValuesRaisesException()
    {
        $insert = new Insert('product');

        $this->expectException(InvalidArgumentException::class);
        $insert->multipleValues([[111.11, 111], []]);
    }

    public function testInsertMultipleValues()
    {
        $insert = new Insert('product');
        $insert->columns(['price', 'stock']);

        $insert->multipleValues([
            [111.11, 111],
            [222.22, 222],
        ]);

        self::assertStringMatchesFormat(
            "INSERT INTO `product`"
            . " (`price`, `stock`)"
            . " VALUES"
            . " (:val%d, :val%d),"
            . " (:val%d, :val%d)",
            $insert->getSQL($this->driver)
        );
    }

    public function testInsertMultipleValuesAddParam()
    {
        $insert = new Insert();
        $insert->into('product');
        $insert->columns(['price', 'stock']);

        $insert->multipleValues([
            [111.11, 111],
            [222.22, 222],
        ]);
        $insert->multipleValues([
            [333.33, 333],
            [444.44, 444],
        ], true);

        self::assertCount(4, $insert->values);

        self::assertStringMatchesFormat(
            "INSERT INTO `product`"
            . " (`price`, `stock`)"
            . " VALUES"
            . " (:val%d, :val%d),"
            . " (:val%d, :val%d),"
            . " (:val%d, :val%d),"
            . " (:val%d, :val%d)",
            $insert->getSQL($this->driver)
        );

        $insert->multipleValues([
            [555.55, 555],
            [666.66, 666],
        ], false);

        self::assertCount(2, $insert->values);

        self::assertStringMatchesFormat(
            "INSERT INTO `product`"
            . " (`price`, `stock`)"
            . " VALUES"
            . " (:val%d, :val%d),"
            . " (:val%d, :val%d)",
            $insert->getSQL($this->driver)
        );
    }

    /**
     * @dataProvider provideInvalidValues
     */
    public function testResetValues()
    {
        $insert = new Insert();
        $insert->into('product');

        $insert->columns(['price', 'stock']);

        $insert->values([12.34, 56]);
        $insert->values([56.78, 90], true);

        $insert->values([11.11, 22]);

        self::assertSame([[11.11, 22]], $insert->values);
    }

    /**
     * @dataProvider provideInvalidColumns
     */
    public function testSetInvalidColumnsRaisesException($columns)
    {
        $insert = new Insert();
        $insert->into('product');

        $this->expectException(RuntimeException::class);
        $insert->columns($columns);
    }

    public function provideInvalidColumns(): array
    {
        return [
            [[]],
            [[1]],
            [["1"]],
            [[new \stdClass()]],
        ];
    }

    public function testInsertFromSelectSourceSetParentAndClonesSelectIfHasParent()
    {
        $select = (new Select())->from('store_product');

        $insert1 = new Insert('product');
        $insert2 = new Insert('product');

        $insert1->select($select);
        $insert2->select($select);

        $select1 = $insert1->select;
        $select2 = $insert2->select;

        self::assertSame($insert1, $select1->parent);
        self::assertSame($insert2, $select2->parent);

        self::assertSame($select, $select1);

        self::assertEquals($select1, $select2);
        self::assertNotSame($select1, $select2);
    }

    public function testGetSql()
    {
        $insert = new Insert('product');

        $insert->row(['price' => 111.11, 'stock' => 111, 'enabled' => true]);
        $insert->row(['price' => 222.22, 'stock' => 222, 'enabled' => false], true);

        self::assertStringMatchesFormat(
            "INSERT INTO `product`"
            . " (`price`, `stock`, `enabled`)"
            . " VALUES"
            . " (:val%d, :val%d, :val%d),"
            . " (:val%d, :val%d, :val%d)",
            $sql = $insert->getSQL($this->driver)
        );

        //cached sql
        self::assertSame($sql, $insert->getSQL($this->driver));

        $insert->row(['price' => 333.33, 'stock' => 333, 'enabled' => true], true);
        self::assertStringMatchesFormat(
            "INSERT INTO `product`"
            . " (`price`, `stock`, `enabled`)"
            . " VALUES"
            . " (:val%d, :val%d, :val%d),"
            . " (:val%d, :val%d, :val%d),"
            . " (:val%d, :val%d, :val%d)",
            $sql = $insert->getSQL($this->driver)
        );
    }

    public function testColumnsSqlCache()
    {
        // simple string column =  sql-asterisk
        $insert = new Insert('product');

        $insert->row(['price' => 111.11, 'stock' => 111, 'enabled' => true]);
        $insert->row(['price' => 222.22, 'stock' => 222, 'enabled' => false]);

        $sql = $insert->getSQL();
        self::assertArrayHasKey('columns', $this->getPropertyValue($insert, 'sqls'));
    }

    public function testGetSqlWithSelect()
    {
        $insert = new Insert('product');

        $insert->select((new Select())->from('store2_product'));
        $insert->select->where("price > 0");

        self::assertSame(
            "INSERT INTO `product` SELECT * FROM `store2_product` WHERE price > 0",
            $insert->getSQL($this->driver)
        );
    }

    public function testGetSqlWithSelectWithColumns()
    {
        $insert = new Insert('product');

        $columns = ['id', 'price', 'stock'];

        $insert->columns($columns);
        $insert->select(new Select($columns, 'store2_product'));
        $insert->select->where("enabled IS TRUE");

        self::assertSame(
            $values_sql = "SELECT `id`, `price`, `stock` FROM `store2_product` WHERE enabled IS TRUE",
            $this->invokeMethod($insert, 'getValuesSQL', $this->driver, new Params())
        );

        self::assertSame(
            "INSERT INTO `product` (`id`, `price`, `stock`) {$values_sql}",
            $insert->getSQL($this->driver)
        );
    }

    public function testGetSqlWithoutColumns()
    {
        $insert = new Insert('product');

        $insert->values([111.11, 11, true]);
        $insert->values([222.22, 22, true]);

        self::assertSame('', $this->invokeMethod($insert, 'getColumnsSQL', $this->driver, new Params()));
    }

    public function testThatCloningAlsoClonesSelectFrom()
    {
        $insert1 = new Insert('product');
        $insert1->select(new Select('*', 'product_bak'));

        $insert2 = clone $insert1;

        self::assertInstanceOf(Select::class, $insert2->select);
        self::assertEquals($insert1->select, $insert2->select);
        self::assertNotSame($insert1->select, $insert2->select);
    }

    public function testMagicGetter()
    {
        $insert = new Insert('product');
        $insert->ignore();

        $rows = $values = [];
        for ($i = 1; $i < 4; $i += 1) {
            $rows[$i] = $row = [
                'name' => 'product-' . ($i * 111),
                'price' => ($i * 111.11),
                'published' => ($i % 2 > 0),
            ];
            $values[] = array_values($row);
        }

        $insert->rows($rows);

        self::assertSame('product', $insert->into);
        self::assertSame('product', $insert->table);
        self::assertSame(null, $insert->select);
        self::assertSame(true, $insert->ignore);
        self::assertSame(array_keys($rows[1]), $insert->columns);
        self::assertSame($values, $insert->values);

        $this->expectException(RuntimeException::class);
        $insert->nonexistentProperty;
    }
}
