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
            . ' ((unit_price * quantity / 100) * :expr%x) AS "totVat"',
            $this->driver->getSelectColumnsSQL($select)
        );
    }

    public function testGetSelectColumnsSqlWithoutColumnsSpecifications()
    {
        $select = new Sql\Statement\Select();
        $select->from('cart_product', 'cp');

        self::assertSame(
            '"cp".*',
            $this->driver->getSelectColumnsSQL($select)
        );
    }

    public function testGetSelectColumnsSqlWithJoin()
    {
        $select = new Sql\Statement\Select();
        $select->from('cart_product');
        $select->leftJoin('cart', 'c', 'c.id = cp.cart_id');

        self::assertSame(
            'cart_product.*',
            $this->driver->getSelectColumnsSQL($select)
        );
    }

    public function testGetSelectColumnsSqlWithColumnAndJoin()
    {
        $select = new Sql\Statement\Select();
        $select->column('unit_price', 'unitPrice');
        $select->column('"c".user_id', 'userId');
        $select->from('cart_product');
        $select->leftJoin('cart', 'c', 'c.id = cp.cart_id');

        self::assertSame(
            'cart_product.unit_price AS "unitPrice",'
            . ' "c".user_id AS "userId"',
            $this->driver->getSelectColumnsSQL($select)
        );
    }

    public function testGetLimitSQL()
    {
        $select = new Sql\Statement\Select('*', 'user');
        $select->limit(10)->offset(50);

        self::assertSame('', $this->driver->getLimitSQL($select));
    }

    public function testDecorateSelectSQL()
    {
        $selectPrototype = new Sql\Statement\Select('*', 'user');
        $selectPrototype->where("id > 42");

        $sql = $selectPrototype->getSQL($this->driver);
        $sql = str_replace([" ", "\n"], '%w', $sql);

        $select = clone $selectPrototype;
        $select->limit(10);
        self::assertSame('', $this->driver->getLimitSQL($select));
        self::assertStringMatchesFormat(
            "SELECT * FROM ({$sql}) WHERE ROWNUM <= :limit%x",
            $this->driver->decorateSelectSQL($select)
        );

        $select = clone $selectPrototype;
        $select->limit(10)->offset(10);
        self::assertSame('', $this->driver->getLimitSQL($select));
        self::assertStringMatchesFormat(
            "SELECT * FROM (SELECT %s.*, ROWNUM AS %s FROM ({$sql}) %s WHERE ROWNUM <= :limit%x) WHERE %s > :offset%x",
            //$select->getSQL($this->driver)
            $this->driver->decorateSelectSQL($select)
        );

        $select = clone $selectPrototype;
        $select->offset(10);
        self::assertStringMatchesFormat(
            "SELECT * FROM (SELECT %s.*, ROWNUM AS %s FROM ({$sql}) %s) WHERE %s > :offset%x",
            $this->driver->decorateSelectSQL($select)
        );
    }

    public function testDecorateSelect()
    {
        $selectPrototype = new Sql\Statement\Select('*', 'user');
        $selectPrototype->where("id > 42");

        $sql = $selectPrototype->getSQL($this->driver);
        $sql = str_replace([" ", "\n"], '%w', $sql);

        $select = clone $selectPrototype;
        $select->limit(10);
        self::assertSame('', $this->driver->getLimitSQL($select));
        $wrapper = $this->driver->decorateSelect($select);
        self::assertStringMatchesFormat(
            "SELECT %A*%wFROM ({$sql})%A%wWHERE ROWNUM <= :lte%x",
            $this->invokeMethod($wrapper, 'generateSQL', $this->driver)
        );

        $select = clone $selectPrototype;
        $select->limit(10)->offset(10);
        self::assertSame('', $this->driver->getLimitSQL($select));
        $wrapper = $this->driver->decorateSelect($select);
        self::assertStringMatchesFormat(
            "SELECT %A*"
            . "%wFROM"
            . "%w("
                . "%wSELECT %A*, ROWNUM AS %s"
                . "%wFROM ({$sql})%A"
                . "%wWHERE ROWNUM <= :lte%x"
            . "%w)%A"
            . "%wWHERE %s > :gt%x",
            $this->invokeMethod($wrapper, 'generateSQL', $this->driver)
        );

        $select = clone $selectPrototype;
        $select->offset(10);
        self::assertSame('', $this->driver->getLimitSQL($select));
        $wrapper = $this->driver->decorateSelect($select);
        self::assertStringMatchesFormat(
            "SELECT %A*%wFROM%w(SELECT %A*, ROWNUM AS %s%wFROM ({$sql})%A)%A%wWHERE %s > :gt%x",
            $this->invokeMethod($wrapper, 'generateSQL', $this->driver)
        );
    }
}
