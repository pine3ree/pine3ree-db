<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace pine3ree\DbTest\Command;

use pine3ree\Db\Command\Delete;
use pine3ree\Db\Db;
use pine3ree\Db\Exception\RuntimeException;
use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\Statement;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

// @codingStandardsIgnoreStart
if (trait_exists(ProphecyTrait::class)) {
    class DeleteTestBase extends TestCase
    {
       use ProphecyTrait;
    }
} else {
    class DeleteTestBase extends TestCase
    {
    }
}
// @codingStandardsIgnoreEnd

// @codingStandardsIgnoreLine
class DeleteTest extends DeleteTestBase
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
    }

    public function tearDown(): void
    {
        $this->pdoStatement = null;
        $this->pdo = null;
    }

    protected function createDeleteCommand(): Delete
    {
        $db = $this->prophesize(Db::class);

        $db->getDriver(true)->willReturn(new Driver\MySql($this->pdo->reveal()));
        $db->getDriver(false)->willReturn($shallowDriver = new Driver\MySql());
        $db->getDriver()->willReturn($shallowDriver);

        $delete = new Delete($db->reveal());

        $db->prepare($delete->getSqlStatement(), true)
            ->willReturn($this->pdoStatement->reveal());

        $db->prepare($delete->getSqlStatement(), true, null)
            ->willReturn($this->pdoStatement->reveal());

        return $delete;
    }

    public function testFluentInterface()
    {
        $delete = $this->createDeleteCommand();

        self::assertSame($delete, $delete->from('user'));
        self::assertSame($delete, $delete->where("id = 42"));
    }

    public function testGetSqlStatement()
    {
        $delete = $this->createDeleteCommand();
        self::assertInstanceOf(Statement\Delete::class, $delete->getSqlStatement());
    }

    public function testGetSqlRaisesExceptionWithoutFromClause()
    {
        $delete = $this->createDeleteCommand();
        $this->expectException(RuntimeException::class);
        $delete->getSql();
    }

    public function testGetSqlRaisesExceptionWithoutWhereClause()
    {
        $delete = $this->createDeleteCommand();
        $delete->from('user');

        $this->expectException(RuntimeException::class);
        $delete->getSql();
    }

    public function testGetSql()
    {
        $delete = $this->createDeleteCommand();
        $delete->from('user');

        $delete1 = (clone $delete);
        $delete1->where("id = 42");
        self::assertSame(
            "DELETE FROM `user` WHERE id = 42",
            $delete1->getSql()
        );

        $delete2 = (clone $delete);
        $delete2->where->gt('id', 999);
        self::assertSame(
            "DELETE FROM `user` WHERE `id` > :gt1",
            $delete2->getSql()
        );
    }

    public function testExecSuccessReturnsInt()
    {
        $delete = $this->createDeleteCommand();
        $delete->from('user');

        $this->pdoStatement->rowCount()->willReturn(1);
        $delete->where("id = 42");
        $result = $delete->exec();
        self::assertSame(1, $result);

        $this->pdoStatement->rowCount()->willReturn(0);
        $delete->where("id > 999");
        $result = $delete->exec();
        self::assertSame(0, $result);
    }

    public function testExecFailureReturnsFalse()
    {
        $delete = $this->createDeleteCommand();
        $delete->from('user')->where("id = 42");

        $this->pdoStatement->rowCount()->willReturn(false);
        $result = $delete->exec();
        self::assertSame(false, $result);

        $this->pdoStatement->execute()->willReturn(false);
        $result = $delete->exec();
        self::assertSame(false, $result);
    }
}
