<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Statement;

use InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Clause\Where;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Statement\Update;
use P3\DbTest\DiscloseTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class UpdateTest extends TestCase
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

    public function testUpdateWithoutTableRaisesExceptionOnGetSQL()
    {
        $update = new Update();

        $this->expectException(RuntimeException::class);
        $update->getSQL($this->driver);
    }

    public function testUpdateWithoutSetRaisesExceptionOnGetSQL()
    {
        $update = new Update('product');

        $this->expectException(RuntimeException::class);
        $update->getSQL($this->driver);
    }

    public function testSetNumericColumnRaisesExceptionOnGetSQL()
    {
        $update = new Update('product');
        $this->expectException(InvalidArgumentException::class);
        $update->set('1', '123');
    }

    public function testSetRowWithNumericColumnRaisesExceptionOnGetSQL()
    {
        $update = new Update('product');
        $this->expectException(InvalidArgumentException::class);
        $update->set(['value1', 'price' => 1.23]);
    }

    public function testGetSql()
    {
        $update = new Update('product');
        $update->set('enabled', false);
        $update->where("price < 0.1");

        self::assertStringMatchesFormat(
            "UPDATE `product` SET `enabled` = %x WHERE price < 0.1",
            $sql = $update->getSQL($this->driver)
        );

        //cached sql
        self::assertSame($sql, $update->getSQL($this->driver));

        $update = new Update('product');
        $update->set('enabled', false);
        $update->where->lt('price', 0.1);

        self::assertStringMatchesFormat(
            "UPDATE `product` SET `enabled` = %x WHERE `price` < %x",
            $update->getSQL($this->driver)
        );
    }

    public function testThatCloningAlsoClonesWhereClause()
    {
        $update1 = new Update('product');
        $update1->where("TRUE IS TRUE");

        $update2 = clone $update1;

        self::assertEquals($update1->where, $update2->where);
        self::assertNotSame($update1->where, $update2->where);
    }

    public function testMagicGetter()
    {
        $update = new Update('product');
        $update->set('stock', $literal = new Sql\Literal('stock -1'));
        $update->where->gte('id', 42);

        self::assertSame('product', $update->table);
        self::assertInstanceOf(Where::class, $update->where);
        self::assertSame(['stock' => $literal], $update->set);

        $this->expectException(RuntimeException::class);
        $update->nonexistentProperty;
    }
}
