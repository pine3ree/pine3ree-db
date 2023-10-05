<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\DbTest\Sql;

use PHPUnit\Framework\TestCase;
use pine3ree\Db\Exception\RuntimeException;
use pine3ree\Db\Sql\Clause;
use pine3ree\Db\Sql\DriverInterface;
use pine3ree\Db\Sql\Params;
use pine3ree\DbTest\DiscloseTrait;

class ClauseTest extends TestCase
{
    use DiscloseTrait;

    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    private function createInstance(): Clause
    {
        return new class () extends Clause {
            public function getShortName(): string
            {
                return "unnamedClause";
            }

            public function getSQL(DriverInterface $driver = null, Params $params = null): string
            {
                return "[{$this->getName()}]";
            }
        };
    }

    private function createNamedInstance(): Clause
    {
        return new class () extends Clause {
            protected static string $name = "NAMED CLAUSE";

            public function getSQL(DriverInterface $driver = null, Params $params = null): string
            {
                return "[{$this->getName()}]";
            }
        };
    }

    public function testUnnamedClause()
    {
        $clause = $this->createInstance();
        self::assertSame('UNNAMED CLAUSE', $name = $this->invokeMethod($clause, 'getName'));
        // cached $name static var
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
        $uc->nonExistentProperty;
    }
}
