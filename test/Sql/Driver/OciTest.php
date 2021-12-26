<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Driver;

use P3\Db\Exception\RuntimeException;
use P3\Db\Sql;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Expression;
use P3\Db\Sql\Identifier;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Params;
use P3\Db\Sql\Statement\Select;
use P3\DbTest\DiscloseTrait;
use PDO;
use PHPUnit\Framework\TestCase;

use function getenv;

class OciTest extends TestCase
{
    use DiscloseTrait;

    /** @var Driver\Oci */
    private $driver;

    /** @var PDO */
    private $pdo;

    public function setUp(): void
    {
        $host    = getenv('TEST_P3_DB_OCI_HOST');
        $port    = getenv('TEST_P3_DB_OCI_PORT');
        $dbname  = getenv('TEST_P3_DB_OCI_DBNAME');
        $charset = getenv('TEST_P3_DB_OCI_CHARSET');

        $oci_dsn = "oci:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        $this->driver = new Driver\Oci();

        if (! getenv('TEST_P3_DB_OCI')) {
            //$this->markTestSkipped('pdo-oci test is not enabled!');
            return;
        }

        $this->pdo = new PDO(
            $oci_dsn,
            getenv('TEST_P3_DB_OCI_USERNAME'),
            getenv('TEST_P3_DB_OCI_PASSWD')
        );
    }

    public function tearDown(): void
    {
        $this->driver = null;
        $this->pdo = null;
    }

    /**
     * @dataProvider provideIdentifiers
     */
    public function testQuoteIdentifier(string $identifier, ?string $expected)
    {
        self::assertSame($expected, $this->driver->quoteIdentifier($identifier));
    }

    public function provideIdentifiers(): array
    {
        return [
            ['*', '*'],
            ['USERNAME', 'USERNAME'],
            ['username', 'username'],
            ['_username', '"_username"'],
            ['TABLE.USERNAME', '"TABLE".USERNAME'],
            ['table.USERNAME', '"table".USERNAME'],
            ['table.username', '"table".username'],
            ['table._username', '"table"."_username"'],
            ['table.column', '"table"."column"'], // reserved table, table
            ['"u"."username"', '"u"."username"'], // leave them quoted?
        ];
    }

    /**
     * @dataProvider provideReservedWords
     */
    public function testQuoteReservedWordIdentifier(string $identifier, string $expected)
    {
        self::assertSame($expected, $this->driver->quoteIdentifier($identifier));
    }

    public function provideReservedWords(): array
    {
        return [
            ['betWeen', '"betWeen"'],
            ['char', '"char"'],
            ['EXISTS', '"EXISTS"'],
        ];
    }

    /**
     * @dataProvider provideAliases
     */
    public function testQuoteAlias(string $alias, string $expected)
    {
        self::assertSame($expected, $this->driver->quoteAlias($alias));
    }

    public function provideAliases(): array
    {
        return [
            ['u', '"u"'],
            ['"u"', '"u"'],
            ['some.alias', '"some.alias"'],
            ['"some.other.alias"', '"some.other.alias"'],
        ];
    }

    /**
     * @dataProvider provideNonStringTestValues
     */
    public function testQuoteNonStringValueWithoutConnectionWorks($value, string $expected)
    {
        self::assertSame($expected, $this->driver->quoteValue($value));
    }

    public function testQuoteStringValueWithoutConnectionRaisesException()
    {
        $this->expectException(RuntimeException::class);
        $this->driver->quoteValue("Quote me!");
    }

    /**
     * @dataProvider provideTestValues
     */
    public function testPdoQuoteAnyValueWithConnection($value, string $expected)
    {
        if (! getenv('TEST_P3_DB_OCI')) {
            $this->markTestSkipped('pdo-oci test is not enabled!');
        }

        $this->driver->setPDO($this->pdo);
        self::assertSame($expected, $this->driver->quoteValue($value));
    }

    public function provideNonStringTestValues(): array
    {
        return [
            [null, Sql::NULL],
            [false, Sql::FALSE],
            [true, Sql::TRUE],
            [42, '42'],
            [12.345, '12.345'],
        ];
    }

