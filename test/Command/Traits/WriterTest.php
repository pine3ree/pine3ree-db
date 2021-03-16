<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Command\Traits;

use PDOStatement;
use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Command\Traits\Writer as WriterTrait;
use P3\DbTest\DiscloseTrait;
use PHPUnit\Framework\TestCase;
use P3\Db\Exception\RuntimeException;
use Prophecy\Prophecy\ObjectProphecy;

class WriterTest extends TestCase
{
    use DiscloseTrait;
    /** @var ObjectProphecy|PDO */
    private $pdo;

    /** @var ObjectProphecy|PDOStatement */
    private $pdoStatement;

    public function setUp(): void
    {
        $this->pdoStatement = $this->prophesize(PDOStatement::class);
        $this->pdoStatement->execute()->willReturn($this->returnSelf());
    }

    public function tearDown()
    {
    }

    protected function createWriterCommand()
    {
        $writer = $this->getMockForTrait(WriterTrait::class, [], '', true, true, true, ['prepare']);
        $writer->method('prepare')->will($this->returnValue($this->pdoStatement->reveal()));

        return $writer;
    }

    public function testExecSuccessReturnsInt()
    {
        $writer = $this->createWriterCommand();

        $this->pdoStatement->rowCount()->willReturn(1);
        self::assertSame(1, $writer->exec());
        self::assertSame(1, $writer->execute());

        $this->pdoStatement->rowCount()->willReturn(0);
        self::assertSame(0, $writer->exec());
        self::assertSame(0, $writer->execute());
    }

    public function testExecFailureReturnsFalse()
    {
        $writer = $this->createWriterCommand();

        $this->pdoStatement->rowCount()->willReturn(false);
        self::assertSame(false, $writer->exec());

        $this->pdoStatement->execute()->willReturn(false);
        self::assertSame(false, $writer->exec());
    }

    public function testExecuteWillCallExec()
    {
        $writer = $this->createWriterCommand();

        $this->pdoStatement->rowCount()->willReturn(false);
        self::assertSame($writer->exec(), $writer->execute());

        $this->pdoStatement->rowCount()->willReturn(42);
        self::assertSame($writer->exec(), $writer->execute());

        $this->pdoStatement->execute()->willReturn(false);
        self::assertSame($writer->exec(), $writer->execute());
    }
}
