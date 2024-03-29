<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\DbTest\Command\Traits;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use pine3ree\Db\Command\Traits\Reader as ReaderTrait;
use pine3ree\DbTest\DiscloseTrait;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

// @codingStandardsIgnoreStart
if (trait_exists(ProphecyTrait::class)) {
    abstract class ReaderTestBase extends TestCase
    {
       use ProphecyTrait;
    }
} else {
    abstract class ReaderTestBase extends TestCase
    {
    }
}
// @codingStandardsIgnoreEnd

// @codingStandardsIgnoreLine
class ReaderTest extends \pine3ree\DbTest\Command\Traits\ReaderTestBase
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
        $this->pdoStatement->closeCursor()->willReturn(true);
    }

    public function tearDown(): void
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
