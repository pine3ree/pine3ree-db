<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace pine3ree\DbTest\Command;

use pine3ree\Db\Command\Insert;
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
    class InsertTestBase extends TestCase
    {
       use ProphecyTrait;
    }
} else {
    class InsertTestBase extends TestCase
    {
    }
}
// @codingStandardsIgnoreEnd

// @codingStandardsIgnoreLine
class InsertTest extends InsertTestBase
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

    protected function createInsertCommand(): Insert
    {
        $db = $this->prophesize(Db::class);

        $db->getDriver(true)->willReturn(new Driver\MySql($this->pdo->reveal()));
        $db->getDriver(false)->willReturn($shallowDriver = new Driver\MySql());
        $db->getDriver()->willReturn($shallowDriver);

        $insert = new Insert($db->reveal());

        $db->prepare($insert->getSqlStatement(), true)
            ->willReturn($this->pdoStatement->reveal());

        $db->prepare($insert->getSqlStatement(), true, null)
            ->willReturn($this->pdoStatement->reveal());

        return $insert;
    }

    public function testFluentInterface()
    {
        $insert = $this->createInsertCommand();

        $row = [
            'username' => 'username123',
            'email' => 'email123',
        ];

        self::assertSame($insert, $insert->ignore());
        self::assertSame($insert, $insert->into('user'));
        self::assertSame($insert, $insert->columns(['username', 'email']));
        self::assertSame($insert, $insert->values(array_values($row)));
        self::assertSame($insert, $insert->select(new Statement\Select('*', 'subscribers')));
        self::assertSame($insert, $insert->row($row));
        self::assertSame($insert, $insert->rows([$row, $row]));
        self::assertSame($insert, $insert->multipleValues([array_values($row), array_values($row)]));
    }

    public function testGetSqlStatement()
    {
        $insert = $this->createInsertCommand();
        self::assertInstanceOf(Statement\Insert::class, $insert->getSqlStatement());
    }

    public function testGetSqlRaisesExceptionWithoutIntoClause()
    {
        $insert = $this->createInsertCommand();
        $this->expectException(RuntimeException::class);
        $insert->getSql();
    }

    public function testGetSqlRaisesExceptionWithoutValues()
    {
        $insert = $this->createInsertCommand();
        $insert->into('user');

        $this->expectException(RuntimeException::class);
        $insert->getSql();
    }

    public function testGetSql()
    {
        $insert = $this->createInsertCommand();
        $insert->into('user')->row(['username' => 'popeye']);

        self::assertStringMatchesFormat(
            "INSERT INTO `user` (`username`) VALUES (:val%d)",
            $insert->getSql()
        );
    }

    public function testExecSuccessReturnsInt()
    {
        $insert = $this->createInsertCommand();
        $insert->into('user');

        $this->pdoStatement->rowCount()->willReturn(1);
        $result = $insert->row(['username' => 'user-abc'])->exec();
        self::assertSame(1, $result);

        $this->pdoStatement->rowCount()->willReturn(0);
        $result = $insert->row(['username' => 'INVALID'])->exec();
        self::assertSame(0, $result);
    }

    public function testExecFailureReturnsFalse()
    {
        $insert = $this->createInsertCommand();
        $insert->into('user');

        $this->pdoStatement->rowCount()->willReturn(false);
        $result = $insert->row(['username' => 'INVALID'])->exec();
        self::assertSame(false, $result);

        $this->pdoStatement->execute()->willReturn(false);
        $result = $insert->row(['username' => 'INVALID'])->exec();
        self::assertSame(false, $result);
    }
}
