<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql;

use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Driver;
use P3\Db\Sql\DriverInterface;
use P3\Db\Sql\Element;
use P3\Db\Sql\Statement;
use P3\Db\Sql\Params;
use P3\DbTest\DiscloseTrait;
use PDO;
use PHPUnit\Framework\TestCase;
use P3\Db\Exception\RuntimeException;
use stdClass;

class StatementTest extends TestCase
{
    use DiscloseTrait;

    public function setUp(): void
    {
    }

    public function tearDown()
    {
    }

    private function createInstance(): Statement
    {
        return new class () extends Statement {

            public function setPartialSQL(string $part, string $sql)
            {
                $this->sqls[$part] = $sql;
            }

            public function getSQL(DriverInterface $driver = null, Params $params = null): string
            {
                if (isset($this->sql)) {
                    return $this->sql;
                }

                $driver = $driver ?? Driver::ansi();
                $params = $params ?? ($this->params = new Params());

                return $this->sql = implode("", $this->sqls);
            }
        };
    }

    public function testAssertValidValueReturnsNullIfValid()
    {
        self::assertNull($this->invokeMethod(Statement::class, 'assertValidValue', 123, 'sql-'));
    }

    public function testAssertValidValueRaisesExceptionWithCustomizedMessage()
    {
        $messagePart = 'A sql-statement value must be either';

        $this->expectExceptionMessage($messagePart);
        $this->invokeMethod(Statement::class, 'assertValidValue', new stdClass(), 'sql-');
    }
//
//    public function testAssertValidValueRaisesExceptionWithCustomizedMessage()
//    {
//        self::assertNull($this->invokeMethod(Element::class, 'assertValidValue', null));
//        self::assertNull($this->invokeMethod(Element::class, 'assertValidValue', true));
//        self::assertNull($this->invokeMethod(Element::class, 'assertValidValue', false));
//        self::assertNull($this->invokeMethod(Element::class, 'assertValidValue', 123));
//        self::assertNull($this->invokeMethod(Element::class, 'assertValidValue', 1.23));
//        self::assertNull($this->invokeMethod(Element::class, 'assertValidValue', 'ABC'));
//        self::assertNull($this->invokeMethod(Element::class, 'assertValidValue', new Sql\Literal('TRUE')));
//
//        $this->expectException(InvalidArgumentException::class);
//        $this->invokeMethod(Element::class, 'assertValidValue', new \stdClass());
//    }
//
//    /**
//     * @dataProvider provideElements
//     */
//    public function testGetShortName(Element $e, string $expected)
//    {
//        self::assertSame($expected, $this->invokeMethod($e, 'getShortName'));
//    }
//
//
//    public function testSetParentRaisesExceptionIfElementHasDifferentParent()
//    {
//        $element = $this->createInstance();
//
//        $parent1 = $this->createInstance();
//        $parent2 = $this->createInstance();
//
//        $element->setParent($parent1);
//        // should not throw
//        $element->setParent($parent1);
//
//        $this->expectException(RuntimeException::class);
//        $element->setParent($parent2);
//    }
//
//    public function testParent()
//    {
//        $element = $this->createInstance();
//        $parent = $this->createInstance();
//
//        self::assertNull($element->getParent());
//
//        $element->setParent($parent);
//        self::assertTrue($element->hasParent());
//        self::assertNotNull($element->getParent());
//        self::assertInstanceOf(Sql\ElementInterface::class, $element->getParent());
//
//        $clone = clone $element;
//        self::assertFalse($clone->hasParent());
//        self::assertNull($clone->getParent());
//    }
//
    public function testClearPartialSql()
    {
        $statement = $this->createInstance();

        $statement->setPartialSQL('A', '(A != B)');
        $statement->setPartialSQL('B', ' AND ');
        $statement->setPartialSQL('C', '(TRUE IS NOT FALSE)');

        self::assertSame("(A != B) AND (TRUE IS NOT FALSE)", $statement->getSQL());

        self::assertArrayHasKey('A', $this->getPropertyValue($statement, 'sqls'));
        self::assertArrayHasKey('B', $this->getPropertyValue($statement, 'sqls'));
        self::assertArrayHasKey('C', $this->getPropertyValue($statement, 'sqls'));

        $this->invokeMethod($statement, 'clearPartialSQL', 'A');
        self::assertArrayNotHasKey('A', $this->getPropertyValue($statement, 'sqls'));
        self::assertNull($this->getPropertyValue($statement, 'sql'));

        $this->invokeMethod($statement, 'clearPartialSQL');
        self::assertArrayNotHasKey('B', $this->getPropertyValue($statement, 'sqls'));
        self::assertArrayNotHasKey('C', $this->getPropertyValue($statement, 'sqls'));
    }

//    public function testParamsAndTypes()
//    {
//        $values = [null, 1, true, 1.23, 'A'];
//        $element = $this->createInstance($values);
//
//        self::assertFalse($element->hasParams());
//
//        $element->addValue('B', PDO::PARAM_LOB);
//        $element->addValue('THHGTTG', 42);
//
//        $values[] = 'B';
//        $values[] = 'THHGTTG';
//
//        // trigger parameters building
//        $sql = $element->getSQL();
//        self::assertTrue($element->hasParams());
//        $params = $element->getParams();
//
//        self::assertNotNull($params);
//        self::assertInstanceOf(Params::class, $params);
//
//        self::assertSame(
//            $values,
//            array_values($params->getValues())
//        );
//
//        foreach (array_keys($params->getValues()) as $index) {
//            self::assertStringMatchesFormat(':value%d', $index);
//        }
//
//        self::assertSame(
//            [
//                PDO::PARAM_NULL,
//                PDO::PARAM_INT,
//                PDO::PARAM_INT,
//                PDO::PARAM_STR,
//                PDO::PARAM_STR,
//                PDO::PARAM_LOB,
//                42
//            ],
//            array_values($params->getTypes())
//        );
//
//        foreach (array_keys($params->getTypes()) as $index) {
//            self::assertStringMatchesFormat(':value%d', $index);
//        }
//    }
//
//    public function testGetSqlWithoutValues()
//    {
//        $element = $this->createInstance();
//
//        self::assertSame('ELEMENT', $element->getSQL());
//        self::assertEquals(new Params(), $params = $element->getParams());
//        self::assertSame([], $params->getValues());
//        self::assertSame([], $params->getTypes());
//    }
//
//    public function testGetSqlWithValues()
//    {
//        $values = [null, 1, true, 1.23, 'A'];
//        $element = $this->createInstance($values);
//
//        $element->addValue('B', PDO::PARAM_LOB);
//        $element->addValue('THHGTTG', 42);
//
//        $values[] = 'B';
//        $values[] = 'THHGTTG';
//
//        self::assertStringMatchesFormat(
//            'ELEMENT[:value%d, :value%d, :value%d, :value%d, :value%d, :value%d, :value%d]',
//            $sql = $element->getSQL()
//        );
//    }
//
//    public function testGetValueSQL()
//    {
//        $element = $this->createInstance();
//        $params = new Params(Params::MODE_POSITIONAL);
//        $literal_str = "TRUE IS NOT FALSE";
//        $literal = new Sql\Literal($literal_str);
//        $value_sql = $this->invokeMethod($element, 'getValueSQL', $params, $literal);
//        self::assertSame($literal_str, $value_sql);
//
//        $element = $this->createInstance();
//        $params = new Params(Params::MODE_POSITIONAL);
//        $value_sql = $this->invokeMethod($element, 'getValueSQL', $params, 123);
//        self::assertSame('?', $value_sql);
//
//        $element = $this->createInstance();
//        $params = new Params(Params::MODE_NAMED);
//        $value_sql = $this->invokeMethod($element, 'getValueSQL', $params, 123);
//        self::assertSame(':param1', $value_sql);
//
//        $element = $this->createInstance();
//        $params = new Params(Params::MODE_NAMED);
//        $value_sql = $this->invokeMethod($element, 'getValueSQL', $params, 123, PDO::PARAM_INT, 'value');
//        self::assertSame(':value1', $value_sql);
//    }
//
//    public function testGetIdentifierSQL()
//    {
//        $element = $this->createInstance();
//
//        $driver = Driver::ansi();
//
//        $column = 'cart.product_id';
//        $identifier_sql = $this->invokeMethod($element, 'getIdentifierSQL', $column, $driver);
//        self::assertSame('"cart"."product_id"', $identifier_sql);
//
//        $identifier = new Sql\Identifier($column);
//        $identifier_sql = $this->invokeMethod($element, 'getIdentifierSQL', $identifier, $driver);
//        self::assertSame('"cart"."product_id"', $identifier_sql);
//
//        $alias_str = 'my.Alias';
//        $alias = new Sql\Alias($alias_str);
//        $identifier_sql = $this->invokeMethod($element, 'getIdentifierSQL', $alias, $driver);
//        self::assertSame('"my.Alias"', $identifier_sql);
//
//        $literal_str = "CONCAT(`name`, ' ' , `surname`)";
//        $literal = new Sql\Literal($literal_str);
//        $identifier_sql = $this->invokeMethod($element, 'getIdentifierSQL', $literal, $driver);
//        self::assertSame($literal_str, $identifier_sql);
//    }
//
//    public function testAssertValidValue()
//    {
//        self::assertNull($this->invokeMethod(Element::class, 'assertValidValue', null));
//        self::assertNull($this->invokeMethod(Element::class, 'assertValidValue', true));
//        self::assertNull($this->invokeMethod(Element::class, 'assertValidValue', false));
//        self::assertNull($this->invokeMethod(Element::class, 'assertValidValue', 123));
//        self::assertNull($this->invokeMethod(Element::class, 'assertValidValue', 1.23));
//        self::assertNull($this->invokeMethod(Element::class, 'assertValidValue', 'ABC'));
//        self::assertNull($this->invokeMethod(Element::class, 'assertValidValue', new Sql\Literal('TRUE')));
//
//        $this->expectException(InvalidArgumentException::class);
//        $this->invokeMethod(Element::class, 'assertValidValue', new \stdClass());
//    }
//
//    /**
//     *
//     * @dataProvider provideSqlStrings
//     */
//    public function testIsEmptySQL($sql, $expectedSql, $expectedBool)
//    {
//        self::assertSame($expectedBool, $this->invokeMethodArgs(Element::class, 'isEmptySQL', [&$sql]));
//        self::assertSame(trim($sql), $expectedSql);
//    }
//
//    public function provideSqlStrings(): array
//    {
//        return [
//            ['', '', true],
//            [' ', '', true],
//            [' TRUE ', 'TRUE', false],
//            ["\nenabled  =  1\n", 'enabled  =  1', false],
//        ];
//    }
//
//    public function testMagicGetter()
//    {
//        $element = $this->createInstance();
//
//        self::assertNull($element->parent);
//        self::assertNull($element->params);
//
//        $parent = $this->createInstance();
//        $element->setParent($parent);
//        self::assertNotNull($element->parent);
//        self::assertSame($element->getParent(), $element->parent);
//
//        $element->getSQL();
//        self::assertNotNull($element->params);
//        self::assertSame($element->getParams(), $element->params);
//    }
//
//    public function testMagicGetterRaisesExceptionWIthUnsupportedPropertyName()
//    {
//        $element = $this->createInstance();
//        $this->expectException(RuntimeException::class);
//        $element->nonExistentProperty;
//    }
}
