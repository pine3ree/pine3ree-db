<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest;

use InvalidArgumentException;
use ReflectionMethod;

use function get_class;
use function is_object;

/**
 * Provide method for invoking a private/protected object's method
 */
trait DiscloseTrait
{
    private function invokeMethod($object, string $methodName, ...$args)
    {
        if (!is_object($object)) {
            throw new InvalidArgumentException(
                'The object argument must be a php object!'
            );
        }
        $method = new ReflectionMethod(get_class($object), $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }

    private function getProperty($object, string $propertyName)
    {
        if (!is_object($object)) {
            throw new InvalidArgumentException(
                'The object argument must be a php object!'
            );
        }
        $propertyName = new \ReflectionProperty(get_class($object), $propertyName);
        $propertyName->setAccessible(true);

        return $propertyName->getValue($object);
    }
}
