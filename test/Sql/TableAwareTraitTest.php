<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\DbTest\Sql;

use PHPUnit\Framework\TestCase;
use pine3ree\Db\Exception\InvalidArgumentException;
use pine3ree\Db\Exception\RuntimeException;
use pine3ree\Db\Sql\TableAwareTrait;
use pine3ree\DbTest\DiscloseTrait;

class TableAwareTraitTest extends TestCase
{
    use DiscloseTrait;

    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    public function testSetEmptyTableRaisesException()
    {
        $tableAware = $this->getMockForTrait(TableAwareTrait::class);

        $this->expectException(InvalidArgumentException::class);
        $this->invokeMethod($tableAware, 'setTable', '  ');
    }

    public function testSetTableTwiceRaisesException()
    {
        $tableAware = $this->getMockForTrait(TableAwareTrait::class);

        $this->invokeMethod($tableAware, 'setTable', 'cart_product');
        $this->expectException(RuntimeException::class);
        $this->invokeMethod($tableAware, 'setTable', 'store_product');
    }
}
