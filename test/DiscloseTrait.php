<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest;

use P3\Db\Exception\InvalidArgumentException;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionProperty;

use function class_exists;
use function get_class;
use function is_object;
use function is_string;

/**
 * Provide method for invoking a private/protected object's method
 */
trait DiscloseTrait
{
    /**
     * @param object|string $objectOrClass
     * @param string $methodName
     * @param array $args
     * @return mixed
     * @throws InvalidArgumentException
     */
    protected function invokeMethod($objectOrClass, string $methodName, ...$args)
    {
        self::assertValidObjectOrClass($objectOrClass);

        $class = is_object($objectOrClass) ? get_class($objectOrClass) : $objectOrClass;

        $method = new ReflectionMethod($class, $methodName);
        $method->setAccessible(true);

        if ($method->isStatic()) {
            $object = null;
        } else {
            self::assertValidObject($object = $objectOrClass);
        }

        return $method->invokeArgs($object, $args);
    }

    /**
     * @param object|string $objectOrClass
     * @param string $propertyName
     * @return mixed
     */
    protected function getPropertyValue($objectOrClass, string $propertyName)
    {
        self::assertValidObjectOrClass($objectOrClass);

        $class = is_object($objectOrClass) ? get_class($objectOrClass) : $objectOrClass;

        $property = $this->getProperty($class, $propertyName);

        if ($property->isStatic()) {
            $object = null;
        } else {
            self::assertValidObject($object = $objectOrClass);
        }

        return $property->getValue($object);
    }

    /**
     * @param object|string $objectOrClass
     * @param string $propertyName
     * @return ReflectionProperty
     * @throws InvalidArgumentException
     */
    protected function getProperty($objectOrClass, string $propertyName): ?ReflectionProperty
    {
        self::assertValidObjectOrClass($objectOrClass);

        $class = is_object($objectOrClass) ? get_class($objectOrClass) : $objectOrClass;

        $property = new ReflectionProperty($class, $propertyName);
        $property->setAccessible(true);

        return $property;
    }

    /**
     * @param object|string $objectOrClass
     * @param string $constantName
     * @return mixed
     */
    protected function getConstantValue($objectOrClass, string $constantName)
    {
        $class = is_object($objectOrClass) ? get_class($objectOrClass) : $objectOrClass;

        $constant = new ReflectionClassConstant($class, $constantName);

        return $constant->getValue();
    }

    private static function assertValidObject($object)
    {
        if (!is_object($object)) {
            throw new InvalidArgumentException(
                'The object argument must be a php object!'
            );
        }
    }

    private static function assertValidObjectOrClass($objectOrClass)
    {
        if (!is_object($objectOrClass)
            && (!is_string($objectOrClass) || !class_exists($objectOrClass))
        ) {
            throw new InvalidArgumentException(
                'The object argument must be a php object or a valid class!'
            );
        }
    }
}
