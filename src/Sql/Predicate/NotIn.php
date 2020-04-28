<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Predicate\In;

/**
 * This class represents a sql NOT IN condition
 */
class NotIn extends In
{
    /**
     * @see In::__construct()
     */
    public function __construct($identifier, array $values)
    {
        parent::__construct($identifier, $values);
        $this->not = true;
    }
}
