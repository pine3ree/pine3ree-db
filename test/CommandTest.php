<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest;

//use PDO;
use P3\Db\Command;
use P3\Db\Db;
use P3\Db\Exception\RuntimeException;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Statement;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

class CommandTest extends TestCase
{
    /** @var Command */
    private $command;

    /** @var Db */
    private $db;

    /** @var Driver\Mysql */
    private $driver;

    /** @var Statement\Select */
    private $sqlStatement;

    /** @var PDO */
    private $pdo;

    public function setUp(): void
    {
        $this->pdo = $this->prophesize(PDO::class);
        $this->db = $this->prophesize(Db::class);

        $this->sqlStatement = $this->getMockForAbstractClass(
            Statement::class,
            []
        );
        $this->sqlStatement
            ->method('getSQL')
            ->will($this->returnValue(
                "SELECT * FROM user WHERE id = :eq1"
            ));

        $this->driver = $this->prophesize(Driver\MySql::class);

        $this->db
            ->getDriver(true)
            ->willReturn(new Driver\MySql($this->pdo->reveal()));

        $this->db
            ->prepare($this->sqlStatement, true)
            ->willReturn($this->prophesize(PDOStatement::class)->reveal());

        $this->command = $this->getMockForAbstractClass(
            Command::class,
            [$this->db->reveal(), $this->sqlStatement]
        );

        $pdoStatement = $this->prophesize(PDOStatement::class);

        $this->command
            ->method('execute')
            ->will($this->returnValue($pdoStatement->reveal()));
    }

    public function tearDown()
    {
        $this->command = null;
        $this->sqlStatement = null;
        $this->db = null;
        $this->pdo = null;
        $this->driver = null;
    }

    public function testGetSqlStatement()
    {
        $command = clone $this->command;
        $sqlStatement = $command->sqlStatement;
        self::assertSame($sqlStatement, $command->getSqlStatement());
    }

    public function testGetSqlIsForwardedToSqlStatement()
    {
        $command = clone $this->command;
        $sqlStatement = $command->sqlStatement;
        self::assertSame($sqlStatement->getSQL(), $command->getSQL());
    }

    public function testGetParamsIsForwardedToSqlStatement()
    {
        $command = clone $this->command;
        $sqlStatement = $command->sqlStatement;
        self::assertSame($sqlStatement->getParams(), $command->getParams());
    }

    public function testGetParamsTypesIsForwardedToSqlStatement()
    {
        $command = clone $this->command;
        $sqlStatement = $command->sqlStatement;
        self::assertSame($sqlStatement->getParamsTypes(), $command->getParamsTypes());
    }

    public function testGetParamsTypesWithTrueArgIsForwardedToSqlStatement()
    {
        $command = clone $this->command;
        $sqlStatement = $command->sqlStatement;
        self::assertSame($sqlStatement->getParamsTypes(true), $command->getParamsTypes(true));
    }

    public function testExecute()
    {
        $command = clone $this->command;
        self::assertInstanceOf(PDOStatement::class, $command->execute());
    }

    public function testMagicGetter()
    {
        self::assertTrue(
            $this->command->sqlStatement === $this->command->__get('sqlStatement')
        );

        $this->expectException(RuntimeException::class);
        $this->command->nonexistentProp;
    }

    public function testClone()
    {
        $command = clone $this->command;
        self::assertFalse($command->sqlStatement === $this->command->sqlStatement);
    }
}
