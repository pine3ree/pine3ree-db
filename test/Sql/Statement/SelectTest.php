<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Statement;

use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Alias;
use P3\Db\Sql\Clause\Join;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Expression;
use P3\Db\Sql\Identifier;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Statement\Select;
use P3\DbTest\DiscloseTrait;
use PHPUnit\Framework\TestCase;
use P3\Db\Exception\RuntimeException;
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

    public function tearDown()
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
            "SELECT {$expected_columns_sql} FROM `customer`",
            $select->getSQL($this->driver)
        );
    }

    public function provideColumns(): array
    {
        return [
            [null, "*"],
            [[], "*"],
            ['*', "*"],
            ['', "*"],
            [
                new Literal("COUNT(*)"),
                "COUNT(*)",
            ],
            [
                new Expression("CONCAT('ABC', {str})", ['str' => 'DEF']),
                "CONCAT('ABC', :expr%x)",
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

        self::assertSame("SELECT `c`.* FROM `customer` `c`", $select->getSQL($this->driver));

        $select = new Select();
        $select->columns([
            'id',
            'c.name'
        ]);
        $select->from('customer', 'c');

        self::assertSame("SELECT `c`.`id`, `c`.`name` FROM `customer` `c`", $select->getSQL($this->driver));
    }

    /**
     * @dataProvider provideColumn
     */
    public function testSelectColumn($column, ?string $alias, string $column_sql)
    {
        $select = new Select(null, 'product');
        $select->column($column, $alias);

        self::assertStringMatchesFormat("SELECT {$column_sql} FROM `product`", $select->getSQL($this->driver));
    }

    public function provideColumn(): array
    {
        return [
            ['unit_price', null, "`unit_price`"],
            ['unit_price', 'unitPrice', "`unit_price` AS `unitPrice`"],
            ['p.unit_price', 'unitPrice', "`p`.`unit_price` AS `unitPrice`"],
            ['`p`.`unit_price`', 'unitPrice', "`p`.`unit_price` AS `unitPrice`"],
            [new Literal("SUM(unit_price)"), 'totalPrice', "SUM(unit_price) AS `totalPrice`"],
            [
                new Expression("SUM(unit_price) + {tax}", ['tax' => 5.00]),
                'totalPrice',
                "SUM(unit_price) + :expr%x AS `totalPrice`",
            ],
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
        ($select = new Select())->column('unit_price', 'unitPrice')->from('product', 'p');
        self::assertSame(
            'SELECT "p".unit_price AS "unitPrice" FROM product "p"',
            $select->getSQL(new Driver\Oci())
        );
    }

    public function testSelectFromTable()
    {
        ($select = new Select())->from('product', null);
        self::assertSame("SELECT * FROM `product`", $select->getSQL($this->driver));

        ($select = new Select())->from('product', 'p');
        self::assertSame("SELECT `p`.* FROM `product` `p`", $select->getSQL($this->driver));
    }

    public function testSelectWithJoinAndNOAliasTriggerTablePrefix()
    {
        ($select = new Select())->from('product', null);
        $select->column('*');
        $select->column('c.name', 'categoryName');
        $select->leftJoin('category', 'c', 'c.id = product.category_id');

        self::assertSame(
            "SELECT `product`.*, `c`.`name` AS `categoryName`"
            . " FROM `product`"
            . " LEFT JOIN `category` `c` ON (`c`.id = `product`.category_id)",
            $select->getSQL($this->driver)
        );
    }

    public function testCallingFromAgainRaisesException()
    {
        ($select = new Select())->from('product', null);

        $this->expectException(RuntimeException::class);
        $select->from('another_table');
    }

    public function testSelectFromSubselect()
    {
        ($subSelect = new Select())->from('cart');
        ($select = new Select())->from($subSelect, 'c');
        self::assertSame("SELECT `c`.* FROM (SELECT * FROM `cart`) `c`", $select->getSQL($this->driver));

        ($subSelect = new Select())->from('cart_product', 'cp')->where->gt('cp.price', 0);
        ($select = new Select())->from($subSelect, 'p');
        self::assertStringStartsWith(
            "SELECT `p`.* FROM (SELECT `cp`.* FROM `cart_product` `cp` WHERE `cp`.`price` > :gt",
            $select->getSQL($this->driver)
        );
    }

    public function testSelectWithLimit()
    {
        ($select = new Select())->from('user')->limit(10);
        self::assertStringStartsWith(
            "SELECT * FROM `user` LIMIT :limit",
            $select->getSQL($this->driver)
        );

        $params = $select->getParams();
        self::assertSame(10, current($params) ?? null);
    }

    public function testSelectWithNegativeLimit()
    {
        ($select = new Select())->from('user')->limit(-1);
        self::assertStringStartsWith(
            "SELECT * FROM `user` LIMIT :limit",
            $select->getSQL($this->driver)
        );

        $params = $select->getParams();
        self::assertSame(0, current($params) ?? null);
    }

    public function testSelectWithOffset()
    {
        ($select = new Select())->from('user')->offset(100);
        self::assertStringStartsWith(
            "SELECT * FROM `user` LIMIT " . PHP_INT_MAX . " OFFSET :offset",
            $select->getSQL($this->driver)
        );

        $params = $select->getParams();
        self::assertSame(100, current($params) ?? null);
    }

    public function testSelectZeroOrNegativeOffsetIsDiscarded()
    {
        ($select = new Select())->from('user')->offset(0);
        self::assertSame(
            "SELECT * FROM `user`",
            $select->getSQL($this->driver)
        );

        ($select = new Select())->from('user')->offset(-1);
        self::assertSame(
            "SELECT * FROM `user`",
            $select->getSQL($this->driver)
        );
    }

    public function testSelectWithLimitAndOffset()
    {
        ($select = new Select())->from('user')->limit(10)->offset(100);

        self::assertStringStartsWith(
            "SELECT * FROM `user` LIMIT :limit",
            $select->getSQL($this->driver)
        );
        self::assertNotFalse(strpos($select->getSQL($this->driver), " OFFSET :offset"));

        $params = $select->getParams();
        self::assertSame(10, current($params) ?? null);
        self::assertSame(100, next($params) ?? null);
    }

    public function testAnsiDriverDoesNotSupportLimitAndOffset()
    {
        ($select = new Select())->from('user')->limit(10)->offset(100);
        self::assertSame(
            'SELECT * FROM "user" [LIMIT 10 OFFSET 100]',
            $select->getSQL(Driver::ansi())
        );

        self::assertEmpty($select->getParams());
    }

    /**
     * @dataProvider provideGroupBy
     */
    public function testGroupByClause($groupBy, string $expectedSQL)
    {
        $select = (new Select())->sum("unit_price*quantity", "productTotal")->from('cart_product');

        $select->groupBy($groupBy);
        self::assertSame(
            "SELECT SUM(unit_price*quantity) AS `productTotal` FROM `cart_product` GROUP BY {$expectedSQL}",
            $select->getSQL($this->driver)
        );

        // test replace
        $select->groupBy(['tax_id'], true);
        self::assertSame(
            "SELECT SUM(unit_price*quantity) AS `productTotal` FROM `cart_product` GROUP BY `tax_id`",
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

    /**
     * @dataProvider provideOrderBy
     */
    public function testOrderByClause($orderBy, $sortDirOrReplace, string $expectedSQL)
    {
        $select = (new Select())->from('product');
        $select->orderBy($orderBy, $sortDirOrReplace);

        $orderBySQL = $expectedSQL ? " ORDER BY {$expectedSQL}" : '';

        self::assertSame(
            "SELECT * FROM `product`{$orderBySQL}",
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
            . " FROM `product` `p`"
            . " GROUP BY `category_id`"
            . " HAVING `totByCategory` > :gt%x",
            $select->getSQL($this->driver)
        );
    }

    public function testJoinClause()
    {
        // using string a specification => will generate a ON clause
        $select = (new Select(['*', "c.*"]))->from('order', 'o');
        $select->leftJoin('customer', 'c', "c.id = o.customer_id");
        self::assertSame(
            "SELECT `o`.*, `c`.* FROM `order` `o` LEFT JOIN `customer` `c` ON (`c`.id = `o`.customer_id)",
            $select->getSQL($this->driver)
        );

        // using literal-predicate a specification
        $select = (new Select())->from('user', 'u');
        $select->leftJoin('customer', 'c', new Sql\Predicate\Literal("USING (customer_id)"));
        self::assertSame(
            "SELECT `u`.* FROM `user` `u` LEFT JOIN `customer` `c` USING (customer_id)",
            $select->getSQL($this->driver)
        );

        // multiple join
        $select = (new Select(['*', "o.*", 'c.*']))->from('order_product', 'op');
        $select->leftJoin('order', 'o', "op.order_id = o.id");
        $select->leftJoin('customer', 'c', "c.id = o.customer_id");
        self::assertSame(
            "SELECT `op`.*, `o`.*, `c`.* FROM `order_product` `op`"
            . " LEFT JOIN `order` `o` ON (`op`.order_id = `o`.id)"
            . " LEFT JOIN `customer` `c` ON (`c`.id = `o`.customer_id)",
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
        self::assertSame(
            "SELECT `op`.*, `o`.*, `c`.* FROM `order_product` `op`"
            . " INNER JOIN `order` `o` ON (`op`.`order_id` = `o`.`id`)"
            . " INNER JOIN `customer` `c` ON (`c`.`id` = `o`.`customer_id`)",
            $select->getSQL($this->driver)
        );
    }

    public function testThatAddIntersectAfterUnionRaisesExceptiom()
    {
        $select = new Select('*', 'product', 'p');
        $union = (new Select('*', 'store1_product'))->orderBy('price');
        $select->union($union);
        $select->union; // triggers clear SQL cache

        $this->expectException(RuntimeException::class);
        $select->intersect(new Select('*', 'store2_product'));
    }

    public function testThatAddUnionAfterIntersectRaisesExceptiom()
    {
        $select = new Select('*', 'product', 'p');
        $intersect = (new Select('*', 'store1_product'))->orderBy('price');
        $select->intersect($intersect);
        $select->intersect; // triggers clear SQL cache

        $this->expectException(RuntimeException::class);
        $select->union(new Select('*', 'store2_product'));
    }

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

        // literal
        $select = new Select(['fullPrice' => new Literal('price * (1 + vat_rate/100)')], 'product', 'p');
        $sql = $select->getSQL();
        self::assertArrayHasKey('columns', $this->getPropertyValue($select, 'sqls'));

        // expression without params
        $select = new Select(['fullPrice' => new Expression('price * (1 + vat_rate/100)')], 'product', 'p');
        $sql = $select->getSQL();
        self::assertArrayHasKey('columns', $this->getPropertyValue($select, 'sqls'));
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
        $select = new Select(['id', 'name', 'price', 'vat_rate'], 'product', 'p');
        $select->sum('price', 'totPrice');
        $select->where->gte('price', 0.5);
        $select->leftJoin('category', 'c', "c.id = p.category_id");
        $select->groupBy('p.category_id');
        $select->having->gte('totPrice', 10.0);
        $select->orderBy('p.price');
        $select->limit(10)->offset(20);

        $sql = $select->getSQL();

        self::assertArrayHasKey('columns', $this->getPropertyValue($select, 'sqls'));
        $this->invokeMethod($select, 'clearPartialSQL', 'columns');
        self::assertArrayNotHasKey('columns', $this->getPropertyValue($select, 'sqls'));

        self::assertSame($sql, $select->getSQL());

        self::assertArrayHasKey('group', $this->getPropertyValue($select, 'sqls'));
        $this->invokeMethod($select, 'clearPartialSQL', 'group');
        self::assertArrayNotHasKey('group', $this->getPropertyValue($select, 'sqls'));

        self::assertSame($sql, $select->getSQL());

        self::assertArrayHasKey('order', $this->getPropertyValue($select, 'sqls'));
        $this->invokeMethod($select, 'clearPartialSQL', 'order');
        self::assertArrayNotHasKey('order', $this->getPropertyValue($select, 'sqls'));
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
        self::assertSame(null, $select->union_all);

        $this->expectException(RuntimeException::class);
        $select->nonexistentProperty;
    }
}
