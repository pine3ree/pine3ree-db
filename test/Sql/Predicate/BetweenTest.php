<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Predicate;

use ArrayObject;
use InvalidArgumentException;
use P3\Db\Sql\Predicate;
use PHPUnit\Framework\TestCase;
use stdClass;

class BetweenTest extends TestCase
{
    public function setUp(): void
    {
    }

    public function tearDown()
    {
    }

    /**
     * @dataProvider provideInvalidIdentifiers
     */
    public function testContructorWithInvalidIdentifierRaisesException($identifier)
    {
        $this->expectException(InvalidArgumentException::class);
        $predicate = new Predicate\Between($identifier, 24, 42);
    }

    public function provideInvalidIdentifiers(): array
    {
        return [
            [new stdClass()],
            [new ArrayObject()],
            [''],
            [null],
            [1.23],
        ];
    }

    /**
     * @dataProvider provideInvalidLimit
     */
    public function testContructorWithInvalidLowerLimitRaisesException($min)
    {
        $this->expectException(InvalidArgumentException::class);
        $predicate = new Predicate\Between('id', $min, 42);
    }

    /**
     * @dataProvider provideInvalidLimit
     */
    public function testContructorWithInvalidUpperLimitRaisesException($max)
    {
        $this->expectException(InvalidArgumentException::class);
        $predicate = new Predicate\Between('id', 24, $max);
    }

    public function provideInvalidLimit(): array
    {
        return [
            [null],
            [new stdClass()],
            [new ArrayObject()],
        ];
    }

    public function testGetSql()
    {
        $predicate = new Predicate\Between('id', 24, 42);

        self::assertStringMatchesFormat('"id" BETWEEN :min%d AND :max%d', $sql = $predicate->getSQL());
        // test cached sql
        self::assertSame($sql, $predicate->getSQL());
    }

    public function testNotBetweenSql()
    {
        $predicate = new Predicate\NotBetween('id', 24, 42);

        self::assertStringMatchesFormat('"id" NOT BETWEEN :min%d AND :max%d', $predicate->getSQL());
    }
}
