<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest;

use P3\Db\Command\Delete;
use P3\Db\Command\Insert;
use P3\Db\Command\Select;
use P3\Db\Command\Update;
use P3\Db\Db;
use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Exception\RuntimeException;
use P3\Db\Sql;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Driver\Sqlite;
use PDO;
use PHPUnit\Framework\TestCase;
use stdClass;

class DbTest extends TestCase
{
    use DiscloseTrait;

    /**
     * The Db instance used to interact with sqlite memory database
     *
     * @var Db
     */
    private $db;

    /**
     * The PDO instance used to interact with sqlite memory database
     *
     * @var PDO
     */
    private $pdo;

    /** @var string */
    private const DSN = 'sqlite::memory:';

    public function setUp(): void
    {
        $this->pdo = new PDO(self::DSN);
        $this->db = new Db($this->pdo);

        $createTableSQL = <<<EOCS
CREATE TABLE "product" (
  "id" integer NOT NULL DEFAULT '0' PRIMARY KEY AUTOINCREMENT,
  "name" text NOT NULL,
  "price" real NOT NULL DEFAULT '0.00000',
  "tax_rate" real NOT NULL DEFAULT '0.00',
  "published" integer(1) NOT NULL DEFAULT '0',
  "created_at" text(19) COLLATE 'NOCASE' NOT NULL DEFAULT '0000-00-00 00:00:00',
  "updated_at" text(19) COLLATE 'NOCASE' NOT NULL DEFAULT '0000-00-00 00:00:00'
)
EOCS;
        $this->db->exec($createTableSQL);
        $this->db->exec("UPDATE sqlite_sequence SET seq = 1 WHERE name = 'product'");

        for ($i = 1; $i < 10; $i += 1) {
            $price = mt_rand(10000, 100000) / 1000;
            $published = mt_rand(0, 1);
            $created_at = date('Y-m-d H:i:s', time() + mt_rand(-86400 * 70, -86400 * 35));
            $updated_at = date('Y-m-d H:i:s', time() + mt_rand(-86400 * 28, 86400 * 7));
            $this->db->exec(<<<EOIS
INSERT INTO "product" ("name", "price", "tax_rate", "published", "created_at", "updated_at")
VALUES ('product-{$i}', '{$price}', '22', '{$published}', '{$created_at}', '{$updated_at}');
EOIS
            );
        }
    }

    public function tearDown()
    {
        unset($this->db);
        unset($this->pdo);
    }

    public function testInvalidPdoClassRaisesException()
    {
        $this->expectException(InvalidArgumentException::class);
        $db = new Db(
            'mysql:host=localhost;dbname=testdb',
            'testuser',
            'secret',
            [],
            stdClass::class
        );
    }

    public function testInvalidFirstCtorArgumentException()
    {
        $this->expectException(InvalidArgumentException::class);
        $db = new Db(new stdClass());
    }

    public function testIsConnected()
    {
        $db = new Db(self::DSN);
        self::assertFalse($db->isConnected());

        $pdo = $this->prophesize(PDO::class);
        $db = new Db($pdo->reveal());
        self::assertTrue($db->isConnected());
    }

    public function testConnect()
    {
        $db = new Db(self::DSN);
        $this->invokeMethod($db, 'connect');
        self::assertTrue($db->isConnected());

        $db = new Db(self::DSN);
        $this->invokeMethod($db, 'connect', true);
        self::assertTrue($db->isConnected());
    }

    public function testDisconnect()
    {
        $pdo = $this->prophesize(PDO::class);

        $db = new Db($pdo->reveal());
        self::assertTrue($db->isConnected());
        $this->invokeMethod($db, 'disconnect');
        self::assertFalse($db->isConnected());
    }

    public function testReconnect()
    {
        $db = new Db(self::DSN);
        $pdo = $db->getPDO(true);

        self::assertTrue($db->isConnected());
        $this->invokeMethod($db, 'reconnect');
        self::assertTrue($db->isConnected());

        self::assertInstanceOf(PDO::class, $db->getPDO(false));
        self::assertNotSame($pdo, $db->getPDO(true));

        // reconnect without DNS
        $db = new Db($pdo);
        $this->expectException(RuntimeException::class);
        $this->invokeMethod($db, 'reconnect');
    }

