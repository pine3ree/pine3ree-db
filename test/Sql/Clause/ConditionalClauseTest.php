<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\DbTest\Sql\Clause;

use PDO;
use PHPUnit\Framework\TestCase;
use pine3ree\Db\Exception\RuntimeException;
use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Clause\ConditionalClause;
use pine3ree\Db\Sql\Predicate;
use pine3ree\Db\Sql\Predicate\Set;
use pine3ree\Db\Sql\Statement\Select;
use pine3ree\DbTest\DiscloseTrait;

class ConditionalClauseTest extends TestCase
{
    use DiscloseTrait;

    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    private function createInstance($predicates = null, string $defaultLogicalOperator = null): ConditionalClause
    {
        return new class ($predicates, $defaultLogicalOperator) extends ConditionalClause {
            protected static string $name = "MY CONDITIONS ARE:";
        };
    }

    public function testIsEmpty()
    {
        $conditionalClause = $this->createInstance();
        self::assertTrue($conditionalClause->isEmpty());

        $conditionalClause = $this->createInstance(['id' => 42]);
        self::assertFalse($conditionalClause->isEmpty());
    }

    public function testGetParams()
    {
        $conditionalClause = $this->createInstance();
        self::assertTrue($conditionalClause->isEmpty());

        $conditionalClause = $this->createInstance($specs = [
            'id' => 42,
            'price' => 123.45,
        ]);

        // trigger parameter building
        $sql = $conditionalClause->getSQL();
        // trigger use cached value
        self::assertSame($sql, $conditionalClause->getSQL());

        self::assertTrue($conditionalClause->hasParams());
        self::assertTrue($conditionalClause->getSearchCondition()->hasParams());

        $params = $conditionalClause->getParams();

        self::assertSame($conditionalClause->getSearchCondition()->getParams(), $params);

        $params_values = $params->getValues();
        $params_types = $params->getTypes();

        self::assertSame(array_values($specs), array_values($params_values));
        self::assertSame([PDO::PARAM_INT, PDO::PARAM_STR], array_values($params_types));
    }

    public function testAddPredicateClearsCompiledSql()
    {
        $conditionalClause = $this->createInstance();
        $conditionalClause->addPredicate(new Predicate\Literal("TRUE IS TRUE"));

        self::assertSame("MY CONDITIONS ARE: TRUE IS TRUE", $conditionalClause->getSQL());
        $conditionalClause->addPredicate(new Predicate\Literal("FALSE IS FALSE"));
        self::assertNull($this->getPropertyValue($conditionalClause, 'sql'));
    }

    /**
     * @dataProvider provideChainableMethodNames
     */
    public function testPredicatesMethods(string $methodName, ...$args)
    {
        $conditionalClause = $this->createInstance();
        $searchCondition = $conditionalClause->getSearchCondition();
        self::assertSame($searchCondition, $conditionalClause->{$methodName}(...$args));
    }

    public function provideChainableMethodNames(): array
    {
        $select = new Select('id', 'user');
        $select->where->isTrue('enable');

        return [
            ['literal', "42 IS NOT NULL"],
            ['expression', "id = {id}", ['id' => 42]],
            ['expr', "id = {id}", ['id' => 42]],
            ['all', 'id', '>', $select],
            ['any', 'id', '>', $select],
            ['some', 'id', '>', $select],
            ['between', 'id', 24, 42],
            ['notBetween', 'id', 24, 42],
            ['exists', $select],
            ['notExists', $select],
            ['in', 'id', [1, 2, 3]],
            ['notIn', 'id', [1, 2, 3]],
            ['is', 'enabled', true],
            ['isNot', 'role_id', null],
            ['isNull', 'role_id'],
            ['isNotNull', 'role_id'],
            ['isTrue', 'enabled'],
            ['isFalse', 'disabled'],
            ['isUnknown', 'role'],
            ['isNotUnknown', 'role'],
            ['like', 'id', '%.example.com'],
            ['notLike', 'email', '%.example.com'],
            ['equal', 'id', 42],
            ['eq', 'id', 42],
            ['notEqual', 'id', 42],
            ['neq', 'id', 42],
            ['ne', 'id', 42],
            ['lessThan', 'id', 42],
            ['lt', 'id', 42],
            ['lessThanEqual', 'id', 42],
            ['lte', 'id', 42],
            ['greaterThanEqual', 'id', 42],
            ['gte', 'id', 42],
            ['greaterThan', 'id', 42],
            ['gt', 'id', 42],
            ['and'],
            ['or'],
        ];
    }

    public function testOpenWithoutLogicalOperator()
    {
        $conditionalClause = $this->createInstance(['id' => 42]);

        $nestedSet = $conditionalClause->beginGroup();

        self::assertInstanceOf(Set::class, $nestedSet);
        self::assertSame([], $nestedSet->getPredicates());
        self::assertSame(Sql::AND, $nestedSet->getDefaultLogicalOperator());
    }

    public function testOpenWithLogicalOperator()
    {
        $conditionalClause = $this->createInstance(['id' => 42]);

        $nestedSet = $conditionalClause->beginGroup(Sql::OR);
        self::assertSame(Sql::OR, $nestedSet->getDefaultLogicalOperator());
    }

    public function testCloseNestedSet()
    {
        $conditionalClause = $this->createInstance(['id' => 42]);
        $nestedSet = $conditionalClause->beginGroup();
        $parent = $nestedSet->endGroup();

        self::assertSame($parent, $conditionalClause->getSearchCondition());
    }

    public function testTraversable()
    {
        $conditionalClause = $this->createInstance([
            "id > 42",
            "price > 100.0",
        ]);

        $predicates = $conditionalClause->getSearchCondition()->getPredicates();
        foreach ($conditionalClause as $key => $predicate) {
            self::assertSame($predicate, $predicates[$key] ?? null);
        }
    }

    public function testMagicGetter()
    {
        $conditionalClause = $this->createInstance(['id' => 42]);

        self::assertSame(
            $this->getPropertyValue($conditionalClause, 'searchCondition'),
            $conditionalClause->searchCondition
        );

        $this->expectException(RuntimeException::class);
        $conditionalClause->nonExistentProperty;
    }
}
