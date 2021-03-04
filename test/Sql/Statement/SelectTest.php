<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Statement;

use InvalidArgumentException;
//use PDO;
use PHPUnit\Framework\TestCase;
use P3\Db\Sql\Clause\Join;
use P3\Db\Sql\Driver;
use P3\Db\Sql;
use P3\Db\Sql\Alias;
use P3\Db\Sql\Expression;
use P3\Db\Sql\Identifier;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Statement\Select;
use RuntimeException;

use function getenv;

class SelectTest extends TestCase
{
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

        self::assertSame("SELECT {$expected_columns_sql} FROM `customer`", $select->getSQL($this->driver));
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
                "CONCAT('ABC', :expr1)",
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

        self::assertSame("SELECT {$column_sql} FROM `product`", $select->getSQL($this->driver));
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
                "SUM(unit_price) + :expr2 AS `totalPrice`",
            ],
        ];
    }

    public function textExceptionsOnInvalidColumnTypes()
    {
        $select = new Select(null, 'user');

        $this->expectException(InvalidArgumentException::class);
        $select->columns([new \stdClass()]);
        $this->expectException(InvalidArgumentException::class);
        $select->column(new \stdClass());

        $this->expectException(InvalidArgumentException::class);
        $select->columns([null]);
        $this->expectException(InvalidArgumentException::class);
        $select->column(null);

        $this->expectException(InvalidArgumentException::class);
        $select->columns([0]);
        $this->expectException(InvalidArgumentException::class);
        $select->column(0);
    }

    public function testSelectWithoutFromRisesExceptionOnGetSQL()
    {
        $select = new Select();
        $select->columns([]);

        $this->expectException(RuntimeException::class);
        self::assertSame("SELECT *", $select->getSQL($this->driver));
    }

    public function testInvalidFromRisesException()
    {
        $this->expectException(InvalidArgumentException::class);
        (new Select())->from('', null);

        $this->expectException(InvalidArgumentException::class);
        (new Select())->from(new stdClass(), null);
    }

    public function testFromSubselectWithEmptyAliasRisesException()
    {
        $this->expectException(InvalidArgumentException::class);
        (new Select())->from(new Select([], 'subtable'), null);

        $this->expectException(InvalidArgumentException::class);
        (new Select())->from(new Select([], 'subtable', ''), null);
    }

    public function testSelectFromTable()
    {
        ($select = new Select())->from('product', null);
        self::assertSame("SELECT * FROM `product`", $select->getSQL($this->driver));

        ($select = new Select())->from('product', 'p');
        self::assertSame("SELECT `p`.* FROM `product` `p`", $select->getSQL($this->driver));
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
        self::assertSame(
            "SELECT * FROM `product` ORDER BY {$expectedSQL}",
            $select->getSQL($this->driver)
        );

        // test replace
        $select->orderBy(['unit_price' => 'DESC'], true);
        self::assertSame(
            "SELECT * FROM `product` ORDER BY `unit_price` DESC",
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
        ];
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
}
