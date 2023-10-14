<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\DbTest\Sql\Driver\Feature;

use PHPUnit\Framework\TestCase;
use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\Driver\Feature\SelectDecorator;
use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Statement\Select;
use pine3ree\DbTest\DiscloseTrait;

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

    public function testThatSelectGetsDecorated()
    {
        $driver = $this->createDriverInstance(10);
        $select = new Select('*', 'product');

        self::assertStringMatchesFormat(
            'SELECT *%wFROM "product"%w[LIMIT 10]',
            $select->getSQL($driver)
        );
    }

    public function testThatChildSelectGetsDecorated()
    {
        $driver = $this->createDriverInstance(10);

        $from   = (new Select())->from('product');
        $select = (new Select())->from($from, 'p');

        self::assertStringMatchesFormat(
            'SELECT "p".* FROM (SELECT * FROM "product" [LIMIT 10]) "p" [LIMIT 10]',
            $select->getSQL($driver)
        );

//        self::assertNotSame($from, $select->from);
        self::assertSame($from->getParent(), $select->from->getParent());
        self::assertSame($select, $select->from->getParent());
    }
}
