<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Driver\Feature;

use P3\Db\Sql\Driver;
use P3\Db\Sql\Driver\Feature\SelectSqlDecorator;
use P3\Db\Sql\Params;
use P3\Db\Sql\Statement\Select;
use P3\DbTest\DiscloseTrait;
use PHPUnit\Framework\TestCase;

class SelectSqlDecoratorTest extends TestCase
{
    use DiscloseTrait;

    public function setUp(): void
    {
    }

    public function tearDown()
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
