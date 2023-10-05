<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\DbTest\Command\Traits;

use pine3ree\Db\Command\Traits\Writer as WriterTrait;
use pine3ree\DbTest\DiscloseTrait;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

// @codingStandardsIgnoreStart
if (trait_exists(ProphecyTrait::class)) {
    abstract class WriterTestBase extends TestCase
    {
       use ProphecyTrait;
    }
} else {
    abstract class WriterTestBase extends TestCase
    {
    }
}
// @codingStandardsIgnoreEnd

// @codingStandardsIgnoreLine
class WriterTest extends WriterTestBase
{
    use DiscloseTrait;

    /** @var ObjectProphecy|PDO */
    private $pdo;

    /** @var ObjectProphecy|PDOStatement */
    private $pdoStatement;

    public function setUp(): void
    {
        $this->pdoStatement = $this->prophesize(PDOStatement::class);
        $this->pdoStatement->execute()->willReturn(true);
    }

    public function tearDown(): void
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

        $this->pdoStatement->execute()->willReturn(false);
        self::assertSame(false, $writer->exec());
    }

    public function testExecuteWillCallExec()
    {
        $writer = $this->createWriterCommand();

        $this->pdoStatement->rowCount()->willReturn(42);
        self::assertSame($writer->exec(), $writer->execute());

        $this->pdoStatement->execute()->willReturn(false);
        self::assertSame($writer->exec(), $writer->execute());
    }
}
