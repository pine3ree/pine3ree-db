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
use P3\Db\Sql\Params;
use P3\DbTest\DiscloseTrait;
use PDO;
use PHPUnit\Framework\TestCase;
use P3\Db\Exception\RuntimeException;
use stdClass;

class ElementTest extends TestCase
{
    use DiscloseTrait;

    public function setUp(): void
    {
    }

    public function tearDown()
    {
    }

    private function createInstance(array $values = []): Element
    {
        return new class ($values) extends Element {

            /** @var array */
            private $values = [];

            /** @var array */
            private $values_types;

            protected $count = 0;

            public function __construct(array $values = [])
            {
                foreach ($values as $value) {
                    $this->addValue($value);
                }
            }

            public function addValue($value, int $type = null)
            {
                $this->count += 1;
                $index = "v:{$this->count}";
                $this->values[$index] = $value;
                if (isset($type)) {
                    $this->values_types[$index] = $type;
                }
            }

            public function getSQL(DriverInterface $driver = null, Params $params = null): string
            {
                if (isset($this->sql)) {
                    return $this->sql;
                }

                $driver = $driver ?? Driver::ansi();
                $params = $params ?? ($this->params = new Params());

                $sqls = ["ELEMENT"];

                if (!empty($this->values)) {
                    $sqls[] = "[";
                    $values_sqls = [];
                    foreach ($this->values as $index => $value) {
                        $type = $this->values_types[$index] ?? null;
                        $values_sqls[] = $this->getValueSQL($params, $value, $type, 'value');
                    }
                    $sqls[] = implode(", ", $values_sqls);
                    $sqls[] = "]";
                }

                return $this->sql = implode("", $sqls);
            }
        };
    }

    /**
     * @dataProvider provideUnsupportedIdentifiers
     */
    public function testQuotingUnsupportedIdentifierTypeRaisesException($identifier)
    {
        $element = $this->createInstance();

        $this->expectException(InvalidArgumentException::class);
        $this->invokeMethod($element, 'getIdentifierSQL', $identifier, Driver::ansi());
    }

    public function provideUnsupportedIdentifiers(): array
    {
        return [
            [null],
            [false],
            [true],
            [1],
            [1.23],
            [new stdClass()],
        ];
    }

    /**
     * @dataProvider provideValidIdentifiers
     */
    public function testAssertValidIdentifierReturnsNullIfValid($identifier)
    {
        self::assertNull(
            $this->invokeMethodArgs(Element::class, 'assertValidIdentifier', [&$identifier, ''])
        );
    }

    public function provideValidIdentifiers(): array
    {
        return [
            ['t0.id'],
            [new Sql\Identifier('cart.product_id')],
            [new Sql\Alias('my.Alias')],
            [new Sql\Literal('unit_price * quantity')],
        ];
    }

