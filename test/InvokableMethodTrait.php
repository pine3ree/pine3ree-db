<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest;

use ReflectionMethod;

use function get_class;

/**
 * Provide method for invoking a private/protected object's method
 */
trait InvokableMethodTrait
{
    private function invokeMethod(object $object, string $methodName, ...$args)
    {
        $method = new ReflectionMethod(get_class($obj), $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }
}
