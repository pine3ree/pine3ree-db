<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\DbTest\Sql\Clause;

use PHPUnit\Framework\TestCase;
use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Clause\Combine;
use pine3ree\Db\Sql\Clause\Except;
use pine3ree\Db\Sql\Clause\Intersect;
use pine3ree\Db\Sql\Clause\Union;
use pine3ree\Db\Sql\Statement\Select;
use pine3ree\DbTest\DiscloseTrait;
use pine3ree\Db\Exception\InvalidArgumentException;

class CombineTest extends TestCase
{
    use DiscloseTrait;

    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    public function testGetSQL()
    {
        $select = new Select('*', 'product', 'p');

        $union = Combine::create(Sql::UNION, $select);
        self::assertSame(
            'UNION SELECT "p".* FROM "product" "p"',
            $union->getSQL()
        );

        $union = Combine::create(Sql::UNION, $select, true);
        self::assertSame(
            'UNION ALL SELECT "p".* FROM "product" "p"',
            $union->getSQL()
        );

        $intersect = Combine::create(Sql::INTERSECT, $select);
        self::assertSame(
            'INTERSECT SELECT "p".* FROM "product" "p"',
            $intersect->getSQL()
        );

        $except = Combine::create(Sql::EXCEPT, $select);
        self::assertSame(
            'EXCEPT SELECT "p".* FROM "product" "p"',
            $except->getSQL()
        );
    }

    public function testFactoryMethod()
    {
        $select = new Select('*', 'product', 'p');

        self::assertInstanceOf(Union::class, Combine::create(Sql::UNION, $select));
        self::assertInstanceOf(Union::class, Combine::create('union', $select));
        self::assertInstanceOf(Intersect::class, Combine::create(Sql::INTERSECT, $select));
        self::assertInstanceOf(Intersect::class, Combine::create('intersect', $select));
        self::assertInstanceOf(Except::class, Combine::create(Sql::EXCEPT, $select));
        self::assertInstanceOf(Except::class, Combine::create('except', $select));
    }

    public function testFactoryMethodRaisesExceptionWithInvalidType()
    {
        $select = new Select('*', 'product', 'p');

        $this->expectException(InvalidArgumentException::class);
        Combine::create('invalid', $select);
    }

    public function testMagicGet()
    {
        $select = new Select('*', 'product', 'p');

        $union     = Combine::create(Sql::UNION, $select, true);
        $intersect = Combine::create(Sql::INTERSECT, $select, true);
        $except    = Combine::create(Sql::EXCEPT, $select, true);

        self::assertSame(Sql::UNION, $union->name);
        self::assertSame(Sql::INTERSECT, $intersect->name);
        self::assertSame(Sql::EXCEPT, $except->name);

        self::assertSame($select, $union->select);
        self::assertNotEquals($select, $intersect->select);
        self::assertNotEquals($select, $except->select);

        self::assertTrue($union->all);
        self::assertTrue($intersect->all);
        self::assertTrue($except->all);

        self::assertSame($union->getSQL(), $union->sql);
        self::assertSame($intersect->getSQL(), $intersect->sql);
        self::assertSame($except->getSQL(), $except->sql);
    }

    public function testHasGetParams()
    {
        $select = new Select('*', 'product', 'p');

        $union = Combine::create(Sql::UNION, $select);

        $union->getSQL(); // Trigger parameter collection
        self::assertFalse($union->hasParams());

        $union->select->where->lt('price', 1000);

        $union->getSQL(); // Trigger parameter collection
        self::assertTrue($union->hasParams());

        self::assertSame($select->getParams(), $union->getParams());
    }
}
