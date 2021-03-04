<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest;

//use PDO;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;
use P3\Db\Command;
use P3\Db\Db;
use P3\Db\Sql\Driver;
use P3\Db\Sql;
use P3\Db\Sql\Expression;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Statement;

class DbTest extends TestCase
{
    /** @var Command */
    private $command;

    /** @var Driver\Mysql */
    private $driver;

    /** @var Statement\Select */
    private $sqlStatement;

    /** @var PDO */
    private $pdo;

    /** @var string */
    private const DSN = 'sqlite::memory:';

    public function setUp(): void
    {
    }

    public function tearDown()
    {
    }
//
//
//    private function createDbConnectionFromDSN(
//        string $dsn,
//        string $username = null,
//        string $password = null,
//        array $options = null,
//        string $pdoClass = null
//    ) : Db {
//        return new Db($dsn, $username, $password, $options, $pdoClass);
//    }

    public function testInvalidPdoClassRisesException()
    {
        $this->expectException(InvalidArgumentException::class);
        $db = new Db(
            'mysql:host=localhost;dbname=testdb',
            'testuser',
            'secret',
            [],
            \stdClass::class
        );
    }

    public function testInvalidFirstCtorArgumentException()
    {
        $this->expectException(InvalidArgumentException::class);
        $db = new Db(new \stdClass());
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
        $connect = new \ReflectionMethod(Db::class, 'connect');
        $connect->setAccessible(true);

        $db = new Db(self::DSN);
        $connect->invoke($db);

        self::assertTrue($db->isConnected());

        $db = new Db(self::DSN);
        $connect->invokeArgs($db, [true]);
        self::assertTrue($db->isConnected());
    }

    public function testDisconnect()
    {
        $disconnect = new \ReflectionMethod(Db::class, 'disconnect');
        $disconnect->setAccessible(true);

        $pdo = $this->prophesize(PDO::class);

        $db = new Db($pdo->reveal());
        self::assertTrue($db->isConnected());
        $disconnect->invoke($db);
        self::assertFalse($db->isConnected());
    }

    public function testReconnect()
    {
        $reconnect = new \ReflectionMethod(Db::class, 'reconnect');
        $reconnect->setAccessible(true);

        $db = new Db(self::DSN);
        $pdo = $db->getPDO(true);

        self::assertTrue($db->isConnected());
        $reconnect->invoke($db);
        self::assertTrue($db->isConnected());

        self::assertInstanceOf(PDO::class, $db->getPDO(false));
        self::assertNotSame($pdo, $db->getPDO(true));
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

    public function testthatCreatePdoInstanceWithEmptyDsnRisesException()
    {
        $pdo = $this->prophesize(PDO::class);
        $db = new Db($pdo->reveal());

        $createPdo = new \ReflectionMethod(Db::class, 'createPDO');
        $createPdo->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $createPdo->invoke($db);
    }

//    public function testGetSqlIsForwardedToSqlStatement()
//    {
//        $command = clone $this->command;
//        $sqlStatement = $command->sqlStatement;
//        self::assertSame($sqlStatement->getSQL(), $command->getSQL());
//    }
//
//    public function testGetParamsIsForwardedToSqlStatement()
//    {
//        $command = clone $this->command;
//        $sqlStatement = $command->sqlStatement;
//        self::assertSame($sqlStatement->getParams(), $command->getParams());
//    }
//
//    public function testGetParamsTypesIsForwardedToSqlStatement()
//    {
//        $command = clone $this->command;
//        $sqlStatement = $command->sqlStatement;
//        self::assertSame($sqlStatement->getParamsTypes(), $command->getParamsTypes());
//    }
//
//    public function testGetParamsTypesWithTrueArgIsForwardedToSqlStatement()
//    {
//        $command = clone $this->command;
//        $sqlStatement = $command->sqlStatement;
//        self::assertSame($sqlStatement->getParamsTypes(true), $command->getParamsTypes(true));
//    }
//
//    public function testExecute()
//    {
//        $command = clone $this->command;
//        self::assertInstanceOf(PDOStatement::class, $command->execute());
//    }
//
//    public function testMagicGetter()
//    {
//        self::assertTrue(
//            $this->command->sqlStatement === $this->command->__get('sqlStatement')
//        );
//
//        $this->command->nonexistentProp;
//    }
//
//    public function testClone()
//    {
//        $command = clone $this->command;
//        self::assertFalse($command->sqlStatement === $this->command->sqlStatement);
//    }
}