    public function testInitializePDO()
    {
        $options = [
            PDO::ATTR_TIMEOUT => 15,
        ];

        $pdo = $this->prophesize(PDO::class);

        $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)
            ->willReturn('pgsql');

        foreach ($options as $attr => $value) {
            $pdo->setAttribute($attr, $value)->shouldBeCalledOnce();
        }

        $pdo->exec("SET NAMES 'utf8'")->shouldBeCalledOnce();

        $db = new Db($pdo->reveal(), 'user', 'pass', [
            PDO::ATTR_TIMEOUT => 15,
        ]);

        self::assertTrue($this->invokeMethod($db, 'initializePDO'));
        // already initialized test
        self::assertTrue($this->invokeMethod($db, 'initializePDO'));
    }

    public function testCallInitializePdoWithoutPdoReturnsFalse()
    {
        $db = new Db(self::DSN);

        self::assertFalse($this->invokeMethod($db, 'initializePDO'));
    }

    public function testhatGetShallowDriverDoesNotCreateConnection()
    {
        $db = new Db(self::DSN);
        $db->getDriver(false);
        self::assertFalse($db->isConnected());
    }

    public function testthatGetDriverCreatesConnection()
    {
        $db = new Db(self::DSN);
        $db->getDriver(true);
        self::assertTrue($db->isConnected());
    }

    public function testthatGetShallowDriverCallsReturnTheSameInstance()
    {
        $db = new Db(self::DSN);

        $driver = $db->getDriver();

        self::assertSame($driver, $db->getDriver());
    }

    public function testthatGetDriverCallsReturnTheSameInstance()
    {
        $db = new Db(self::DSN);

        $driver = $db->getDriver(true);
        self::assertSame($driver, $db->getDriver(true));
        self::assertSame($driver, $db->getDriver());
    }

    public function testthatShallowDriverWillBeInjectedWithPdo()
    {
        $db = new Db(self::DSN);

        $driver = $db->getDriver(false);
        $pdo = $db->getPDO(true);
        $driver = $db->getDriver(false);

        self::assertSame($pdo, $this->getPropertyValue($driver, 'pdo'));
    }

    public function testInvalidDsnPrefixLeadsToAnsiDriverInstance()
    {
        $db = new Db('faqlite:dbname=/bar/lib/fake.db');
        self::assertSame(Driver::ansi(), $db->getDriver(false));
    }

    public function testGetPdo()
    {
        $db = new Db(self::DSN);

        // connection is not triggered
        $db->getPDO();
        self::assertFalse($db->isConnected());

        // connection is not triggered
        $db->getPDO(true);
        self::assertTrue($db->isConnected());

        // connection is already triggered
        $db->getPDO();
        self::assertTrue($db->isConnected());
    }

    public function testthatCreatePdoInstanceWithEmptyDsnRaisesException()
    {
        $pdo = $this->prophesize(PDO::class);
        $db = new Db($pdo->reveal());

        $this->expectException(RuntimeException::class);
        $this->invokeMethod($db, 'createPDO');
    }

    public function testSelectCall()
    {
        $db = new Db(self::DSN);
        self::assertInstanceOf(Select::class, $db->select());
    }

    public function testFetchOneBy()
    {
        $row = $this->db->fetchOneBy('product', 'name', 'product-3');

        self::assertInternalType('array', $row);

        self::assertArrayHasKey('id', $row);
        self::assertArrayHasKey('name', $row);
        self::assertArrayHasKey('price', $row);
        self::assertArrayHasKey('tax_rate', $row);
        self::assertArrayHasKey('published', $row);
        self::assertArrayHasKey('created_at', $row);
        self::assertArrayHasKey('updated_at', $row);

        self::assertSame('3', $row['id']);
    }

    public function testFetchOneByNotFound()
    {
        $row = $this->db->fetchOneBy('product', 'name', 'product-33');
        self::assertNull($row);
    }

    public function testFetchOne()
    {
        $row = $this->db->fetchOne('product');
        self::assertInternalType('array', $row);
        self::assertSame('1', $row['id']);
        self::assertSame('product-1', $row['name']);

        $row = $this->db->fetchOne('product', ['id' => 2]);
        self::assertInternalType('array', $row);
        self::assertSame('2', $row['id']);
        self::assertSame('product-2', $row['name']);

        $row = $this->db->fetchOne('product', 'id < 7', ['id' => 'DESC']);
        self::assertInternalType('array', $row);
        self::assertSame('6', $row['id']);
        self::assertSame('product-6', $row['name']);
    }

    public function testFetchAll()
    {
        $rows = $this->db->fetchAll('product');
        self::assertCount(9, $rows);

        $row = $rows[0] ?? null;
        self::assertInternalType('array', $row);
        self::assertArrayHasKey('id', $row);
        self::assertArrayHasKey('name', $row);
        self::assertArrayHasKey('price', $row);
        self::assertArrayHasKey('tax_rate', $row);
        self::assertArrayHasKey('published', $row);
        self::assertArrayHasKey('created_at', $row);
        self::assertArrayHasKey('updated_at', $row);
        self::assertSame('1', $row['id']);

        $rows = $this->db->fetchAll('product', "id > 2");
        self::assertCount(7, $rows);

        $rows = $this->db->fetchAll('product', null, 'price');
        self::assertGreaterThanOrEqual($rows[0]['price'], $rows[8]['price']);

        $rows = $this->db->fetchAll('product', null, null, 3);
        self::assertCount(3, $rows);

        $rows = $this->db->fetchAll('product', null, null, 3, 2);
        self::assertCount(3, $rows);

        $rows = $this->db->fetchAll('product', null, null, null, 3);
        self::assertCount(6, $rows);

        $rows = $this->db->fetchAll('product', null, null, 3, 22);
        self::assertCount(0, $rows);
    }

    public function testCount()
    {
        self::assertSame(9, $this->db->count('product'));
        self::assertSame(3, $this->db->count('product', 'id < 4'));
        self::assertSame(0, $this->db->count('product', 'id BETWEEN 33 AND 44'));
    }

    public function testInsert()
    {
        self::assertInstanceOf(Insert::class, $this->db->insert());
        self::assertInstanceOf(Insert::class, $this->db->insert('product'));

        $affected = $this->db->insert('product', []);
        self::assertSame(0, $affected);

        $affected = $this->db->insert(
            'product',
            [
                'name' => 'product-a',
                'tax_rate' => 10.0,
                'price' => 100.10,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => '0000-00-00 00:00:00',
            ]
        );

        self::assertSame(true, $affected);
        self::assertSame('10', $this->pdo->lastInsertId());
        self::assertSame(10, $this->db->count('product'));

        $affected = $this->db->insert(
            'product',
            [
                [
                    'name' => 'product-b',
                    'tax_rate' => 11.0,
                    'price' => 110.11,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => '0000-00-00 00:00:00',
                ],
                [
                    'name' => 'product-c',
                    'tax_rate' => 12.0,
                    'price' => 120.12,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => '0000-00-00 00:00:00',
                ]
            ]
        );

        self::assertSame(2, $affected);
        self::assertSame('12', $this->pdo->lastInsertId());
        self::assertSame(12, $this->db->count('product'));
    }

    public function testUpdate()
    {
        self::assertInstanceOf(Update::class, $this->db->update());
        self::assertInstanceOf(Update::class, $this->db->update('product'));

        $affected = $this->db->update('product', ['price' => 99.99], "id > 99");
        self::assertSame(0, $affected);

        $affected = $this->db->update('product', ['price' => 99.99999], "id = 9");
        self::assertSame(1, $affected);

        $affected = $this->db->update('product', ['price' => Sql::literal("(price*10)")], "id <= 3");
        self::assertSame(3, $affected);

        $row = $this->db->fetchOneBy('product', 'id', 9);
        self::assertEquals(99.99999, $row['price']);
    }

    public function testUpdateWithEmptyDataRaisesException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->db->update('product', []);
    }

    public function testUpdateWithEmptyWhereRaisesException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->db->update('product', ['published' => true]);
    }

    public function testDelete()
    {
        self::assertInstanceOf(Delete::class, $this->db->delete());
        self::assertInstanceOf(Delete::class, $this->db->delete('product'));

        $affected = $this->db->delete('product', "id > 99");
        self::assertSame(0, $affected);

        $affected = $this->db->delete('product', "id < 3");
        self::assertSame(2, $affected);
    }

    public function testQueryCall()
    {
        $sql = "SELECT * FROM user";

        $pdo = $this->prophesize(PDO::class);
        $db = new Db($pdo->reveal());

        $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)->shouldBeCalledOnce();
        $pdo->query($sql)->shouldBeCalled();

        $db->query($sql);
    }

    public function testGetParamTypeCall()
    {
        $db = new Db(self::DSN);

        // @codingStandardsIgnoreStart
        self::assertSame(PDO::PARAM_NULL, $this->invokeMethod($db, 'getParamType', null));
        self::assertSame(PDO::PARAM_INT,  $this->invokeMethod($db, 'getParamType', 42));
        self::assertSame(PDO::PARAM_INT,  $this->invokeMethod($db, 'getParamType', true));
        self::assertSame(PDO::PARAM_INT,  $this->invokeMethod($db, 'getParamType', false));
        self::assertSame(PDO::PARAM_STR,  $this->invokeMethod($db, 'getParamType', 'ABC'));
        self::assertSame(PDO::PARAM_STR,  $this->invokeMethod($db, 'getParamType', [1, 2, 3]));
        self::assertSame(PDO::PARAM_STR,  $this->invokeMethod($db, 'getParamType', new stdClass()));
        // @codingStandardsIgnoreEnd
    }

    public function testExecCall()
    {
        $sql = "SELECT * FROM user";

        $pdo = $this->prophesize(PDO::class);
        $db = new Db($pdo->reveal());

        $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)->shouldBeCalledOnce();
        $pdo->exec($sql)->shouldBeCalled();

        $db->exec($sql);
    }

    public function testLastInsertIdCall()
    {
        $pdo = $this->prophesize(PDO::class);

        foreach ([null, 'seq_user_id'] as $name) {
            $db = new Db(self::DSN);
            self::assertNull($db->lastInsertId($name));

            $db = new Db($pdo->reveal());
            $pdo->lastInsertId($name)->shouldBeCalled()->willReturn('42');

            self::assertSame('42', $db->lastInsertId($name));
        }
    }

    public function testTransactionMethodsWithoutPdoConnections()
    {
        $db = new Db(self::DSN);
        self::assertNull($this->getPropertyValue($db, 'pdo'));
        self::assertFalse($db->inTransaction());
        self::assertFalse($db->commit());
        self::assertFalse($db->rollBack());
        self::assertNull($this->getPropertyValue($db, 'pdo'));
    }

    public function testTransactionMethodsWithoutBeginningTransaction()
    {
        $pdo = $this->prophesize(PDO::class);
        $db = new Db($pdo->reveal());
        self::assertFalse($db->inTransaction());
        self::assertFalse($db->commit());
        self::assertFalse($db->rollBack());
    }

    public function testTransactionMethods()
    {
        $pdo = $this->prophesize(PDO::class);
        $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)->shouldBeCalledOnce();

        $db = new Db($pdo->reveal());

        $pdo->inTransaction()->shouldBeCalled()->willReturn(false);
        $pdo->beginTransaction()->shouldBeCalled()->willReturn(true);
        self::assertTrue($db->beginTransaction());
        self::assertTrue($db->inTransaction());
        self::assertSame(1, $this->getPropertyValue($db, 'transactionLevel'));

        self::assertFalse($db->beginTransaction());
        self::assertSame(2, $this->getPropertyValue($db, 'transactionLevel'));

        self::assertFalse($db->beginTransaction());
        self::assertSame(3, $this->getPropertyValue($db, 'transactionLevel'));

        $pdo->commit()->shouldNotBeCalled();
        self::assertFalse($db->commit());
        self::assertSame(2, $this->getPropertyValue($db, 'transactionLevel'));

        $pdo->commit()->shouldNotBeCalled();
        self::assertFalse($db->commit());
        self::assertSame(1, $this->getPropertyValue($db, 'transactionLevel'));

        $pdo->inTransaction()->shouldBeCalled()->willReturn(true);
        $pdo->commit()->shouldBeCalled()->willReturn(true);
        self::assertTrue($db->commit());
        self::assertSame(0, $this->getPropertyValue($db, 'transactionLevel'));

        $pdo->inTransaction()->shouldBeCalled()->willReturn(false);
        $pdo->beginTransaction()->shouldBeCalled()->willReturn(true);
        self::assertTrue($db->beginTransaction());
        self::assertFalse($db->beginTransaction());
        self::assertSame(2, $this->getPropertyValue($db, 'transactionLevel'));

        $pdo->rollBack()->shouldNotBeCalled();
        self::assertFalse($db->rollBack());
        self::assertSame(1, $this->getPropertyValue($db, 'transactionLevel'));
        self::assertTrue($this->getPropertyValue($db, 'inRollBack'));

        $pdo->rollBack()->shouldBeCalled()->willReturn(true);
        self::assertTrue($db->rollBack());
        self::assertSame(0, $this->getPropertyValue($db, 'transactionLevel'));
        self::assertFalse($this->getPropertyValue($db, 'inRollBack'));
    }

    public function testBeginTransactionRaisesExceptionIfPdoAlreadyDid()
    {
        $pdo = $this->prophesize(PDO::class);
        $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)->shouldBeCalledOnce();

        $db = new Db($pdo->reveal());
        $pdo->inTransaction()->shouldBeCalled()->willReturn(true);
        $pdo->beginTransaction()->shouldNotBeCalled();

        $this->expectException(RuntimeException::class);
        $db->beginTransaction();
    }

    public function testCommitRaisesExceptionIfInRollBackState()
    {
        $pdo = $this->prophesize(PDO::class);
        $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)->shouldBeCalledOnce();

        $db = new Db($pdo->reveal());

        $pdo->inTransaction()->shouldBeCalled()->willReturn(false);
        $pdo->beginTransaction()->shouldBeCalled()->willReturn(true);
        self::assertTrue($db->beginTransaction());
        self::assertFalse($db->beginTransaction());
        self::assertSame(2, $this->getPropertyValue($db, 'transactionLevel'));

        $pdo->rollBack()->shouldNotBeCalled();
        self::assertFalse($db->rollBack());
        self::assertSame(1, $this->getPropertyValue($db, 'transactionLevel'));
        self::assertTrue($this->getPropertyValue($db, 'inRollBack'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Cannot commit: the db connection is in rollBack state!");
        $db->commit();
    }

    public function testQuotingMethods()
    {
        $table  = 'order_product';
        $column = 'op.order_id';
        $alias  = 'my.alias';
        $value  = "this element's name";

        $dsns = [
            "ansi:dbname=ansidb",
            "mysql:dbname=mysqldb",
            "pgsql:dbname=pgsqldb",
            "sqlite::memory:",
            "sqlsrv:dbname=sqlsrvdb",
        ];

        foreach ($dsns as $dsn) {
            $db = new Db($dsn);

            $sqlDriver = $db->getDriver('sqlite' === substr($dsn, 0, 6));

            self::assertSame($db->quoteIdentifier($table), $sqlDriver->quoteIdentifier($table));
            self::assertSame($db->quoteIdentifier($column), $sqlDriver->quoteIdentifier($column));
            self::assertSame($db->quoteAlias($alias), $sqlDriver->quoteAlias($alias));

            if ($sqlDriver instanceof Sqlite) {
                $pdo = $db->getPDO(true);
                self::assertSame($pdo->quote($value), $db->quoteValue($value));
                self::assertSame($pdo->quote($value), $sqlDriver->quoteValue($value));
            }
        }
    }
}
