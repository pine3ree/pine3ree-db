<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Query;

use P3\Db\Query\Traits\ParamsAwareTrait;

/**
 * Class Clause
 */
abstract class Clause
{
    use ParamsAwareTrait;
}
