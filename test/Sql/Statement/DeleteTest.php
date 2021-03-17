<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Statement;

use P3\Db\Sql\Clause\Where;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Statement\Delete;
use P3\DbTest\DiscloseTrait;
use PHPUnit\Framework\TestCase;
use P3\Db\Exception\RuntimeException;

class DeleteTest extends TestCase
{
    use DiscloseTrait;

    /** @var Driver\MySql */
    private $driver;

    public function setUp(): void
    {
        $this->driver = new Driver\MySql();
    }

    public function tearDown()
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
