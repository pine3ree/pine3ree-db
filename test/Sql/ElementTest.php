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
use P3\Db\Sql\Element;
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

            protected static $index = 0;

            protected const MAX_INDEX = 99;

            public function __construct(array $values = [])
            {
                foreach ($values as $value) {
                    $this->addValue($value);
                }
            }

            public static function getMaxIndex(): int
            {
                return static::MAX_INDEX;
            }

            public function addValue($value, int $type = null)
            {
                $idx = "v:" . count($this->values);
                $this->values[$idx] = $value;
                if (isset($type)) {
                    $this->values_types[$idx] = $type;
                }
            }

            public function getSQL(Driver $driver = null): string
            {
                if (isset($this->sql)) {
                    return $this->sql;
                }

                $driver = $driver ?? Driver::ansi();

                $sqls = [];

                $sqls[] = "ELEMENT";

                if (!empty($this->values)) {
                    $sqls[] = "[";
                    $values_sqls = [];
                    foreach ($this->values as $idx => $value) {
                        $type = $this->values_types[$idx] ?? null;
                        $values_sqls[] = $this->getValueSQL($value, $type, 'param');
                    }
                    $sqls[] = implode(", ", $values_sqls);
                    $sqls[] = "]";
                }

                return $this->sql = implode("", $sqls);
            }
        };
    }

    /**
     * @dataProvider provideUnsupportedOperators
     */
    public function testImportParametersBeforeSqlRaisesException()
    {
        $element1 = $this->createInstance();
        $element2 = $this->createInstance([1, 2]);

        $this->expectException(RuntimeException::class);
        $this->invokeMethod($element1, 'importParams', $element2);
    }

    /**
     * @dataProvider provideUnsupportedIdentifiers
     */
    public function testQuotingUnsupportedIdentifierTypeRaisesException($identifier)
    {
        $element = $this->createInstance();

        $this->expectException(InvalidArgumentException::class);
        $this->invokeMethod($element, 'quoteGenericIdentifier', $identifier, Driver::ansi());
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

    public function provideUnsupportedOperators(): array
    {
        return [
            ['+'],
            ['?'],
            ['1'],
            ['N-O-T'],
            ['I-s'],
            ['*'],
        ];
    }

    public function testGetNextIndex()
    {
        $element = $this->createInstance();
        $maxIndex = $element->getMaxIndex();
        for ($i = 0; $i < $maxIndex; $i += 1) {
            self::assertSame($i + 1, $this->invokeMethod($element, 'getNextIndex'));
        }

        // test that the index is reset after reaching its max limit
        self::assertSame(1, $this->invokeMethod($element, 'getNextIndex'));
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

    public function testParent()
    {
        $element = $this->createInstance();
        $parent = $this->createInstance();

        $element->setParent($parent);
        self::assertTrue($element->hasParent());

        $clone = clone $element;
        self::assertFalse($clone->hasParent());
    }

    public function testGetSql()
    {
        $element = $this->createInstance();

        self::assertSame('ELEMENT', $element->getSQL());
        self::assertSame([], $element->getParams());
        self::assertSame([], $element->getParamsTypes());

        $values = [null, 1, true, 1.23, 'A'];
        $element = $this->createInstance($values);

        $element->addValue('B', PDO::PARAM_LOB);
        $element->addValue('THHGTTG', 42);

        $values[] = 'B';
        $values[] = 'THHGTTG';

        self::assertStringMatchesFormat(
            'ELEMENT[:param%x, :param%x, :param%x, :param%x, :param%x, :param%x, :param%x]',
            $sql = $element->getSQL()
        );

        // cached sql
        self::assertSame($sql, $element->getSQL());
        self::assertSame(
            $values,
            array_values($element->getParams())
        );

        foreach ($element->getParams() as $key => $param_value) {
            self::assertStringMatchesFormat(':param%x', $key);
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
            array_values($element->getParamsTypes())
        );

        self::assertSame(
            [
                'PDO::PARAM_NULL',
                'PDO::PARAM_INT',
                'PDO::PARAM_INT',
                'PDO::PARAM_STR',
                'PDO::PARAM_STR',
                'PDO::PARAM_LOB',
                'UNKNOWN',
            ],
            array_values($element->getParamsTypes(true))
        );

        foreach ($element->getParamsTypes() as $key => $param_type) {
            self::assertStringMatchesFormat(':param%x', $key);
        }
    }
}
