<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql;

use P3\Db\Sql\Driver;
use P3\Db\Sql\Element;
use P3\DbTest\InvokableMethodTrait;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ElementTest extends TestCase
{
    use InvokableMethodTrait;

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

    public function testGetSql()
    {
        $element = $this->createInstance();

        self::assertSame('ELEMENT', $element->getSQL());
        self::assertSame([], $element->getParams());
        self::assertSame([], $element->getParamsTypes());

        $values = [null, 1, true, 1.23, 'A'];
        $element = $this->createInstance($values);

        self::assertStringMatchesFormat(
            'ELEMENT[:param%d, :param%d, :param%d, :param%d, :param%d]',
            $sql = $element->getSQL()
        );

        // cached sql
        self::assertSame($sql, $element->getSQL());
        self::assertSame(
            array_combine([':param1', ':param2', ':param3', ':param4', ':param5'], $values),
            $element->getParams()
        );

        self::assertSame(
            [
                ':param1' => PDO::PARAM_NULL,
                ':param2' => PDO::PARAM_INT,
                ':param3' => PDO::PARAM_INT,
                ':param4' => PDO::PARAM_STR,
                ':param5' => PDO::PARAM_STR,
            ],
            $element->getParamsTypes()
        );

        self::assertSame(
            [
                ':param1' => 'PDO::PARAM_NULL',
                ':param2' => 'PDO::PARAM_INT',
                ':param3' => 'PDO::PARAM_INT',
                ':param4' => 'PDO::PARAM_STR',
                ':param5' => 'PDO::PARAM_STR',
            ],
            $element->getParamsTypes(true)
        );
    }
}
