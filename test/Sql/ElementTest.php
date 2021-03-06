<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql;

use P3\Db\Sql;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Element;
use P3\DbTest\DiscloseTrait;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
            private $values;

            protected static $index;

            public function __construct(array $values = [])
            {
                $this->values = $values;
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
                    foreach ($this->values as $value) {
                        $values_sqls[] = $this->getValueSQL($value, null, 'param');
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
        $element1->importParams($element2);
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
        $index = $this->invokeMethod($element, 'getNextIndex');

        self::assertSame($index + 1, $this->invokeMethod($element, 'getNextIndex'));
        self::assertSame($index + 2, $this->invokeMethod($element, 'getNextIndex'));
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

    public function testGetSql()
    {
        $element = $this->createInstance();

        self::assertSame('ELEMENT', $element->getSQL());
        self::assertSame([], $element->getParams());
        self::assertSame([], $element->getParamsTypes());

        $values = [null, 1, true, 1.23, 'A'];
        $element = $this->createInstance($values);

        self::assertStringMatchesFormat(
            'ELEMENT[:param%x, :param%x, :param%x, :param%x, :param%x]',
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
            ],
            array_values($element->getParamsTypes(true))
        );

        foreach ($element->getParamsTypes() as $key => $param_type) {
            self::assertStringMatchesFormat(':param%x', $key);
        }
    }
}
