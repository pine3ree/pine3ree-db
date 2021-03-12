<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Command;

use P3\Db\Command\Update;
use P3\Db\Db;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Statement;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use P3\Db\Exception\RuntimeException;

class UpdateTest extends TestCase
{
    /** @var ObjectProphecy|PDO */
    private $pdo;

    /** @var ObjectProphecy|PDOStatement */
    private $pdoStatement;

    public function setUp(): void
    {
        // setup mock-objects

        $this->pdo = $this->prophesize(PDO::class);
        $this->pdoStatement = $this->prophesize(PDOStatement::class);

        $this->pdoStatement
            ->execute()
            ->willReturn($this->returnSelf());

        $this->pdoStatement
            ->closeCursor()
            ->willReturn(null);
    }

    public function tearDown()
    {
        $this->pdoStatement = null;
        $this->pdo = null;
    }

    protected function createUpdateCommand(Db &$dbMock = null): Update
    {
        $db = $this->prophesize(Db::class);

        $db->getDriver(true)->willReturn(new Driver\MySql($this->pdo->reveal()));
        $db->getDriver(false)->willReturn($shallowDriver = new Driver\MySql());
        $db->getDriver()->willReturn($shallowDriver);

        $update = new Update($db->reveal());

        $db->prepare($update->getSqlStatement(), true)
            ->willReturn($this->pdoStatement->reveal());

        $dbMock = $db->reveal();

        return $update;
    }

    public function testGetSqlStatement()
    {
        $update = $this->createUpdateCommand($db);
        self::assertInstanceOf(Statement\Update::class, $update->getSqlStatement());
    }

    public function testGetSqlRaisesExceptionWithoutSetClause()
    {
        $update = $this->createUpdateCommand($db);
        $update->table('user', 'u');

        $this->expectException(RuntimeException::class);
        $update->getSql();
    }

    public function testGetSqlRaisesExceptionWithoutWhereClause()
    {
        $update = $this->createUpdateCommand($db);
        $update->table('user')->set(['enabled' => true]);

        $this->expectException(RuntimeException::class);
        $update->getSql();
    }

    public function testGetSqlRaisesExceptionWithEmptyWhereClause()
    {
        $update = $this->createUpdateCommand($db);
        $update->table('user')->set(['enabled' => true])->where("");

        $this->expectException(RuntimeException::class);
        $update->getSql();
    }

    public function testGetSql()
    {
        $update = $this->createUpdateCommand($db);
        $update->table('user')->set(['enabled' => true])->where("id = 42");

        self::assertStringMatchesFormat(
            "UPDATE `user` SET `enabled` = :set%x WHERE id = 42",
            $update->getSql()
        );
    }

    public function testExecSuccessReturnsInt()
    {
        $update = $this->createUpdateCommand($db);
        $update->table('user');

        $this->pdoStatement->rowCount()->willReturn(1);
        $result = $update->set(['username' => 'popeye'])->exec();
        self::assertSame(1, $result);

        $this->pdoStatement->rowCount()->willReturn(0);
        $result = $update->set(['username' => 'INVALID'])->exec();
        self::assertSame(0, $result);
    }

    public function testExecFailureReturnsFalse()
    {
        $update = $this->createUpdateCommand($db);
        $update->table('user');

        $this->pdoStatement->rowCount()->willReturn(false);
        $result = $update->set(['username' => 'INVALID'])->exec();
        self::assertSame(false, $result);

        $this->pdoStatement->execute()->willReturn(false);
        $result = $update->set(['username' => 'INVALID'])->exec();
        self::assertSame(false, $result);
    }
}
