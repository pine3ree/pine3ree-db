<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace pine3ree\DbTest\Sql\Clause;

use pine3ree\Db\Sql\Clause\Where;
use pine3ree\Db\Sql\Clause\WhereAwareTrait;
use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\Params;
use pine3ree\DbTest\DiscloseTrait;
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
