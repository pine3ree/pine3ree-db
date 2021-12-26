<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql;

use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Sql\TableAwareTrait;
use P3\DbTest\DiscloseTrait;
use PHPUnit\Framework\TestCase;
use P3\Db\Exception\RuntimeException;

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
