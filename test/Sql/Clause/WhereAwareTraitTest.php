<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Clause;

use P3\Db\Sql\Clause\Where;
use P3\Db\Sql\Clause\WhereAwareTrait;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Params;
use P3\DbTest\DiscloseTrait;
use PHPUnit\Framework\TestCase;

class WhereAwareTraitTest extends TestCase
{
    use DiscloseTrait;

    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    public function testWhereCallCreatesInstanceIfNotExisting()
    {
        $whereAware = $this->getMockForTrait(WhereAwareTrait::class);

        $whereAware->where("TRUE IS TRUE");
        self::assertInstanceOf(Where::class, $this->getPropertyValue($whereAware, 'where'));
    }

    public function testWhereClosureCall()
    {
        $whereAware = $this->getMockForTrait(WhereAwareTrait::class);

        $whereAware->where("TRUE IS TRUE");
        $whereAware->where(function (Where $where) {
            $where->or()->literal("TRUE IS NOT FALSE");
        });

        self::assertSame(
            "WHERE TRUE IS TRUE OR TRUE IS NOT FALSE",
            $this->invokeMethod($whereAware, 'getWhereSQL', Driver::ansi(), new Params())
        );
    }
}
