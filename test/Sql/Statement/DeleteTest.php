<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace pine3ree\DbTest\Sql\Statement;

use pine3ree\Db\Sql\Clause\Where;
use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\Statement\Delete;
use pine3ree\DbTest\DiscloseTrait;
use PHPUnit\Framework\TestCase;
use pine3ree\Db\Exception\RuntimeException;

class DeleteTest extends TestCase
{
    use DiscloseTrait;

    /** @var Driver\MySql */
    private $driver;

    public function setUp(): void
    {
        $this->driver = new Driver\MySql();
    }

    public function tearDown(): void
    {
        $this->driver = null;
    }

    public function testDeleteWithoutTableRaisesExceptionOnGetSQL()
    {
        $delete = new Delete();

        $this->expectException(RuntimeException::class);
        $delete->getSQL($this->driver);
    }

    public function testDeleteWithoutWhereClauseRaisesExceptionOnGetSQL()
    {
        $delete = new Delete('product');

        $this->expectException(RuntimeException::class);
        $delete->getSQL($this->driver);
    }

    public function testDeleteWithEmptyWhereClauseRaisesExceptionOnGetSQL()
    {
        $delete = new Delete('product');
        $delete->where("");

        $this->expectException(RuntimeException::class);
        $delete->getSQL($this->driver);
    }

    public function testGetSql()
    {
        $delete = new Delete('product');
        $delete->where("id > 42");

        self::assertSame(
            "DELETE FROM `product` WHERE id > 42",
            $sql = $delete->getSQL($this->driver)
        );

        //cached sql
        self::assertSame($sql, $delete->getSQL($this->driver));

        $delete = new Delete('product');
        $delete->where->gt('id', 42);
        $delete->where->lt('price', 0.25);

        self::assertStringMatchesFormat(
            "DELETE FROM `product` WHERE `id` > :gt%d AND `price` < :lt%d",
            $delete->getSQL($this->driver)
        );
    }

    public function testThatCloningAlsoClonesWhereClause()
    {
        $delete1 = new Delete('product');
        $delete1->where("FALSE");

        $delete2 = clone $delete1;

        self::assertEquals($delete1->where, $delete2->where);
        self::assertNotSame($delete1->where, $delete2->where);
    }

    public function testMagicGetter()
    {
        $delete = new Delete('product');
        $delete->where->gte('id', 42);

        self::assertSame('product', $delete->table);
        self::assertSame('product', $delete->from);
        self::assertInstanceOf(Where::class, $delete->where);

        $this->expectException(RuntimeException::class);
        $delete->nonexistentProperty;
    }
}
