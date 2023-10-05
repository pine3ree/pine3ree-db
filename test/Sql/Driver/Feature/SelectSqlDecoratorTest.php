<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace pine3ree\DbTest\Sql\Driver\Feature;

use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\Driver\Feature\SelectSqlDecorator;
use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Statement\Select;
use pine3ree\DbTest\DiscloseTrait;
use PHPUnit\Framework\TestCase;

class SelectSqlDecoratorTest extends TestCase
{
    use DiscloseTrait;

    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    private function createDriverInstance(): SelectSqlDecorator
    {
        return new class () extends Driver\Ansi implements SelectSqlDecorator
        {
            public function decorateSelectSQL(Select $select, Params $params, bool $pretty = false): string
            {
                return "[[" . $this->generateSelectSQL($select, $params, $pretty) . "]]";
            }
        };
    }

    public function testThatSelectSqlIsDecorated()
    {
        $driver = $this->createDriverInstance(10);
        $select = new Select('*', 'product');

        self::assertStringMatchesFormat(
            '[[SELECT *%wFROM "product"]]',
            $select->getSQL($driver)
        );
    }
}
