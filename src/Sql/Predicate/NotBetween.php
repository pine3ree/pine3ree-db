<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Predicate\Between;

/**
 * This class represents a sql NOT BETWEEN condition
 */
class NotBetween extends Between
{
    /**
     * @see Between::__construct()
     */
    public function __construct($identifier, $min_value, $max_value)
    {
        parent::__construct($identifier, $min_value, $max_value);
        $this->not = true;
    }
}
