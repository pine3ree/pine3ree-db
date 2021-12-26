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

    public function tearDown(): void
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
        $default_name = $this->getConstantValue(Params::class, 'DEFAULT_NAME');
        self::assertStringStartsWith(":{$default_name}", $marker);
    }

    public function testThatPositionalParamsStartsAtNumberOneAndUsesQuestionMark()
    {
        $params = new Params(Params::MODE_POSITIONAL);
        $marker = $params->create(123, PDO::PARAM_INT);
        $marker = $params->create(456, PDO::PARAM_INT);

        self::assertSame('?', $marker);

        $values = $params->getValues();
        $types  = $params->getTypes();

        self::assertArrayNotHasKey(0, $values);
        self::assertArrayNotHasKey(0, $types);

        self::assertSame(1, array_keys($values)[0]);
        self::assertSame(1, array_keys($types)[0]);
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
     * @dataProvider provideValuesAndExpectedTypes
     */
    public function testThatCreatingParamsAddCorrectTypesIfNoneProvided($value, $expectedPdoType)
    {
        $params = new Params(Params::MODE_POSITIONAL);
        $params->create($value);
        $types = $params->getTypes();

        self::assertSame($expectedPdoType, $types[1]);
    }

    public function provideValuesAndExpectedTypes(): array
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

    /**
     * @dataProvider provideValuesAndTypes
     */
    public function testThatCreatingParamsUsesProvidedTypes($value, $providedPdoType)
    {
        $params = new Params(Params::MODE_POSITIONAL);
        $params->create($value, $providedPdoType);
        $types = $params->getTypes();

        self::assertSame($providedPdoType, $types[1]);
    }

    public function provideValuesAndTypes(): array
    {
        return [
            [null, PDO::PARAM_NULL],
            // @see https://bugs.php.net/bug.php?id=38386
            // @see https://bugs.php.net/bug.php?id=49255
            [false, PDO::PARAM_INT],
            [true, PDO::PARAM_INT],
            [123, PDO::PARAM_INT],
            ['ABC', PDO::PARAM_STR],
            [random_bytes(4), PDO::PARAM_LOB],
            [1.23, PDO::PARAM_STR],
            [new stdClass(), PDO::PARAM_STR],
            ['THHGTTG', 42], // invalid but should be set anyway
        ];
    }

    /**
     * @dataProvider provideValuesAndTypesAndExpectedPdoConstantNames
     */
    public function testGetPdoTypesReturnsCorrectPdoConstantNames($value, $providedType, $expectedPdoConstantName)
    {
        $params = new Params(Params::MODE_POSITIONAL);
        $params->create($value, $providedType);
        $pdo_types = $params->getPdoTypes();

        self::assertSame($expectedPdoConstantName, $pdo_types[1]);
    }

    public function provideValuesAndTypesAndExpectedPdoConstantNames(): array
    {
        return [
            [null, null, 'PDO::PARAM_NULL'],
            // @see https://bugs.php.net/bug.php?id=38386
            // @see https://bugs.php.net/bug.php?id=49255
            [false, null, 'PDO::PARAM_INT'],
            [true, null, 'PDO::PARAM_INT'],
            [123, null, 'PDO::PARAM_INT'],
            ['ABC', null, 'PDO::PARAM_STR'],
            [1.23, null, 'PDO::PARAM_STR'],
            [random_bytes(4), PDO::PARAM_LOB, 'PDO::PARAM_LOB'],
            ['THHGTTG', 42, 'UNKNOWN'],
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