    /**
     * @dataProvider provideInvalidIdentifiers
     */
    public function testAssertValidIdentifierRaisesExceptionIfNotSupported($identifier)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->invokeMethodArgs(Element::class, 'assertValidIdentifier', [&$identifier, '']);
    }

    /**
     * @dataProvider provideInvalidIdentifiers
     */
    public function testAssertValidIdentifierRaisesExceptionWithCustomizedMessage($identifier)
    {
        $messagePart = is_string($identifier)
            ? 'A string identifier cannot be empty'
            : 'A sql-element identifier must be either';

        $this->expectExceptionMessage($messagePart);
        $this->invokeMethodArgs(Element::class, 'assertValidIdentifier', [&$identifier, 'sql-element ']);
    }

    public function provideInvalidIdentifiers(): array
    {
        $invalid = $this->provideUnsupportedIdentifiers();
        $invalid[] = [' '];

        return $invalid;
    }

    /**
     * @dataProvider provideElements
     */
    public function testGetShortName(Element $e, string $expected)
    {
        self::assertSame($expected, $this->invokeMethod($e, 'getShortName'));
    }

    public function provideElements(): array
    {
        return [
            [new Sql\Alias('totPrice'), 'Alias'],
            [new Sql\Expression('id > {min}', ['min' => 42]), 'Expression'],
            [new Sql\Identifier('product.id'), 'Identifier'],
            [new Sql\Literal('TRUE'), 'Literal'],
            [new Sql\Statement\Select(), 'Select'],
            [new Sql\Statement\Insert(), 'Insert'],
            [new Sql\Statement\Update(), 'Update'],
            [new Sql\Statement\Delete(), 'Delete'],
            [new Sql\Clause\Having(), 'Having'],
            [new Sql\Clause\Join(Sql::JOIN_AUTO, 'cart'), 'Join'],
            [new Sql\Clause\On(), 'On'],
            [new Sql\Clause\Where(), 'Where'],
            [new Sql\Predicate\Between('id', 11, 22), 'Between'],
        ];
    }

    public function testSetParentRaisesExceptionIfElementHasDifferentParent()
    {
        $element = $this->createInstance();

        $parent1 = $this->createInstance();
        $parent2 = $this->createInstance();

        $element->setParent($parent1);
        // should not throw
        $element->setParent($parent1);

        $this->expectException(RuntimeException::class);
        $element->setParent($parent2);
    }

    public function testParent()
    {
        $element = $this->createInstance();
        $parent = $this->createInstance();

        self::assertNull($element->getParent());
        self::assertFalse($this->invokeMethod($element, 'parentIsNot', $parent));

        $element->setParent($parent);
        self::assertTrue($element->hasParent());
        self::assertNotNull($element->getParent());
        self::assertInstanceOf(Sql\ElementInterface::class, $element->getParent());
        self::assertFalse($this->invokeMethod($element, 'parentIsNot', $parent));

        self::assertTrue($this->invokeMethod($element, 'parentIsNot', $this->createInstance()));

        $clone = clone $element;
        self::assertFalse($clone->hasParent());
        self::assertNull($clone->getParent());
    }

    public function testClearParentSql()
    {
        $element = $this->createInstance();
        $parent = $this->createInstance();

        $element->setParent($parent);

        $element->getSQL();
        $parent->getSQL();

        self::assertNotNull($this->getPropertyValue($element, 'sql'));
        self::assertNotNull($this->getPropertyValue($parent, 'sql'));

        $this->invokeMethod($element, 'clearSQL');

        self::assertNull($this->getPropertyValue($element, 'sql'));
        self::assertNull($this->getPropertyValue($parent, 'sql'));
    }

    public function testParamsAndTypes()
    {
        $values = [null, 1, true, 1.23, 'A'];
        $element = $this->createInstance($values);

        self::assertFalse($element->hasParams());

        $element->addValue('B', PDO::PARAM_LOB);
        $element->addValue('THHGTTG', 42);

        $values[] = 'B';
        $values[] = 'THHGTTG';

        // trigger parameters building
        $sql = $element->getSQL();
        self::assertTrue($element->hasParams());
        $params = $element->getParams();

        self::assertNotNull($params);
        self::assertInstanceOf(Params::class, $params);

        self::assertSame(
            $values,
            array_values($params->getValues())
        );

        foreach (array_keys($params->getValues()) as $index) {
            self::assertStringMatchesFormat(':value%d', $index);
        }

        self::assertSame(
            [
                PDO::PARAM_NULL,
                PDO::PARAM_INT,
                PDO::PARAM_INT,
                PDO::PARAM_STR,
                PDO::PARAM_STR,
                PDO::PARAM_LOB,
                42
            ],
            array_values($params->getTypes())
        );

        foreach (array_keys($params->getTypes()) as $index) {
            self::assertStringMatchesFormat(':value%d', $index);
        }
    }

    public function testGetSqlWithoutValues()
    {
        $element = $this->createInstance();

        self::assertSame('ELEMENT', $element->getSQL());
        self::assertEquals(new Params(), $params = $element->getParams());
        self::assertSame([], $params->getValues());
        self::assertSame([], $params->getTypes());
    }

    public function testGetSqlWithValues()
    {
        $values = [null, 1, true, 1.23, 'A'];
        $element = $this->createInstance($values);

        $element->addValue('B', PDO::PARAM_LOB);
        $element->addValue('THHGTTG', 42);

        $values[] = 'B';
        $values[] = 'THHGTTG';

        self::assertStringMatchesFormat(
            'ELEMENT[:value%d, :value%d, :value%d, :value%d, :value%d, :value%d, :value%d]',
            $sql = $element->getSQL()
        );
    }

    public function testGetValueSQL()
    {
        $element = $this->createInstance();
        $params = new Params(Params::MODE_POSITIONAL);
        $literal_str = "TRUE IS NOT FALSE";
        $literal = new Sql\Literal($literal_str);
        $value_sql = $this->invokeMethod($element, 'getValueSQL', $params, $literal);
        self::assertSame($literal_str, $value_sql);

        $element = $this->createInstance();
        $params = new Params(Params::MODE_POSITIONAL);
        $value_sql = $this->invokeMethod($element, 'getValueSQL', $params, 123);
        self::assertSame('?', $value_sql);

        $element = $this->createInstance();
        $params = new Params(Params::MODE_NAMED);
        $value_sql = $this->invokeMethod($element, 'getValueSQL', $params, 123);
        self::assertSame(':param1', $value_sql);

        $element = $this->createInstance();
        $params = new Params(Params::MODE_NAMED);
        $value_sql = $this->invokeMethod($element, 'getValueSQL', $params, 123, PDO::PARAM_INT, 'value');
        self::assertSame(':value1', $value_sql);
    }

    public function testGetIdentifierSQL()
    {
        $element = $this->createInstance();

        $driver = Driver::ansi();

        $column = 'cart.product_id';
        $identifier_sql = $this->invokeMethod($element, 'getIdentifierSQL', $column, $driver);
        self::assertSame('"cart"."product_id"', $identifier_sql);

        $identifier = new Sql\Identifier($column);
        $identifier_sql = $this->invokeMethod($element, 'getIdentifierSQL', $identifier, $driver);
        self::assertSame('"cart"."product_id"', $identifier_sql);

        $alias_str = 'my.Alias';
        $alias = new Sql\Alias($alias_str);
        $identifier_sql = $this->invokeMethod($element, 'getIdentifierSQL', $alias, $driver);
        self::assertSame('"my.Alias"', $identifier_sql);

        $literal_str = "CONCAT(`name`, ' ' , `surname`)";
        $literal = new Sql\Literal($literal_str);
        $identifier_sql = $this->invokeMethod($element, 'getIdentifierSQL', $literal, $driver);
        self::assertSame($literal_str, $identifier_sql);
    }

    public function testAssertValidValue()
    {
        self::assertNull($this->invokeMethod(Element::class, 'assertValidValue', null));
        self::assertNull($this->invokeMethod(Element::class, 'assertValidValue', true));
        self::assertNull($this->invokeMethod(Element::class, 'assertValidValue', false));
        self::assertNull($this->invokeMethod(Element::class, 'assertValidValue', 123));
        self::assertNull($this->invokeMethod(Element::class, 'assertValidValue', 1.23));
        self::assertNull($this->invokeMethod(Element::class, 'assertValidValue', 'ABC'));
        self::assertNull($this->invokeMethod(Element::class, 'assertValidValue', new Sql\Literal('TRUE')));

        $this->expectException(InvalidArgumentException::class);
        $this->invokeMethod(Element::class, 'assertValidValue', new \stdClass());
    }

    /**
     *
     * @dataProvider provideSqlStrings
     */
    public function testIsEmptySQL($sql, $expectedSql, $expectedBool)
    {
        self::assertSame($expectedBool, $this->invokeMethodArgs(Element::class, 'isEmptySQL', [&$sql]));
        self::assertSame(trim($sql), $expectedSql);
    }

    public function provideSqlStrings(): array
    {
        return [
            ['', '', true],
            [' ', '', true],
            [' TRUE ', 'TRUE', false],
            ["\nenabled  =  1\n", 'enabled  =  1', false],
        ];
    }

    public function testMagicGetter()
    {
        $element = $this->createInstance();

        self::assertNull($element->parent);
        self::assertNull($element->params);

        $parent = $this->createInstance();
        $element->setParent($parent);
        self::assertNotNull($element->parent);
        self::assertSame($element->getParent(), $element->parent);

        $element->getSQL();
        self::assertNotNull($element->params);
        self::assertSame($element->getParams(), $element->params);
    }

    public function testMagicGetterRaisesExceptionWithUnsupportedPropertyName()
    {
        $element = $this->createInstance();
        $this->expectException(RuntimeException::class);
        $element->nonExistentProperty;
    }

    public function testMagicIsset()
    {
        $element = $this->createInstance();

        self::assertFalse(isset($element->parent));
        self::assertFalse(isset($element->params));

        $parent = $this->createInstance();
        $element->setParent($parent);
        self::assertTrue(isset($element->parent));

        $element->getSQL();
        self::assertTrue(isset($element->params));
    }
}
