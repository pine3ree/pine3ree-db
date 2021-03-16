<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Command\Traits;

use PDOStatement;
use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Command\Traits\Reader as ReaderTrait;
use P3\DbTest\DiscloseTrait;
use PHPUnit\Framework\TestCase;
use P3\Db\Exception\RuntimeException;
use Prophecy\Prophecy\ObjectProphecy;

class ReaderTest extends TestCase
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
        $this->pdoStatement->closeCursor()->willReturn(null);
    }

    public function tearDown()
    {
    }

    protected function createReaderCommand(bool $fail = false)
    {
        $reader = $this->getMockForTrait(ReaderTrait::class, [], '', true, true, true, ['prepare']);

        if ($fail) {
            $reader->method('prepare')->will($this->returnValue(false));
        } else {
            $reader->method('prepare')->will($this->returnValue($this->pdoStatement->reveal()));
        }

        return $reader;
    }

    public function testQuerySuccessReturnsPDOStatement()
    {
        $reader = $this->createReaderCommand();

        self::assertSame($this->pdoStatement->reveal(), $reader->query());
        self::assertSame($this->pdoStatement->reveal(), $reader->execute());
    }

    public function testtQueryFailureReturnsFalse()
    {
        $reader = $this->createReaderCommand();
        $this->pdoStatement->execute()->willReturn(false);
        self::assertSame(false, $reader->query());

        $reader = $this->createReaderCommand(true);
        $this->pdoStatement->execute()->willReturn($this->returnSelf());
        self::assertSame(false, $reader->query());
    }

    public function testExecuteWillCalltQuery()
    {
        $reader = $this->createReaderCommand();

        self::assertSame($reader->query(), $reader->execute());

        $this->pdoStatement->execute()->willReturn(false);
        self::assertSame($reader->query(), $reader->execute());

        $reader = $this->createReaderCommand(true);
        $this->pdoStatement->execute()->willReturn($this->returnSelf());
        self::assertSame($reader->query(), $reader->execute());
    }
}
