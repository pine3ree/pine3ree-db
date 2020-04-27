<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Predicate\Between;

/**
 * Class NotBetween
 */
class NotBetween extends Between
{
    /**
     * @see Between::__construct()
     */
    public function __construct(string $identifier, $min_value, $max_value)
    {
        parent::__construct($identifier, $min_value, $max_value);
        $this->not = true;
    }
}
