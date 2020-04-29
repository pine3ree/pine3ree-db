<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use P3\Db\Sql\Expression;

/**
 * Class Statement
 */
abstract class Statement extends Expression
{
    /**
     * @var string[] Cached parts of the final sql statement
     */
    protected $sqls = [];
}
