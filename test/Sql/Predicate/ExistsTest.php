<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Predicate;

use P3\Db\Sql;
use P3\Db\Sql\Predicate\Exists;
use PHPUnit\Framework\TestCase;

class ExistsTest extends TestCase
{
    public function setUp(): void
    {
    }

    public function tearDown()
    {
    }

    public function testGetSql()
    {
        $select = Sql::select('*', 'product');
        $predicate = new Exists($select);

        self::assertSame('EXISTS (SELECT * FROM "product")', $sql = $predicate->getSQL());
        // test cached sql
        self::assertSame($sql, $predicate->getSQL());
    }
}
