<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use JsonSerializable;
use PDO;

use function addcslashes;
use function is_bool;
use function is_int;
use function is_null;
use function is_string;
use function str_replace;
use function strpos;
use function trim;

/**
 * This abstract class represents a generic SQL Expression and is the ancestor
 * of all the other sql-related classes.
 */
interface ExpressionInterface
{
    public function getSQL(): string;

    public function getParams(): array;

    public function getParamsTypes(): array;
}
