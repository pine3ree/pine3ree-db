<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace pine3ree\DbTest\Command;

use pine3ree\Db\Command\Update;
use pine3ree\Db\Db;
use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\Statement;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use pine3ree\Db\Exception\RuntimeException;

// @codingStandardsIgnoreStart
if (trait_exists(ProphecyTrait::class)) {
    class UpdateTestBase extends TestCase
    {
       use ProphecyTrait;
    }
} else {
    class UpdateTestBase extends TestCase
    {
    }
}
// @codingStandardsIgnoreEnd

// @codingStandardsIgnoreLine
class UpdateTest extends UpdateTestBase
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

    public function tearDown(): void
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

        $db->prepare($update->getSqlStatement(), true, null)
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
            "UPDATE `user` SET `enabled` = :set%d WHERE id = 42",
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
