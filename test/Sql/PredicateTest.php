<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql;

use ArrayObject;
use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Alias;
use P3\Db\Sql\Driver;
use P3\Db\Sql\DriverInterface;
use P3\Db\Sql\Identifier;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate;
use PHPUnit\Framework\TestCase;
use stdClass;

use function array_keys;
use function array_values;

class PredicateTest extends TestCase
{
    public function setUp(): void
    {
    }

    public function tearDown()
    {
    }

    private function createInstance($identifier, string $operator, $value): Predicate
    {
        return new class ($identifier, $operator, $value) extends Predicate {

            protected $identifier;
            protected $operator;
            protected $value;

            protected static $index;

            public function __construct($identifier, string $operator, $value)
            {
                self::assertValidIdentifier($identifier);
                self::assertValidOperator($operator);
                self::assertValidValue($value);

                $this->identifier = $identifier;
                $this->operator = $operator;
                $this->value = $value;
            }

            public function getSQL(DriverInterface $driver = null): string
            {
                if (isset($this->sql)) {
                    return $this->sql;
                }

                $driver = $driver ?? Driver::ansi();

                $sqls = [];

                $sqls[] = $this->quoteGenericIdentifier($this->identifier, $driver);
                $sqls[] = $this->operator;
                $sqls[] = $this->getValueSQL($this->value, null, 'value');

                return $this->sql = implode(" ", $sqls);
            }
        };
    }

    /**
     * @dataProvider provideInvalidIdentifiers
     */
    public function testInvalidIdentifierRaisesException($identifier)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->createInstance($identifier, '=', 42);
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

    public function provideValidIdentifiers(): array
    {
        return [
            ['cart', '"cart"'],
            ['_cart_to_product', '"_cart_to_product"'],
            ['product.price', '"product"."price"'],
        ];
    }

    public function testThatStringIdentifiersAreQuotedAsIdentifiers()
    {
        $predicate = $this->createInstance('tb.id', '=', 42);
        self::assertStringMatchesFormat('"tb"."id" = :value%x', $predicate->getSQL());
    }

    public function testThatLiteralIdentifiersAreSupportedAndNotQuoted()
    {
        $literal = 'tb.column';
        $predicate = $this->createInstance(new Literal($literal), '=', 42);
        self::assertStringMatchesFormat("{$literal} = :value%x", $predicate->getSQL());
    }

    public function testThatSqlIdentifiersAreSupportedAndQuotedProperly()
    {
        $predicate = $this->createInstance(new Identifier('tb.column'), '=', 42);
        self::assertStringMatchesFormat('"tb"."column" = :value%x', $predicate->getSQL());
    }

    public function testThatAliasIdentifiersAreSupportedAndQuotedProperly()
    {
        $predicate = $this->createInstance(new Alias('my.alias'), '=', 42);
        self::assertStringMatchesFormat('"my.alias" = :value%x', $predicate->getSQL());
    }

    /**
     * @dataProvider provideUnsupportedOperators
     */
    public function testUnsupportedOperatorRaisesException($operator)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->createInstance('t.id', $operator, 42);
    }

    public function provideUnsupportedOperators(): array
    {
        return [['^'], ['"'], ['~'], [';'], ['HELLO']];
    }

    /**
     * @dataProvider provideSupportedOperators
     */
    public function testSupportedOperatorDoesNotRaiseException(string $operator)
    {
        $ex = null;

        try {
            $this->createInstance('t.id', $operator, 42);
        } catch (Exception $ex) {
            self::fail("Creating a predicate instance with operator`{$operator}`!");
        }

        self::assertNull($ex);
    }

    public function provideSupportedOperators(): array
    {
        $values = [];
        foreach (Sql::OPERATORS as $operator) {
            $values[] = [$operator];
        }

        return $values;
    }

    public function testGetSql()
    {
        $predicate = $this->createInstance('t.id', Sql::EQ, 42);

        self::assertStringMatchesFormat(
            '"t"."id" = :value%x',
            $sql = $predicate->getSQL()
        );

        // test cached sql
        self::assertSame($sql, $predicate->getSQL());

        $params = $predicate->getParams();
        $params_keys = array_keys($params);
        $params_values = array_values($params);

        self::assertSame([42], $params_values);
        self::assertStringMatchesFormat(':value%x', $params_keys[0]);
    }
}
