<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Driver\Feature;

use P3\Db\Sql\Driver;
use P3\Db\Sql\Driver\Feature\SelectDecorator;
use P3\Db\Sql\Statement\Select;
use P3\DbTest\DiscloseTrait;
use PHPUnit\Framework\TestCase;

class SelectDecoratorTest extends TestCase
{
    use DiscloseTrait;

    public function setUp(): void
    {
    }

    public function tearDown()
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

            public function decorateSelect(Select $select): Select
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
