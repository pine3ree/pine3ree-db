<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Sql;

use P3\Db\Sql\Driver;
use P3\Db\Sql\DriverInterface;
use P3\Db\Sql\Element;
use P3\Db\Sql\Params;
use P3\DbTest\DiscloseTrait;
use PDO;
use PHPUnit\Framework\TestCase;
use stdClass;

class ParamsTest extends TestCase
{
    use DiscloseTrait;

    public function setUp(): void
    {
    }

    public function tearDown()
    {
    }

    public function testThatParamsDefaultModeIsNamed()
    {
        $params = new Params();
        self::assertEquals(Params::MODE_NAMED, $this->getPropertyValue($params, 'mode'));
    }

    public function testThatNamedParamsUsesProvidedNameTGenerateMarkers()
    {
        $params = new Params(Params::MODE_NAMED);
        $marker = $params->create(123, PDO::PARAM_INT, 'myname');
        self::assertStringStartsWith(':myname', $marker);
    }

    public function testThatNamedParamsUsesDefaultNameIfNoneProvidedToGenerateMarkers()
    {
        $params = new Params(Params::MODE_NAMED);
        $marker = $params->create(123, PDO::PARAM_INT);
        self::assertStringStartsWith(':param', $marker);
    }

    public function testThatPositionalParamsStartsAtNumberOneAndUsesQuestionMark()
    {
        $params = new Params(Params::MODE_POSITIONAL);
        $marker = $params->create(123, PDO::PARAM_INT);
        $marker = $params->create(456, PDO::PARAM_INT);
        self::assertStringStartsWith('?', $marker);

        $values = $params->getValues();

        self::assertSame(1, array_keys($values)[0]);
    }

    public function testThatCreatingParamsIncrementInternalCounter()
    {
        $params = new Params(Params::MODE_POSITIONAL);

        $params->create(123, PDO::PARAM_INT);
        $params->create(456, PDO::PARAM_INT);
        $params->create(789, PDO::PARAM_INT);

        self::assertSame(3, $this->getPropertyValue($params, 'count'));
        self::assertSame(3, count($params->getValues()));
        self::assertSame(3, count($params->getTypes()));
    }

    public function testThatIsEmptyWorksWhenEmpty()
    {
        $params = new Params(Params::MODE_POSITIONAL);
        self::assertTrue($params->isEmpty());
    }

    public function testThatIsEmptyWorksWhenNonEmpty()
    {
        $params = new Params(Params::MODE_POSITIONAL);
        $params->create(123);

        self::assertFalse($params->isEmpty());
    }

    /**
     * @dataProvider provideValues
     */
    public function testThatCreatingParamsAddCorectTypesIfNoneProvided($value, $expectedPdoType)
    {
        $params = new Params(Params::MODE_POSITIONAL);
        $params->create($value);
        $types = $params->getTypes();

        self::assertSame($expectedPdoType, $types[1]);
    }

    public function provideValues(): array
    {
        return [
            [null, PDO::PARAM_NULL],
            // @see https://bugs.php.net/bug.php?id=38386
            // @see https://bugs.php.net/bug.php?id=49255
            [false, PDO::PARAM_INT],
            [true, PDO::PARAM_INT],
            [123, PDO::PARAM_INT],
            ['ABC', PDO::PARAM_STR],
            [1.23, PDO::PARAM_STR],
            [new stdClass(), PDO::PARAM_STR],
        ];
    }

    public function testThatNamedParamsHasDifferentIndexesForDifferentNames()
    {
        $params = new Params(Params::MODE_NAMED);

        $params->create(123, PDO::PARAM_INT, 'intparam');
        $params->create(456, PDO::PARAM_INT, 'intparam');
        $params->create(789, PDO::PARAM_INT, 'intparam');

        $params->create('A', PDO::PARAM_STR, 'strparam');
        $params->create('B', PDO::PARAM_STR, 'strparam');

        $params->create(false, PDO::PARAM_INT, 'boolparam');

        self::assertArrayHasKey('intparam', $this->getPropertyValue($params, 'index'));
        self::assertArrayHasKey('strparam', $this->getPropertyValue($params, 'index'));
        self::assertArrayHasKey('boolparam', $this->getPropertyValue($params, 'index'));

        self::assertSame(3, $this->getPropertyValue($params, 'index')['intparam']);
        self::assertSame(2, $this->getPropertyValue($params, 'index')['strparam']);
        self::assertSame(1, $this->getPropertyValue($params, 'index')['boolparam']);
    }
}
