<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Predicate;

use ArrayObject;
use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Sql\Predicate;
use PHPUnit\Framework\TestCase;
use stdClass;

class LikeTest extends TestCase
{
    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    /**
     * @dataProvider provideInvalidIdentifiers
     */
    public function testContructorWithInvalidIdentifierRaisesException($identifier)
    {
        $this->expectException(InvalidArgumentException::class);
        $predicate = new Predicate\Like($identifier, 'A%');
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
     * @dataProvider provideInvalidPatterns
     */
    public function testContructorWithInvalidPatternRaisesException($pattern)
    {
        $this->expectException(InvalidArgumentException::class);
        $predicate = new Predicate\Like('name', $pattern);
    }

    public function provideInvalidPatterns(): array
    {
        return [
            [new stdClass()],
            [new ArrayObject()],
            [null],
            [1.23],
        ];
    }


    /**
     * @dataProvider provideInvalidEscapes
     */
    public function testContructorWithInvalidEscapeRaisesException($escape)
    {
        $this->expectException(InvalidArgumentException::class);
        $predicate = new Predicate\Like('name', 'A%', $escape);
    }

    public function provideInvalidEscapes(): array
    {
        return [
            ["AB"],
            [""],
        ];
    }

    public function testSqlWithoutEscapeChar()
    {
        $predicate = new Predicate\Like('name', 'A%');

        self::assertStringMatchesFormat('"name" LIKE :like%d', $sql = $predicate->getSQL());
        // test cached sql
        self::assertSame($sql, $predicate->getSQL());
    }

    public function testSqlWithEscapeChar()
    {
        $predicate = new Predicate\Like('name', '%o', '#');

        self::assertStringMatchesFormat('"name" LIKE :like%d ESCAPE \'#\'', $predicate->getSQL());
    }

    public function testNotLikeSql()
    {
        $predicate = new Predicate\NotLike('name', 'A%');

        self::assertStringMatchesFormat('"name" NOT LIKE :like%d', $predicate->getSQL());
    }
}
