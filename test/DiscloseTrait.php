<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace pine3ree\DbTest;

use InvalidArgumentException;
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
        return $this->invokeMethodArgs($objectOrClass, $methodName, $args);
    }

    /** Invoke a protected/private object/class method with given arguments array
     *
     * @param object|string $objectOrClass
     * @param string $methodName
     * @param array $args
     * @return mixed
     * @throws InvalidArgumentException
     */
    protected function invokeMethodArgs($objectOrClass, string $methodName, array $args = [])
    {
        self::assertValidObjectOrClass($objectOrClass);

        $method = new ReflectionMethod($objectOrClass, $methodName);
        $method->setAccessible(true);

        if ($method->isStatic()) {
            $object = null;
        } else {
            self::assertValidObject($object = $objectOrClass);
        }

        return $method->invokeArgs($object, $args);
    }

    /**
     * Return the value of an object/class's private/protected property
     *
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
     * Return an object/class's private/protected reflection property instance and make
     * it accessible
     *
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
     * Return the value of an object/class's constant
     *
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
