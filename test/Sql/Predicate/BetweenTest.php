<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\DbTest\Sql\Predicate;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use pine3ree\Db\Exception\InvalidArgumentException;
use pine3ree\Db\Sql\Predicate;
use stdClass;

class BetweenTest extends TestCase
{
    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
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

        self::assertStringMatchesFormat(
            '"id" BETWEEN :min%d AND :max%d',
            $sql = $predicate->getSQL()
        );
        // test cached sql
        self::assertSame($sql, $predicate->getSQL());
    }

    public function testNotBetweenSql()
    {
        $predicate = new Predicate\NotBetween('id', 24, 42);

        self::assertStringMatchesFormat(
            '"id" NOT BETWEEN :min%d AND :max%d',
            $predicate->getSQL()
        );
    }
}