    public function provideTestValues(): array
    {
        return $this->provideNonStringTestValues() + [
            ["abc", "'abc'"],
            ["ab\\c", "'ab\\c'"],
            ["ab'c", "'ab''c'"],
        ];
    }

    public function testGetSelectColumnsSQL()
    {
        $select = new Select();
        $select->from('cart_product', 'cp');
        $select->columns([
            'product_name',
            'vatRate' => Sql::identifier('product.vat_rate'),
            'totPrice' => Sql::literal("(unit_price * quantity)"),
            'totVat' => Sql::expr("((unit_price * quantity / 100) * {vat_rate})", ['vat_rate' => 22]),
            'fortyTwo' => '42',
        ]);

        self::assertStringMatchesFormat(
            '"cp".product_name AS "product_name",'
            . ' product.vat_rate AS "vatRate",'
            . ' (unit_price * quantity) AS "totPrice",'
            . ' ((unit_price * quantity / 100) * :expr%d) AS "totVat",'
            . ' 42 AS "fortyTwo"',
            $this->driver->getSelectColumnsSQL($select, new Params())
        );
    }

    public function testGetSelectColumnsSqlWithoutColumnsSpecifications()
    {
        $select = new Select();
        $select->from('cart_product', 'cp');

        self::assertSame(
            '"cp".*',
            $this->driver->getSelectColumnsSQL($select, new Params())
        );
    }

    public function testGetSelectColumnsSqlWithJoin()
    {
        $select = new Select();
        $select->from('cart_product');
        $select->leftJoin('cart', 'c', 'c.id = cp.cart_id');

        self::assertSame(
            'cart_product.*',
            $this->driver->getSelectColumnsSQL($select, new Params())
        );
    }

    public function testGetSelectColumnsSqlWithColumnAndJoin()
    {
        $select = new Select();
        $select->column('unit_price', 'unitPrice');
        $select->column('"c".user_id', 'userId');
        $select->from('cart_product');
        $select->leftJoin('cart', 'c', 'c.id = cp.cart_id');

        self::assertSame(
            'cart_product.unit_price AS "unitPrice",'
            . ' "c".user_id AS "userId"',
            $this->driver->getSelectColumnsSQL($select, new Params())
        );
    }

    public function testCacheableColumnsSql()
    {
        // simple string column =  sql-asterisk
        $select = new Select([], 'product', 'p');
        $sql = $select->getSQL($this->driver);
        self::assertArrayHasKey('columns', $this->getPropertyValue($select, 'sqls'));

        // simple string columns
        $select = new Select(['id', 'name', 'price', 'vat_rate'], 'product', 'p');
        $sql = $select->getSQL($this->driver);
        self::assertArrayHasKey('columns', $this->getPropertyValue($select, 'sqls'));

        // identifier
        $select = new Select(new Identifier('p.price'), 'product', 'p');
        $sql = $select->getSQL($this->driver);
        self::assertArrayHasKey('columns', $this->getPropertyValue($select, 'sqls'));

        // literal
        $select = new Select(['fullPrice' => new Literal('price * (1 + vat_rate/100)')], 'product', 'p');
        $sql = $select->getSQL($this->driver);
        self::assertArrayHasKey('columns', $this->getPropertyValue($select, 'sqls'));

        // expression without params
        $select = new Select(['fullPrice' => new Expression('price * (1 + vat_rate/100)')], 'product', 'p');
        $sql = $select->getSQL($this->driver);
        self::assertArrayHasKey('columns', $this->getPropertyValue($select, 'sqls'));

        // expression with params
        $select = new Select([
            'fullPrice' => new Expression('price * (1 + {vat_rate}/100)', ['vat_rate' => 20.0])
        ], 'product', 'p');
        $sql = $select->getSQL($this->driver);
        self::assertArrayNotHasKey('columns', $this->getPropertyValue($select, 'sqls'));

        // select
        $select = new Select(['stock' => new Select('quantity', 'product_stock', 'ps')], 'product', 'p');
        $sql = $select->getSQL($this->driver);
        self::assertArrayNotHasKey('columns', $this->getPropertyValue($select, 'sqls'));
    }

