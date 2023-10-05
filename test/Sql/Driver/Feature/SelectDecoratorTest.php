<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace pine3ree\DbTest\Sql\Driver\Feature;

use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\Driver\Feature\SelectDecorator;
use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Statement\Select;
use pine3ree\DbTest\DiscloseTrait;
use PHPUnit\Framework\TestCase;

class SelectDecoratorTest extends TestCase
{
    use DiscloseTrait;

    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    private function createDriverInstance(int $limit = 10): SelectDecorator
    {
        return new class ($limit) extends Driver\Ansi implements SelectDecorator
        {
            private $limit = null;

            public function __construct(int $limit)
            {
                parent::__construct();
                $this->limit = $limit;
            }

            public function decorateSelect(Select $select, Params $params): Select
            {
                return $select->limit($this->limit);
            }
        };
    }

    public function testThatSelectsGetsDecorated()
    {
        $driver = $this->createDriverInstance(10);
        $select = new Select('*', 'product');

        self::assertStringMatchesFormat(
            'SELECT *%wFROM "product"%w[LIMIT 10]',
            $select->getSQL($driver)
        );
    }
}
