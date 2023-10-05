<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\DbTest\Sql;

use ArrayObject;
use Exception;
use PHPUnit\Framework\TestCase;
use pine3ree\Db\Exception\InvalidArgumentException;
use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Alias;
use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\DriverInterface;
use pine3ree\Db\Sql\Identifier;
use pine3ree\Db\Sql\Literal;
use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Predicate;
use stdClass;

use function array_keys;
use function array_values;

class PredicateTest extends TestCase
{
    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    private function createInstance($identifier, string $operator, $value): Predicate
    {
        return new class ($identifier, $operator, $value) extends Predicate
        {
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

            public function getSQL(DriverInterface $driver = null, Params $params = null): string
            {
                if (isset($this->sql)) {
                    return $this->sql;
                }

                $driver = $driver ?? Driver::ansi();
                $params = $params ?? ($this->params = new Params());

                $sqls = [];

                $sqls[] = $this->getIdentifierSQL($this->identifier, $driver);
                $sqls[] = $this->operator;
                $sqls[] = $this->getValueSQL($params, $this->value, null, 'value');

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
        self::assertStringMatchesFormat('"tb"."id" = :value%d', $predicate->getSQL());
    }

    public function testThatLiteralIdentifiersAreSupportedAndNotQuoted()
    {
        $literal = 'tb.column';
        $predicate = $this->createInstance(new Literal($literal), '=', 42);
        self::assertStringMatchesFormat("{$literal} = :value%d", $predicate->getSQL());
    }

    public function testThatSqlIdentifiersAreSupportedAndQuotedProperly()
    {
        $predicate = $this->createInstance(new Identifier('tb.column'), '=', 42);
        self::assertStringMatchesFormat('"tb"."column" = :value%d', $predicate->getSQL());
    }

    public function testThatAliasIdentifiersAreSupportedAndQuotedProperly()
    {
        $predicate = $this->createInstance(new Alias('my.alias'), '=', 42);
        self::assertStringMatchesFormat('"my.alias" = :value%d', $predicate->getSQL());
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
            '"t"."id" = :value%d',
            $sql = $predicate->getSQL()
        );

        // test cached sql
        self::assertSame($sql, $predicate->getSQL());

        $params = $predicate->getParams();
        $values = $params->getValues();
        $params_keys = array_keys($values);
        $params_values = array_values($values);

        self::assertSame([42], $params_values);
        self::assertStringMatchesFormat(':value%d', $params_keys[0]);
    }
}
