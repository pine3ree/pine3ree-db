<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\DbTest\Sql;

use PHPUnit\Framework\TestCase;
use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\DriverInterface;
use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Statement;
use pine3ree\DbTest\DiscloseTrait;
use stdClass;

class StatementTest extends TestCase
{
    use DiscloseTrait;

    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    private function createInstance(): Statement
    {
        return new class () extends Statement
        {
            public function setPartialSQL(string $part, string $sql)
            {
                $this->sqls[$part] = $sql;
            }

            public function getSQL(DriverInterface $driver = null, Params $params = null): string
            {
                if (isset($this->sql)) {
                    return $this->sql;
                }

                $driver = $driver ?? Driver::ansi();
                $params = $params ?? ($this->params = new Params());

                return $this->sql = implode("", $this->sqls);
            }
        };
    }

    public function testAssertValidValueReturnsNullIfValid()
    {
        self::assertNull($this->invokeMethod(Statement::class, 'assertValidValue', 123, 'sql-'));
    }

    public function testAssertValidValueRaisesExceptionWithCustomizedMessage()
    {
        $messagePart = 'A sql-statement value must be either';

        $this->expectExceptionMessage($messagePart);
        $this->invokeMethod(Statement::class, 'assertValidValue', new stdClass(), 'sql-');
    }

    public function testClearPartialSql()
    {
        $statement = $this->createInstance();

        $statement->setPartialSQL('A', '(A != B)');
        $statement->setPartialSQL('B', ' AND ');
        $statement->setPartialSQL('C', '(TRUE IS NOT FALSE)');

        self::assertSame("(A != B) AND (TRUE IS NOT FALSE)", $statement->getSQL());

        self::assertArrayHasKey('A', $this->getPropertyValue($statement, 'sqls'));
        self::assertArrayHasKey('B', $this->getPropertyValue($statement, 'sqls'));
        self::assertArrayHasKey('C', $this->getPropertyValue($statement, 'sqls'));

        $this->invokeMethod($statement, 'clearPartialSQL', 'A');
        self::assertArrayNotHasKey('A', $this->getPropertyValue($statement, 'sqls'));
        self::assertNull($this->getPropertyValue($statement, 'sql'));

        $this->invokeMethod($statement, 'clearPartialSQL');
        self::assertArrayNotHasKey('B', $this->getPropertyValue($statement, 'sqls'));
        self::assertArrayNotHasKey('C', $this->getPropertyValue($statement, 'sqls'));
    }
}
