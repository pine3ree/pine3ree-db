<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\DbTest\Sql\Statement;

use PHPUnit\Framework\TestCase;
use pine3ree\Db\Exception\InvalidArgumentException;
use pine3ree\Db\Exception\RuntimeException;
use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Alias;
use pine3ree\Db\Sql\Clause\Join;
use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\Expression;
use pine3ree\Db\Sql\Identifier;
use pine3ree\Db\Sql\Literal;
use pine3ree\Db\Sql\Statement\Select;
use pine3ree\DbTest\DiscloseTrait;
use stdClass;

class SelectTest extends TestCase
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

    /**
     * @dataProvider provideColumns
     */
    public function testSelectColumns($columns, $expected_columns_sql)
    {
        $select = new Select();
        $select->columns($columns);
        $select->from('customer');

        self::assertStringMatchesFormat(
            "SELECT {$expected_columns_sql}%wFROM `customer`",
            $select->getSQL($this->driver)
        );
    }

    public function provideColumns(): array
    {
        return [
            [[], "*"],
            [
                [
                    new Identifier('t1.column_2'),
                ],
                '`t1`.`column_2`',
            ],
            [['1'], "1"], // numeric string are converted to Literals
            [
                [
                    new Literal("COUNT(*)"),
                ],
                "COUNT(*)",
            ],
            [
                [
                    new Expression("CONCAT('ABC', {str})", ['str' => 'DEF']),
                ],
                "CONCAT('ABC', :expr%d)",
            ],
            [
                [
                    new Literal("COUNT(*)"),
                ],
                "COUNT(*)",
            ],
            [
                [
                    'nr' => new Literal("COUNT(*)"),
                ],
                "COUNT(*) AS `nr`",
            ],
            [
                [
                    'first_name'
                ],
                '`first_name`',
            ],
            [
                [
                    'firstName' => 'first_name'
                ],
                '`first_name` AS `firstName`',
            ],
        ];
    }

    public function testSelectColumnsWithTableAliasAddsPrefixIfMissing()
    {
        $select = new Select();
        $select->columns([]);
        $select->from('customer', 'c');

        self::assertStringMatchesFormat(
            "SELECT `c`.*%wFROM `customer` `c`",
            $select->getSQL($this->driver)
        );

        $select = new Select();
        $select->columns([
            'id',
            'c.name'
        ]);
        $select->from('customer', 'c');

        self::assertStringMatchesFormat(
            "SELECT `c`.`id`, `c`.`name`%wFROM `customer` `c`",
            $select->getSQL($this->driver)
        );
    }

    /**
     * @dataProvider provideColumn
     */
    public function testSelectColumn($column, ?string $alias, string $column_sql)
    {
        $select = new Select(null, 'product');
        $select->column($column, $alias);

        self::assertStringMatchesFormat(
            "SELECT {$column_sql}%wFROM `product`",
            $select->getSQL($this->driver)
        );
    }

    public function provideColumn(): array
    {
        return [
            ['unit_price', null, "`unit_price`"],
            ['unit_price', 'unitPrice', "`unit_price` AS `unitPrice`"],
            ['p.unit_price', 'unitPrice', "`p`.`unit_price` AS `unitPrice`"],
            ['`p`.`unit_price`', 'unitPrice', "`p`.`unit_price` AS `unitPrice`"],
            [new Identifier('p.vat_rate'), 'vatRate', "`p`.`vat_rate` AS `vatRate`"],
            [new Literal("SUM(unit_price)"), 'totalPrice', "SUM(unit_price) AS `totalPrice`"],
            [
                new Expression("SUM(unit_price) + {tax}", ['tax' => 5.00]),
                'totalPrice',
                "SUM(unit_price) + :expr%d AS `totalPrice`",
            ],
            ['1', 'numberOne', "1 AS `numberOne`"],
        ];
    }

    /**
     * @dataProvider provideInvalidTypeColumn
     */
    public function testThatExceptionIsThrownWithInvalidTypeColumns($column)
    {
        $select = new Select(null, 'user');

        $this->expectException(InvalidArgumentException::class);
        $select->columns(['ca' => $column]);
    }

    /**
     * @dataProvider provideInvalidTypeColumn
     */
    public function testThatExceptionIsThrownWithInvalidTypeColumn($column)
    {
        $select = new Select(null, 'user');

        $this->expectException(InvalidArgumentException::class);
        $select->column($column, 'ca');
    }

    public function provideInvalidTypeColumn(): array
    {
        return [
            [new stdClass()],
            [null],
            [0],
        ];
    }

    public function testThatAddingItselfAsColumnRaisesException()
    {
        $select = new Select(null, 'user');

        $this->expectException(RuntimeException::class);
        $select->column($select, 'itself');
    }

    public function testThatAddingSelectColumnSetTheParent()
    {
        $select0 = new Select(null, 'category');

        $select1 = new Select(null, 'cart_product');
        $select2 = new Select(null, 'store_product');

        $select1->column($select0, 'cat');
        $select2->column($select0, 'cat');

        $sc1 = $select1->columns['cat'];
        $sc2 = $select2->columns['cat'];

        self::assertSame($select0, $sc1);

        self::assertSame($select1, $sc1->parent);
        self::assertSame($select2, $sc2->parent);

        self::assertNotEquals($sc1, $sc2);
    }

    public function testAggregateMethods()
    {
        $select = new Select();
        $select->count()->from('product');
        self::assertStringMatchesFormat(
            'SELECT COUNT(*)%wFROM "product"',
            $select->getSql()
        );

        $select = new Select();
        $select->count('id')->from('product');
        self::assertStringMatchesFormat(
            'SELECT COUNT(id)%wFROM "product"',
            $select->getSql()
        );

        $select = new Select();
        $select->count('1')->from('product');
        self::assertStringMatchesFormat(
            'SELECT COUNT(1)%wFROM "product"',
            $select->getSql()
        );

        $select = new Select();
        $select->sum('price')->from('product');
        self::assertStringMatchesFormat(
            'SELECT SUM(price)%wFROM "product"',
            $select->getSql()
        );

        $select = new Select();
        $select->min('price')->from('product');
        self::assertStringMatchesFormat(
            'SELECT MIN(price)%wFROM "product"',
            $select->getSql()
        );

        $select = new Select();
        $select->max('price')->from('product');
        self::assertStringMatchesFormat(
            'SELECT MAX(price)%wFROM "product"',
            $select->getSql()
        );

        $select = new Select();
        $select->avg('price')->from('product');
        self::assertStringMatchesFormat(
            'SELECT AVG(price)%wFROM "product"',
            $select->getSql()
        );

        $select = new Select();
        $select->aggregate('SOMEFUNCTION', 'price')->from('product');
        self::assertStringMatchesFormat(
            'SELECT SOMEFUNCTION(price)%wFROM "product"',
            $select->getSql()
        );
    }

    public function testSelectWithoutFromRaisesExceptionOnGetSQL()
    {
        $select = new Select();
        $select->columns([]);

        $this->expectException(RuntimeException::class);
        self::assertSame("SELECT *", $select->getSQL($this->driver));
    }

    public function testEmptyFromRaisesException()
    {
        $this->expectException(InvalidArgumentException::class);
        (new Select())->from('', null);
    }

    public function testInvalidTypeFromRaisesException()
    {
        $this->expectException(InvalidArgumentException::class);
        (new Select())->from(new stdClass(), null);
    }

    public function testFromSubselectWithoutAliasRaisesException()
    {
        $this->expectException(InvalidArgumentException::class);
        (new Select())->from(new Select([], 'subtable'), null);
    }

    public function testFromSubselectWithEmptyAliasRaisesException()
    {
        $this->expectException(InvalidArgumentException::class);
        (new Select())->from(new Select([], 'subtable', ''), null);
    }

    public function testGetColumnsSqlForwardToDriver()
    {
        $select = (new Select())->column('unit_price', 'unitPrice')->from('product', 'p');
        self::assertStringMatchesFormat(
            'SELECT "p".unit_price AS "unitPrice"%wFROM product "p"',
            $select->getSQL(new Driver\Oci())
        );
    }

    public function testGetColumnsSqlCache()
    {
        $select = (new Select())
            ->column('unit_price', 'unitPrice')
            ->column('vat_tax_id', 'vatTaxId')
            ->from('product', 'p');

        $select->getSQL($this->driver);
        $params = $select->getParams();

        $columnsSQL = $this->invokeMethod($select, 'getColumnsSQL', $this->driver, $params);

        self::assertSame(
            $columnsSQL,
            $this->invokeMethod($select, 'getColumnsSQL', $this->driver, $params)
        );
    }

    public function testSelectFromTable()
    {
        $select = (new Select())->from('product', null);
        self::assertStringMatchesFormat("SELECT *%wFROM `product`", $select->getSQL($this->driver));

        $select = (new Select())->from('product', 'p');
        self::assertStringMatchesFormat("SELECT `p`.*%wFROM `product` `p`", $select->getSQL($this->driver));
    }

    public function testSelectWithJoinAndNoAliasTriggerTablePrefix()
    {
        $select = (new Select())->from('product', null);
        $select->column('*');
        $select->column('c.name', 'categoryName');
        $select->leftJoin('category', 'c', 'c.id = product.category_id');

        self::assertStringMatchesFormat(
            "SELECT `product`.*, `c`.`name` AS `categoryName`"
            . "%wFROM `product`"
            . "%wLEFT JOIN `category` `c` ON (`c`.id = `product`.category_id)",
            $select->getSQL($this->driver)
        );
    }

    public function testCallingFromAgainRaisesException()
    {
        $select = (new Select())->from('product', null);

        $this->expectException(RuntimeException::class);
        $select->from('another_table');
    }

    public function testSelectIntoEmptyStringRaisesException()
    {
        $this->expectException(InvalidArgumentException::class);
        $select = (new Select())->from('product', 'p')->into('');
    }

    public function testSelectInto()
    {
        $select = (new Select())->from('product')->into('new_product');

        self::assertSame(
            "SELECT * INTO `new_product` FROM `product`",
            $select->getSQL($this->driver)
        );
    }

    public function testSelectFromSubselect()
    {
        $subSelect = (new Select())->from('cart');
        $select = (new Select())->from($subSelect, 'c');
        self::assertStringMatchesFormat(
            "SELECT `c`.*%wFROM (%wSELECT *%wFROM `cart`%w) `c`",
            $select->getSQL($this->driver)
        );

        $subSelect = (new Select())->from('cart_product', 'cp');
        $subSelect->where->gt('cp.price', 0);
        $select = (new Select())->from($subSelect, 'p');
        self::assertStringMatchesFormat(
            "SELECT `p`.*%wFROM ("
            . "%wSELECT `cp`.*"
            . "%wFROM `cart_product` `cp`"
            . "%wWHERE `cp`.`price` > :gt%d"
            . "%w) `p`",
            $select->getSQL($this->driver)
        );
    }

    public function testSelectFromSubselectClonesItIfAlreadyHasParent()
    {
        $subSelect = (new Select())->from('cart');

        $select1 = new Select();
        $select2 = new Select();

        $select1->from($subSelect, 't');
        $select2->from($subSelect, 't');

        self::assertSame($subSelect, $select1->from);
        self::assertEquals($select1->from, $select2->from);
        self::assertNotSame($select1->from, $select2->from);
    }

    public function testSelectFromSubselectRaisesExceptionWhenAddingItself()
    {
        $select = new Select('*');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("cannot add itself as a FROM clause");
        $select->from($select, 't');
    }

    public function testSelectWithLimit()
    {
        $select = (new Select())->from('user')->limit(10);
        self::assertStringMatchesFormat(
            "SELECT *%wFROM `user`%wLIMIT :limit%d",
            $select->getSQL($this->driver)
        );

        $params_values = $select->getParams()->getValues();
        self::assertSame(10, current($params_values) ?? null);
    }

    public function testSelectWithNegativeOrNullLimitShouldDisableLimit()
    {
        $select = (new Select())->from('user');

        $select->limit(10);
        $select->limit(-1);
        self::assertStringMatchesFormat(
            "SELECT *%wFROM `user`",
            $select->getSQL($this->driver)
        );

        $select->limit(10);
        $select->limit(null);
        self::assertStringMatchesFormat(
            "SELECT *%wFROM `user`",
            $select->getSQL($this->driver)
        );

        self::assertCount(0, $select->getParams()->getValues());
    }

    public function testSelectWithOffset()
    {
        $select = (new Select())->from('user')->offset(100);
        self::assertStringMatchesFormat(
            "SELECT *%wFROM `user`%wLIMIT " . PHP_INT_MAX . " OFFSET :offset%d",
            $select->getSQL($this->driver)
        );

        $params_values = $select->getParams()->getValues();
        self::assertSame(100, current($params_values) ?? null);
    }

    public function testSelectZeroOrNullOrNegativeOffsetIsDiscarded()
    {
        $select = (new Select())->from('user')->offset(0);
        self::assertStringMatchesFormat(
            "SELECT *%wFROM `user`",
            $select->getSQL($this->driver)
        );

        $select = (new Select())->from('user')->offset(null);
        self::assertStringMatchesFormat(
            "SELECT *%wFROM `user`",
            $select->getSQL($this->driver)
        );

        $select = (new Select())->from('user')->offset(-1);
        self::assertStringMatchesFormat(
            "SELECT *%wFROM `user`",
            $select->getSQL($this->driver)
        );
    }

    public function testSelectWithLimitAndOffset()
    {
        $select = (new Select())->from('user')->limit(10)->offset(100);

        self::assertStringMatchesFormat(
            "SELECT *%wFROM `user`%wLIMIT :limit%d OFFSET :offset%d",
            $select->getSQL($this->driver)
        );

        $params_values = $select->getParams()->getValues();
        self::assertSame(10, current($params_values) ?? null);
        self::assertSame(100, next($params_values) ?? null);
    }

    public function testAnsiDriverDoesNotSupportLimitAndOffset()
    {
        $select = (new Select())->from('user')->limit(10)->offset(100);
        self::assertStringMatchesFormat(
            'SELECT *%wFROM "user"%w[LIMIT 10 OFFSET 100]',
            $select->getSQL(Driver::ansi())
        );

        self::assertEmpty($select->getParams()->getValues());
    }

    /**
     * @dataProvider provideGroupBy
     */
    public function testGroupByClause($groupBy, string $expectedSQL)
    {
        $select = (new Select())->sum("unit_price*quantity", "productTotal")->from('cart_product');

        $select->groupBy($groupBy);
        self::assertStringMatchesFormat(
            "SELECT SUM(unit_price*quantity) AS `productTotal`%wFROM `cart_product`%wGROUP BY {$expectedSQL}",
            $select->getSQL($this->driver)
        );

        // test replace
        $select->groupBy(['tax_id'], true);
        self::assertStringMatchesFormat(
            "SELECT SUM(unit_price*quantity) AS `productTotal`%wFROM `cart_product`%wGROUP BY `tax_id`",
            $select->getSQL($this->driver)
        );
    }

    public function provideGroupBy(): array
    {
        return [
            ["cart_id", "`cart_id`"],
            ["cart_id", "`cart_id`"],
            ["cart_id", "`cart_id`"],
            ["cart_id", "`cart_id`"],
            ["cart_id", "`cart_id`"],
            ["cart.id", "`cart`.`id`"],
            [new Alias("some.alias"), "`some.alias`"],
            [new Identifier("some.column"), "`some`.`column`"],
            [new Literal("(unit_price * quantity)"), "(unit_price * quantity)"],
        ];
    }

    public function testGroupBySqlCache()
    {
        $select = (new Select())->sum("unit_price*quantity", "productTotal")->from('cart_product');

        $select->groupBy('cart_id');

        $select->getSQL($this->driver);

        $groupBySQL = $this->invokeMethod($select, 'getGroupBySQL', $this->driver);

        self::assertSame(
            $groupBySQL,
            $this->invokeMethod($select, 'getGroupBySQL', $this->driver)
        );
    }

    /**
     * @dataProvider provideOrderBy
     */
    public function testOrderByClause($orderBy, $sortDirOrReplace, string $expectedSQL)
    {
        $select = (new Select())->from('product');
        $select->orderBy($orderBy, $sortDirOrReplace);

        $orderBySQL = $expectedSQL ? "%wORDER BY {$expectedSQL}" : '';

        self::assertStringMatchesFormat(
            "SELECT *%wFROM `product`{$orderBySQL}",
            $select->getSQL($this->driver)
        );
    }

    public function provideOrderBy(): array
    {
        return [
            ["id", null, "`id` ASC"],
            ["id", 'ASC', "`id` ASC"],
            ["id", 'asc', "`id` ASC"],
            ["id", 'DESC', "`id` DESC"],
            ["id", 'desc', "`id` DESC"],
            ["u.id", null, "`u`.`id` ASC"],
            [new Alias("some.alias"), null, "`some.alias` ASC"],
            [new Identifier("some.column"), null, "`some`.`column` ASC"],
            [new Literal("(unit_price * quantity)"), null, "(unit_price * quantity) ASC"],
            [['unit_price' => 'DESC'], true, '`unit_price` DESC'],
            [['unit_price', 'stock'], 'DESC', '`unit_price` DESC, `stock` DESC'],
            [[], 'DESC', ''],
        ];
    }

    public function testOrderBySqlCache()
    {
        $select = (new Select())->from('product');
        $select->orderBy('unit_price', 'DESC');

        $select->getSQL($this->driver);

        $orderBySQL = $this->invokeMethod($select, 'getOrderBySQL', $this->driver);

        self::assertSame(
            $orderBySQL,
            $this->invokeMethod($select, 'getOrderBySQL', $this->driver)
        );
    }

    public function testWhereMethod()
    {
        $select = new Select(null, 'product', 'p');
        $select->where(function (Sql\Clause\Where $where) {
            $where->gt('price', 10);
        });

        self::assertStringMatchesFormat(
            "SELECT `p`.*"
            . "%wFROM `product` `p`"
            . "%wWHERE `price` > :gt%d",
            $select->getSQL($this->driver)
        );
    }

    public function testHavingMethod()
    {
        $select = new Select(null, 'product', 'p');
        $select->sum('stock', 'totByCategory');
        $select->groupBy('category_id');
        $select->having(function (Sql\Clause\Having $having) {
            $having->gt('totByCategory', 10);
        });

        self::assertStringMatchesFormat(
            "SELECT SUM(stock) AS `totByCategory`"
            . "%wFROM `product` `p`"
            . "%wGROUP BY `category_id`"
            . "%wHAVING `totByCategory` > :gt%d",
            $select->getSQL($this->driver)
        );
    }

    public function testJoinClause()
    {
        // using string a specification => will generate a ON clause
        $select = (new Select(['*', "c.*"]))->from('order', 'o');
        $select->leftJoin('customer', 'c', "c.id = o.customer_id");
        self::assertStringMatchesFormat(
            "SELECT `o`.*, `c`.*"
            . "%wFROM `order` `o`"
            . "%wLEFT JOIN `customer` `c` ON (`c`.id = `o`.customer_id)",
            $select->getSQL($this->driver)
        );

        // using literal-predicate a specification
        $select = (new Select())->from('user', 'u');
        $select->leftJoin('customer', 'c', new Sql\Predicate\Literal("USING (customer_id)"));
        self::assertStringMatchesFormat(
            "SELECT `u`.*"
            . "%wFROM `user` `u`"
            . "%wLEFT JOIN `customer` `c` USING (customer_id)",
            $select->getSQL($this->driver)
        );

        // multiple join
        $select = (new Select(['*', "o.*", 'c.*']))->from('order_product', 'op');
        $select->leftJoin('order', 'o', "op.order_id = o.id");
        $select->leftJoin('customer', 'c', "c.id = o.customer_id");
        self::assertStringMatchesFormat(
            "SELECT `op`.*, `o`.*, `c`.*"
            . "%wFROM `order_product` `op`"
            . "%wLEFT JOIN `order` `o` ON (`op`.order_id = `o`.id)"
            . "%wLEFT JOIN `customer` `c` ON (`c`.id = `o`.customer_id)",
            $select->getSQL($this->driver)
        );
    }

    public function testAddJoinInstance()
    {
        $select = (new Select(['*', "o.*", 'c.*']))->from('order_product', 'op');

        $join = new Join(Sql::JOIN_INNER, 'order', 'o');
        $join->on->equal(new Identifier("op.order_id"), new Identifier("o.id"));
        $select->addJoin($join);

        $join = new Join(Sql::JOIN_INNER, 'customer', 'c');
        $join->on->equal(new Identifier("c.id"), new Identifier("o.customer_id"));
        $select->addJoin($join);
        self::assertStringMatchesFormat(
            "SELECT `op`.*, `o`.*, `c`.*"
            . "%wFROM `order_product` `op`"
            . "%wINNER JOIN `order` `o` ON (`op`.`order_id` = `o`.`id`)"
            . "%wINNER JOIN `customer` `c` ON (`c`.`id` = `o`.`customer_id`)",
            $select->getSQL($this->driver)
        );
    }

    public function testAddJoinThatAlreadBelongsToOtherSelectClonesIt()
    {
        $select1 = (new Select(['*', "o.*"]))->from('order_product', 'op');
        $select2 = (new Select(['*', "o.*"]))->from('order_product', 'op');

        $join = new Join(Sql::JOIN_INNER, 'order', 'o');
        $join->on->equal(new Identifier("op.order_id"), new Identifier("o.id"));

        $select1->addJoin($join);
        $select2->addJoin($join);

        $join1 = $select1->joins[0];
        $join2 = $select2->joins[0];

        self::assertSame($join, $join1);
        self::assertSame($select1, $join1->parent);
        self::assertSame($select2, $join2->parent);
        self::assertEquals($join1, $join2);
        self::assertNotSame($join1, $join2);
    }
