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
use P3\Db\Sql\Driver;
use P3\Db\Sql;
use P3\Db\Sql\Expression;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Statement\Select;
use RuntimeException;

use function getenv;

class SelectTest extends TestCase
{
//    /** @var PDO */
//    private $pdo;
//
//    /** @var Driver\MySql */
//    private $driver;
//
    public function setUp(): void
    {
//        if (! getenv('TEST_P3_DB_MYSQL')) {
//            $this->markTestSkipped('pdo-mysql test is not enabled!');
//        }
//
//        $host    = getenv('TEST_P3_DB_MYSQL_HOST');
//        $port    = getenv('TEST_P3_DB_MYSQL_PORT');
//        $dbname  = getenv('TEST_P3_DB_MYSQL_DBNAME');
//        $charset = getenv('TEST_P3_DB_MYSQL_CHARSET');
//
//        $mysql_dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
//
//        $this->pdo = new PDO(
//            $mysql_dsn,
//            getenv('TEST_P3_DB_MYSQL_USERNAME'),
//            getenv('TEST_P3_DB_MYSQL_PASSWD')
//        );
//
        $this->driver = new Driver\MySql();
    }

    public function tearDown()
    {
        $this->driver = null;
//        $this->pdo = null;
    }

    public function testSelectWithoutFromRisesExceptionOnGetSQL()
    {
        $select = new Select();
        $select->columns([]);

        $this->expectException(RuntimeException::class);
        self::assertEquals("SELECT *", $select->getSQL($this->driver));
    }

    /**
     * @dataProvider provideColumns
     */
    public function testSelectColumns($columns, $expected_columns_sql)
    {
        $select = new Select();
        $select->columns($columns);
        $select->from('customer');

        self::assertEquals("SELECT {$expected_columns_sql} FROM `customer`", $select->getSQL($this->driver));
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

        self::assertEquals("SELECT `c`.* FROM `customer` `c`", $select->getSQL($this->driver));

        $select = new Select();
        $select->columns([
            'id',
            'c.name'
        ]);
        $select->from('customer', 'c');

        self::assertEquals("SELECT `c`.`id`, `c`.`name` FROM `customer` `c`", $select->getSQL($this->driver));
    }

    /**
     * @dataProvider provideColumn
     */
    public function testSelectColumn($column, ?string $alias, string $column_sql)
    {
        $select = new Select(null, 'product');
        $select->column($column, $alias);

        self::assertEquals("SELECT {$column_sql} FROM `product`", $select->getSQL($this->driver));
    }

    public function provideColumn(): array
    {
        return [
            ['unit_price', null, "`unit_price`"],
            ['unit_price', 'unitPrice', "`unit_price` AS `unitPrice`"],
            ['p.unit_price', 'unitPrice', "`p`.`unit_price` AS `unitPrice`"],
            ['`p`.`unit_price`', 'unitPrice', "`p`.`unit_price` AS `unitPrice`"],
            [new Literal("SUM(unit_price)"), 'totalPrice', "SUM(unit_price) AS `totalPrice`"],
            [new Expression("SUM(unit_price) + {tax}", ['tax' => 5.00]), 'totalPrice', "SUM(unit_price) + :expr2 AS `totalPrice`"],
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
}
