<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\DbTest;

use PDOStatement;
use PHPUnit\Framework\TestCase;
use pine3ree\Db\Command;
use pine3ree\Db\Db;
use pine3ree\Db\Exception\RuntimeException;
use pine3ree\Db\Sql\DriverInterface;
use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Statement;
use pine3ree\DbTest\DiscloseTrait;
use Prophecy\PhpUnit\ProphecyTrait;

// @codingStandardsIgnoreStart
if (trait_exists(ProphecyTrait::class)) {
    abstract class CommandTestBase extends TestCase
    {
       use ProphecyTrait;
    }
} else {
    abstract class CommandTestBase extends TestCase
    {
    }
}
// @codingStandardsIgnoreEnd

// @codingStandardsIgnoreLine
class CommandTest extends \pine3ree\DbTest\CommandTestBase
{
    use DiscloseTrait;

    /** @var Command */
    private $command;

    /** @var Db */
    private $db;

    /** @var DriverInterface */
    private $driver;

    /** @var Statement */
    private $sqlStatement;

    public function setUp(): void
    {
        $this->driver = $this->prophesize(DriverInterface::class);
        $this->driver = $this->driver->reveal();

        $this->sqlStatement = $this->prophesize(Statement::class);
        $this->sqlStatement
            ->getSQL($this->driver)
            ->willReturn("SELECT * FROM user WHERE id = 42");
        $this->sqlStatement
            ->getParams()
            ->willReturn($this->prophesize(Params::class)->reveal());

        $this->sqlStatement = $this->sqlStatement->reveal();

        $pdoStatement = $this->prophesize(PDOStatement::class)->reveal();

        $this->db = $this->prophesize(Db::class);
        $this->db
            ->getDriver(true)
            ->willReturn($this->driver);
        $this->db
            ->prepare($this->sqlStatement, true)
            ->willReturn($pdoStatement);
        $this->db
            ->prepare($this->sqlStatement, true, null)
            ->willReturn($pdoStatement);
        $this->db
            ->prepare($this->sqlStatement, false)
            ->willReturn($pdoStatement);
        $this->db
            ->prepare($this->sqlStatement, false, null)
            ->willReturn($pdoStatement);

        $this->db = $this->db->reveal();

        $this->command = $this->getMockForAbstractClass(
            Command::class,
            [$this->db, $this->sqlStatement]
        );
    }

    public function tearDown(): void
    {
    }

    public function testGetSqlStatementReturnsThePassedInStatement()
    {
        self::assertSame($this->sqlStatement, $this->command->getSqlStatement());
    }

    public function testGetSqlIsForwardedToSqlStatement()
    {
        $command = $this->command;
        $sqlStatement = $this->sqlStatement;

        $sqlStatement->getProphecy()->getSQL($this->driver)->shouldBeCalled();
        $command_sql = $command->getSQL();

        self::assertSame($sqlStatement->getSQL($this->driver), $command_sql);

        self::assertSame("SELECT * FROM user WHERE id = 42", $command->getSQL());
    }

    public function testGetParamsIsForwardedToSqlStatement()
    {
        $command = $this->command;
        $sqlStatement = $command->sqlStatement;
        self::assertSame($sqlStatement->getParams(), $command->getParams());
    }

    public function testPrepare()
    {
        self::assertInstanceOf(
            PDOStatement::class,
            $this->invokeMethod($this->command, 'prepare', true)
        );
        self::assertInstanceOf(
            PDOStatement::class,
            $this->invokeMethod($this->command, 'prepare', false)
        );

        self::assertSame(
            $this->db->prepare($this->sqlStatement, true),
            $this->invokeMethod($this->command, 'prepare', true)
        );
        self::assertSame(
            $this->db->prepare($this->sqlStatement, false),
            $this->invokeMethod($this->command, 'prepare', false)
        );
    }

    public function testMagicGetter()
    {
        $command = $this->command;
        self::assertSame($command->getSqlStatement(), $command->sqlStatement);

        self::assertSame($command->getSQL(), $command->sql);
        $this->invokeMethod($command, 'prepare', true);
        self::assertSame($command->getSqlStatement()->getSQL($this->driver), $command->sql);

        $this->expectException(RuntimeException::class);
        $command->nonExistentProperty;
    }

    public function testThatCloningAlsoClonesTheCOmposedSqlStatement()
    {
        $command = clone $this->command;
        self::assertEquals($command->sqlStatement, $this->command->sqlStatement);
        self::assertNotSame($command->sqlStatement, $this->command->sqlStatement);
    }
}
