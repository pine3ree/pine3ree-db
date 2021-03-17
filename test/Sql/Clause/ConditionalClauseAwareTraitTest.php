<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql\Clause;

use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Sql\Clause\ConditionalClause;
use P3\Db\Sql\Clause\ConditionalClauseAwareTrait;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Params;
use P3\DbTest\DiscloseTrait;
use PHPUnit\Framework\TestCase;
use P3\Db\Exception\RuntimeException;
use stdClass;

class ConditionalClauseAwareTraitTest extends TestCase
{
    use DiscloseTrait;

    public function setUp(): void
    {
    }

    public function tearDown()
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