//
//    public function testThatAddIntersectAfterUnionRaisesException()
//    {
//        $select = new Select('*', 'product', 'p');
//        $union = (new Select('*', 'store1_product'))->orderBy('price');
//        $select->union($union);
//        $select->union; // triggers clear SQL cache
//
//        $this->expectException(RuntimeException::class);
//        $select->intersect(new Select('*', 'store2_product'));
//    }
//
//    public function testThatAddUnionAfterIntersectRaisesException()
//    {
//        $select = new Select('*', 'product', 'p');
//        $intersect = (new Select('*', 'store1_product'))->orderBy('price');
//        $select->intersect($intersect);
//
//        $this->expectException(RuntimeException::class);
//        $select->union(new Select('*', 'store2_product'));
//    }
//
//    public function testThatAddExceptAfterIntersectRaisesException()
//    {
//        $select = new Select('*', 'product', 'p');
//        $intersect = (new Select('*', 'store1_product'))->orderBy('price');
//        $select->intersect($intersect);
//
//        $this->expectException(RuntimeException::class);
//        $select->except(new Select('*', 'store2_product'));
//    }
//
//    public function testAddingItselfAsUnionRaisesException()
//    {
//        $select = new Select('*');
//        $this->expectException(RuntimeException::class);
//        $this->expectExceptionMessage("cannot use itself for a/an UNION clause");
//        $select->union($select);
//    }
//
//    public function testAddingItselfAsIntersectRaisesException()
//    {
//        $select = new Select('*');
//        $this->expectException(RuntimeException::class);
//        $this->expectExceptionMessage("cannot use itself for a/an INTERSECT clause");
//        $select->intersect($select);
//    }
//
//    public function testAddingItselfAsExceptRaisesException()
//    {
//        $select = new Select('*');
//        $this->expectException(RuntimeException::class);
//        $this->expectExceptionMessage("cannot use itself for a/an EXCEPT clause");
//        $select->except($select);
//    }

    public function testCacheableColumnsSql()
    {
        // simple string column =  sql-asterisk
        $select = new Select([], 'product', 'p');
        $sql = $select->getSQL();
        self::assertArrayHasKey('columns', $this->getPropertyValue($select, 'sqls'));

        // simple string columns
        $select = new Select(['id', 'name', 'price', 'vat_rate'], 'product', 'p');
        $sql = $select->getSQL();
        self::assertArrayHasKey('columns', $this->getPropertyValue($select, 'sqls'));

        // identifier
        $select = new Select(new Identifier('p.price'), 'product', 'p');
        $sql = $select->getSQL();
        self::assertArrayHasKey('columns', $this->getPropertyValue($select, 'sqls'));

        // literal
        $select = new Select(['fullPrice' => new Literal('price * (1 + vat_rate/100)')], 'product', 'p');
        $sql = $select->getSQL();
        self::assertArrayHasKey('columns', $this->getPropertyValue($select, 'sqls'));

        // expression without params
        $select = new Select(['fullPrice' => new Expression('price * (1 + vat_rate/100)')], 'product', 'p');
        $sql = $select->getSQL();
        self::assertArrayHasKey('columns', $this->getPropertyValue($select, 'sqls'));

        // expression with params
        $select = new Select([
            'fullPrice' => new Expression('price * (1 + {vat_rate}/100)', ['vat_rate' => 20.0])
        ], 'product', 'p');
        $sql = $select->getSQL();
        self::assertArrayNotHasKey('columns', $this->getPropertyValue($select, 'sqls'));

        // select
        $select = new Select(['stock' => new Select('quantity', 'product_stock', 'ps')], 'product', 'p');
        $sql = $select->getSQL();
        self::assertArrayNotHasKey('columns', $this->getPropertyValue($select, 'sqls'));
    }

    public function testUncacheableColumnsSql()
    {
        // test cacheable columns sql
        $select = new Select([
            'fullPrice' => new Expression("(price * {vat_rate})", ['vat_rate' => 22.0])
        ], 'product', 'p');

        $sql = $select->getSQL();

        self::assertArrayNotHasKey('columns', $this->getPropertyValue($select, 'sqls'));
    }

    public function testSqlCachePartialClearing()
    {
        $select0 = new Select(['id', 'name', 'price', 'vat_rate'], 'product', 'p');
        $select0->sum('price', 'totPrice');
        $select0->where->gte('price', 0.5);
        $select0->leftJoin('category', 'c', "c.id = p.category_id");
        $select0->groupBy('p.category_id');
        $select0->having->gte('totPrice', 10.0);
        $select0->orderBy('p.price');
        $select0->limit(10)->offset(20);

        $select1 = clone $select0;

        $sql = $select1->getSQL();

        self::assertArrayHasKey('columns', $this->getPropertyValue($select1, 'sqls'));
        $this->invokeMethod($select1, 'clearPartialSQL', 'columns');
        self::assertArrayNotHasKey('columns', $this->getPropertyValue($select1, 'sqls'));

        self::assertSame($sql, $select1->getSQL());
        self::assertSame($sql, $select1->getSQL());

        self::assertArrayHasKey('group', $this->getPropertyValue($select1, 'sqls'));
        $this->invokeMethod($select1, 'clearPartialSQL', 'group');
        self::assertArrayNotHasKey('group', $this->getPropertyValue($select1, 'sqls'));

        self::assertSame($sql, $select1->getSQL());

        self::assertArrayHasKey('order', $this->getPropertyValue($select1, 'sqls'));
        $this->invokeMethod($select1, 'clearPartialSQL', 'order');
        self::assertArrayNotHasKey('order', $this->getPropertyValue($select1, 'sqls'));

        // test clear all partials
        $select2 = clone $select0;

        $sql = $select2->getSQL();

        self::assertArrayHasKey('columns', $this->getPropertyValue($select2, 'sqls'));
        self::assertArrayHasKey('group', $this->getPropertyValue($select2, 'sqls'));
        self::assertArrayHasKey('order', $this->getPropertyValue($select2, 'sqls'));
        $this->invokeMethod($select2, 'clearPartialSQL');
        self::assertArrayNotHasKey('columns', $this->getPropertyValue($select2, 'sqls'));
        self::assertArrayNotHasKey('group', $this->getPropertyValue($select2, 'sqls'));
        self::assertArrayNotHasKey('order', $this->getPropertyValue($select2, 'sqls'));

        self::assertSame($sql, $select2->getSQL());
    }

    public function testThatChangesInInternalElementsClearAllParentsSqlCache()
    {
        $select = new Select('*', 'product');
        $select->where->gt('id', 42);

        $predicate1 = new Sql\Predicate\Comparison('price', '>', 10.0);
        $predicate2 = new Sql\Predicate\Comparison('vat_rate', '>', 5.0);

        $predicateSet = new Sql\Predicate\Set([
            $predicate1,
            $predicate2,
        ]);

        $select->where->addPredicate($predicateSet);

        $sql = $select->getSQL();

        self::assertNotNull($this->getPropertyValue($select, 'sql'));
        self::assertNotNull($this->getPropertyValue($select->where, 'sql'));
        self::assertNotNull($this->getPropertyValue($select->where->searchCondition, 'sql'));
        self::assertNotNull($this->getPropertyValue($predicateSet, 'sql'));
        self::assertNotNull($this->getPropertyValue($predicate1, 'sql'));
        self::assertNotNull($this->getPropertyValue($predicate2, 'sql'));

        $predicateSet->and()->gt('id', 24);

        // parents get cleared
        self::assertNull($this->getPropertyValue($select, 'sql'));
        self::assertNull($this->getPropertyValue($select->where, 'sql'));
        self::assertNull($this->getPropertyValue($select->where->searchCondition, 'sql'));
        self::assertNull($this->getPropertyValue($predicateSet, 'sql'));
        // siblings do not
        self::assertNotNull($this->getPropertyValue($predicate1, 'sql'));
        self::assertNotNull($this->getPropertyValue($predicate2, 'sql'));
    }

    public function testThatCallingGetSqlWithDifferentDriversResetsTheCompiledSqlString()
    {
        $select = new Select(['id', 'price'], 'product', 'p');

        // ansi implied
        $sql0 = $select->getSQL();
        self::assertSame('SELECT "p"."id", "p"."price" FROM "product" "p"', $sql0);
        $sql1 = $this->getPropertyValue($select, 'sql');
        self::assertNotNull($sql1);
        self::assertSame($sql0, $sql1);
        $select->getSQL();
        $sql2 = $this->getPropertyValue($select, 'sql');
        self::assertNotNull($sql2);
        self::assertSame($sql1, $sql2);

        // ansi
        $sql0 = $select->getSQL($driver = Driver::ansi());
        self::assertSame('SELECT "p"."id", "p"."price" FROM "product" "p"', $sql0);
        $sql1 = $this->getPropertyValue($select, 'sql');
        self::assertNotNull($sql1);
        self::assertSame($sql0, $sql1);
        $select->getSQL($driver);
        $sql2 = $this->getPropertyValue($select, 'sql');
        self::assertNotNull($sql2);
        self::assertSame($sql1, $sql2);

        // mysql
        $sql0 = $select->getSQL($driver = new Driver\MySql());
        self::assertSame('SELECT `p`.`id`, `p`.`price` FROM `product` `p`', $sql0);
        $sql1 = $this->getPropertyValue($select, 'sql');
        self::assertNotNull($sql1);
        self::assertSame($sql0, $sql1);
        $select->getSQL($driver);
        $sql2 = $this->getPropertyValue($select, 'sql');
        self::assertNotNull($sql2);
        self::assertSame($sql1, $sql2);

        // pgsql
        $sql0 = $select->getSQL($driver = new Driver\PgSql());
        self::assertSame('SELECT "p"."id", "p"."price" FROM "product" "p"', $sql0);
        $sql1 = $this->getPropertyValue($select, 'sql');
        self::assertNotNull($sql1);
        self::assertSame($sql0, $sql1);
        $select->getSQL($driver);
        $sql2 = $this->getPropertyValue($select, 'sql');
        self::assertNotNull($sql2);
        self::assertSame($sql1, $sql2);

        // sqlite
        $sql0 = $select->getSQL($driver = new Driver\Sqlite());
        self::assertSame('SELECT "p"."id", "p"."price" FROM "product" "p"', $sql0);
        $sql1 = $this->getPropertyValue($select, 'sql');
        self::assertNotNull($sql1);
        self::assertSame($sql0, $sql1);
        $select->getSQL($driver);
        $sql2 = $this->getPropertyValue($select, 'sql');
        self::assertNotNull($sql2);
        self::assertSame($sql1, $sql2);

        // sqlsrv
        $sql0 = $select->getSQL($driver = new Driver\SqlSrv());
        self::assertSame('SELECT [p].[id], [p].[price] FROM [product] [p]', $sql0);
        $sql1 = $this->getPropertyValue($select, 'sql');
        self::assertNotNull($sql1);
        self::assertSame($sql0, $sql1);
        $select->getSQL($driver);
        $sql2 = $this->getPropertyValue($select, 'sql');
        self::assertNotNull($sql2);
        self::assertSame($sql1, $sql2);

        // oci
        $sql0 = $select->getSQL($driver = new Driver\Oci());
        self::assertSame('SELECT "p".id AS "id", "p".price AS "price" FROM product "p"', $sql0);
        $sql1 = $this->getPropertyValue($select, 'sql');
        self::assertNotNull($sql1);
        self::assertSame($sql0, $sql1);
        $select->getSQL($driver);
        $sql2 = $this->getPropertyValue($select, 'sql');
        self::assertNotNull($sql2);
        self::assertSame($sql1, $sql2);
    }

    public function testThatCloningAlsoClonesClauses()
    {
        $select0 = new Select('*', 'product', 'p');

        $select1 = clone $select0;
        $select1->leftJoin('category', 'c', 'USING(category_id)');
        $select1->where("TRUE IS TRUE");
        $select1->having("FALSE IS FALSE");

        $select2 = clone $select1;

        self::assertEquals($select1, $select2);
        self::assertNotSame($select1, $select2);

        $joins1 = $select1->joins;
        $joins2 = $select2->joins;

        foreach ($joins1 as $k => $join1) {
            $join2 = $joins2[$k] ?? null;
            self::assertEquals($join1, $join2);
            self::assertNotSame($join1, $join2);
        }

        self::assertEquals($select1->where, $select2->where);
        self::assertNotSame($select1->where, $select2->where);
        self::assertEquals($select1->having, $select2->having);
        self::assertNotSame($select1->having, $select2->having);

        $select3 = new Select('*', $select0, 's1');
        $select4 = clone $select3;

        self::assertInstanceOf(Select::class, $select4->from);
        self::assertEquals($select3->from, $select4->from);
        self::assertNotSame($select3->from, $select4->from);

        $select5 = new Select('*', 'store_product', 'sp');
        $select5->union($select0);
        $select6 = clone $select5;

        self::assertEquals($select5->union, $select6->union);
        self::assertNotSame($select5->union, $select6->union);

        $select7 = new Select('*', 'store_product', 'sp');
        $select7->intersect($select0);
        $select8 = clone $select7;

        self::assertEquals($select7->intersect, $select8->intersect);
        self::assertNotSame($select7->intersect, $select8->intersect);

        $select9 = new Select('*', 'store_product', 'sp');
        $select9->except($select0);
        $select10 = clone $select9;

        self::assertEquals($select9->except, $select10->except);
        self::assertNotSame($select9->except, $select10->except);
    }

    public function testThatCloningAlsoClonesColumnsOfTypeSelect()
    {
        $column = new Select('*', 'category', 'c');

        $select1 = new Select(['cat' => $column, 'price'], 'product', 'p');
        $select2 = clone $select1;

        $column1 = $select1->columns['cat'];
        $column2 = $select2->columns['cat'];

        self::assertEquals($column1, $column2);
        self::assertNotSame($column1, $column2);
        self::assertSame($select1, $column1->parent);
        self::assertSame($select2, $column2->parent);
    }

    public function testNestingLevelCalculator()
    {
        $select0 = new Select();
        $select0->from('product', 'p0');
        self::assertSame(0, $this->invokeMethod($select0, 'getNestingLevel'));

        $select1 = new Select('*', $select0, 'p1');
        self::assertSame(1, $this->invokeMethod($select0, 'getNestingLevel'));

        $select2 = new Select('*', $select1, 'p2');
        self::assertSame(2, $this->invokeMethod($select0, 'getNestingLevel'));

        $select3 = new Select('*', $select2, 'p3');
        self::assertSame(3, $this->invokeMethod($select0, 'getNestingLevel'));
    }

    public function testMagicGetter()
    {
        $select = new Select('*', 'product', 'p');
        $select->distinct();
        $select->leftJoin('category', 'c', "c.id = p.category_id");
        $select->where->gte('id', 42);
        $select->having->lte('id', 4242);
        $select->groupBy('category_id');
        $select->orderBy('price');
        $select->limit(100);
        $select->offset(30);

        self::assertSame('product', $select->from);
        self::assertSame('p', $select->alias);
        self::assertSame(Sql::DISTINCT, $select->quantifier);
        self::assertInstanceOf(Sql\Clause\Join::class, $select->joins[0]);
        self::assertInstanceOf(Sql\Clause\Where::class, $select->where);
        self::assertInstanceOf(Sql\Clause\Having::class, $select->having);
        self::assertSame(['category_id'], $select->groupBy);
        self::assertSame(['price' => Sql::ASC], $select->orderBy);
        self::assertSame(100, $select->limit);
        self::assertSame(30, $select->offset);
        self::assertSame(null, $select->union);
        self::assertSame(null, $select->intersect);
        self::assertSame(null, $select->except);
        //self::assertSame(null, $select->combine);

        $this->expectException(RuntimeException::class);
        $select->nonexistentProperty;
    }
}
