<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace pine3ree\DbTest\Sql\Clause;

use pine3ree\Db\Exception\InvalidArgumentException;
use pine3ree\Db\Sql\Clause\ConditionalClause;
use pine3ree\Db\Sql\Clause\ConditionalClauseAwareTrait;
use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\Params;
use pine3ree\DbTest\DiscloseTrait;
use PHPUnit\Framework\TestCase;
use pine3ree\Db\Exception\RuntimeException;
use stdClass;

class ConditionalClauseAwareTraitTest extends TestCase
{
    use DiscloseTrait;

    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    private function createClauseInstance()
    {
        return $this->getMockForAbstractClass(
            ConditionalClause::class,
            [],
            'MyClauseFQCN',
            true,
            true,
            true,
            ['__get']
        );
    }

    public function testUsingInvalidConditionalClauseClassRisesException()
    {
        $condClauseAware = $this->getMockForTrait(ConditionalClauseAwareTrait::class);

        $this->expectException(InvalidArgumentException::class);
        $this->invokeMethod($condClauseAware, 'setConditionalClause', 'myClause', stdClass::class, []);
    }

    public function testSetConditionalClause()
    {
        $condClauseAware = $this->getMockForTrait(ConditionalClauseAwareTrait::class);
        $myClause = $this->createClauseInstance();

        $this->invokeMethod($condClauseAware, 'setConditionalClause', 'myClause', 'MyClauseFQCN', $myClause);

        $condClauseAware->myClause->literal("TRUE IS TRUE");

        self::assertSame(
            "MY CLAUSE FQCN TRUE IS TRUE",
            $this->invokeMethodArgs($condClauseAware, 'getConditionalClauseSQL', [
                'myClause',
                Driver::ansi(),
                new Params(),
            ])
        );
    }

    public function testSetConditionalClauseWithArray()
    {
        $condClauseAware = $this->getMockForTrait(ConditionalClauseAwareTrait::class);

        $this->invokeMethod($condClauseAware, 'setConditionalClause', 'myClause', 'MyClauseFQCN', [
            '||' => [
                "TRUE IS TRUE",
                "FALSE IS FALSE",
            ]
        ]);

        self::assertSame(
            "MY CLAUSE FQCN TRUE IS TRUE OR FALSE IS FALSE",
            $this->invokeMethodArgs($condClauseAware, 'getConditionalClauseSQL', [
                'myClause',
                Driver::ansi(),
                new Params(),
            ])
        );
    }

    public function testInvalidClausePropertyRaisesException()
    {
        $condClauseAware = $this->getMockForTrait(ConditionalClauseAwareTrait::class);
        $myClause = $this->createClauseInstance();

        $this->invokeMethod($condClauseAware, 'setConditionalClause', 'myClause', 'MyClauseFQCN', $myClause);

        $condClauseAware->myClause = new stdClass();

        $this->expectException(RuntimeException::class);
        $this->invokeMethodArgs($condClauseAware, 'getConditionalClauseSQL', [
            'myClause',
            Driver::ansi(),
            new Params(),
        ]);
    }
}
