<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Driver;

use PDO;
use PHPUnit\Framework\TestCase;
use P3\Db\Sql;
use P3\Db\Sql\Driver;
use RuntimeException;

use function getenv;

class OciTest extends TestCase
{
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

    public function tearDown()
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
            ['username', 'username'],
            ['"username"', '"username"'],
            ['_username', '"_username"'],
            ['table._username', 'table."_username"'],
            ['"u"."username"', '"u"."username"'],
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

    public function testGetColumnsSQL()
    {
        $select = new Sql\Statement\Select();
        $select->from('cart_product', 'cp');
        $select->columns([
            'product_name',
            'totPrice' => Sql::literal("(unit_price * quantity)"),
            'totVat' => Sql::expr("((unit_price * quantity / 100) * {vat_rate})", ['vat_rate' => 22]),
        ]);

        self::assertStringMatchesFormat(
            '"cp".product_name AS "product_name",'
            . ' (unit_price * quantity) AS "totPrice",'
            . ' ((unit_price * quantity / 100) * :expr%d) AS "totVat"',
            $this->driver->getColumnsSQL($select)
        );
    }

    public function testGetColumnsSqlWithoutColumnsSpecifications()
    {
        $select = new Sql\Statement\Select();
        $select->from('cart_product', 'cp');

        self::assertSame(
            '"cp".*',
            $this->driver->getColumnsSQL($select)
        );
    }

    public function testGetColumnsSqlWithJoin()
    {
        $select = new Sql\Statement\Select();
        $select->from('cart_product');
        $select->leftJoin('cart', 'c', 'c.id = cp.cart_id');

        self::assertSame(
            'cart_product.*',
            $this->driver->getColumnsSQL($select)
        );
    }

    public function testGetColumnsSqlWithColumnAndJoin()
    {
        $select = new Sql\Statement\Select();
        $select->column('unit_price', 'unitPrice');
        $select->column('"c".user_id', 'userId');
        $select->from('cart_product');
        $select->leftJoin('cart', 'c', 'c.id = cp.cart_id');

        self::assertSame(
            'cart_product.unit_price AS "unitPrice",'
            . ' "c".user_id AS "userId"',
            $this->driver->getColumnsSQL($select)
        );
    }

    public function testGetLimitSQL()
    {
        $select = new Sql\Statement\Select('*', 'user');
        $select->limit(10)->offset(50);

        self::assertSame('', $this->driver->getLimitSQL($select));
    }

    public function testDecoratedSelectSQL()
    {
        $selectPrototype = new Sql\Statement\Select('*', 'user');
        $selectPrototype->where("id > 42");

        $sql = $selectPrototype->getSQL($this->driver);

        $select = clone $selectPrototype;
        $select->limit(10);
        self::assertStringMatchesFormat(
            "SELECT * FROM ({$sql}) WHERE ROWNUM <= :limit%d",
            $this->driver->decorateSelectSQL($select, $sql)
        );

        $select = clone $selectPrototype;
        $select->limit(10)->offset(10);
        self::assertStringMatchesFormat(
            "SELECT * FROM (SELECT %s.*, ROWNUM AS %s FROM ({$sql}) %s WHERE ROWNUM <= :limit%d) WHERE %s > :offset%d",
            $this->driver->decorateSelectSQL($select, $sql)
        );

        $select = clone $selectPrototype;
        $select->offset(10);
        self::assertStringMatchesFormat(
            "SELECT * FROM (SELECT %s.*, ROWNUM AS %s FROM ({$sql}) %s WHERE ROWNUM <= %d) WHERE %s > :offset%d",
            $this->driver->decorateSelectSQL($select, $sql)
        );
    }
}