    public function testGetLimitSQL()
    {
        $select = new Select('*', 'user');
        $select->limit(10)->offset(50);

        self::assertSame('', $this->driver->getLimitSQL($select, new Params()));
    }

    public function testDecorateSelectSQL()
    {
        $selectPrototype = new Select('*', 'user');
        $selectPrototype->where("id > 42");

        $sql = $selectPrototype->getSQL($this->driver, new Params());
        $sql = str_replace([" ", "\n"], '%w', $sql);

        $select = clone $selectPrototype;
        $select->limit(10);
        self::assertSame('', $this->driver->getLimitSQL($select, new Params()));
        self::assertStringMatchesFormat(
            "SELECT * FROM (%w{$sql}%w)%wWHERE ROWNUM <= :rownum%d",
            $this->driver->decorateSelectSQL($select, new Params())
        );

        $select = clone $selectPrototype;
        $select->limit(10)->offset(10);
        self::assertSame('', $this->driver->getLimitSQL($select, new Params()));
        self::assertStringMatchesFormat(
            "SELECT * FROM ("
            . "%wSELECT %s.*, ROWNUM AS %s"
            . "%wFROM (%w{$sql}%w) %s"
            . "%wWHERE ROWNUM <= :rownum%d%w)"
            . "%wWHERE %s > :offset%d",
            $this->driver->decorateSelectSQL($select, new Params())
        );

        $select = clone $selectPrototype;
        $select->offset(10);
        self::assertStringMatchesFormat(
            "SELECT * FROM ("
            . "%wSELECT %s.*, ROWNUM AS %s"
            . "%wFROM (%w{$sql}%w) %s"
            . "%w)%w"
            . "%wWHERE %s > :offset%d",
            $this->driver->decorateSelectSQL($select, new Params())
        );

        $select = clone $selectPrototype;
        self::assertSame(
            $select->getSQL($this->driver, new Params(), " "),
            $this->driver->decorateSelectSQL($select, new Params(), " ")
        );
    }

    public function testDecorateSelect()
    {
        $selectPrototype = new Select('*', 'user');
        $selectPrototype->where("id > 42");

        $sql = $selectPrototype->getSQL($this->driver, new Params());
        $sql = str_replace([" ", "\n"], '%w', $sql);

        $select = clone $selectPrototype;
        $select->limit(10);
        self::assertSame('', $this->driver->getLimitSQL($select, new Params()));
        $wrapper = $this->driver->decorateSelect($select, new Params());
        self::assertStringMatchesFormat(
            "SELECT %A*%wFROM (%w{$sql}%w)%A%wWHERE ROWNUM <= :lte%d",
            $this->invokeMethod($wrapper, 'generateSQL', $this->driver, new Params(), " ")
        );

        $select = clone $selectPrototype;
        $select->limit(10)->offset(10);
        self::assertSame('', $this->driver->getLimitSQL($select, new Params()));
        $wrapper = $this->driver->decorateSelect($select, new Params());
        self::assertStringMatchesFormat(
            "SELECT %A*"
            . "%wFROM"
            . "%w("
                . "%wSELECT %A*, ROWNUM AS %s"
                . "%wFROM (%w{$sql}%w)%A"
                . "%wWHERE ROWNUM <= :lte%d"
            . "%w)%A"
            . "%wWHERE %s > :gt%d",
            $this->invokeMethod($wrapper, 'generateSQL', $this->driver, new Params())
        );

        $select = clone $selectPrototype;
        $select->offset(10);
        self::assertSame('', $this->driver->getLimitSQL($select, new Params()));
        $wrapper = $this->driver->decorateSelect($select, new Params());
        self::assertStringMatchesFormat(
            "SELECT %A*%wFROM%w(%wSELECT %A*, ROWNUM AS %s%wFROM (%w{$sql}%w)%A)%A%wWHERE %s > :gt%d",
            $this->invokeMethod($wrapper, 'generateSQL', $this->driver, new Params())
        );
    }

    public function testDecorateSelectReturnSameInstanceIfNoLimits()
    {
        $select = new Select('*', 'user');
        self::assertSame($select, $this->driver->decorateSelect($select, new Params()));
    }
}
