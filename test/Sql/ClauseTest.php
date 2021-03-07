<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Clause;

use P3\Db\Sql;
use P3\Db\Sql\Clause;
use P3\DbTest\DiscloseTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ClauseTest extends TestCase
{
    use DiscloseTrait;

    /**
     * @var Clause
     */
    protected $clause;

    public function setUp(): void
    {
        $this->clause = $this->createInstance();
    }

    public function tearDown()
    {
    }

    private function createInstance(): Clause
    {
        return new class () extends Clause {
            public function getShortName(): string
            {
                return "unnamedClause";
            }

            public function getSQL(Sql\Driver $driver = null): string
            {
                return "[{$this->getName()}]";
            }
        };
    }

    private function createNamedInstance(): Clause
    {
        return new class () extends Clause {
            protected static $name = "NAMED CLAUSE";

            public function getSQL(Sql\Driver $driver = null): string
            {
                return "[{$this->getName()}]";
            }
        };
    }

    public function testUnnamedClause()
    {
        $clause = $this->createInstance();
        self::assertSame('UNNAMED CLAUSE', $name = $this->invokeMethod($clause, 'getName'));
        // cached __name prop
        self::assertSame($name, $this->invokeMethod($clause, 'getName'));
        self::assertSame("[UNNAMED CLAUSE]", $clause->getSQL());
    }

    public function testNamedClause()
    {
        $clause = $this->createNamedInstance();
        self::assertSame("[NAMED CLAUSE]", $clause->getSQL());
    }

    public function testMagicGetter()
    {
        $uc = $this->createInstance();
        $nc = $this->createNamedInstance();

        self::assertSame('UNNAMED CLAUSE', $uc->name);
        self::assertSame('NAMED CLAUSE', $nc->name);

        $this->expectException(RuntimeException::class);
        $uc->nonexistentProperty;
    }
}
