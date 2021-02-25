<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Predicate\IsUnknown;

/**
 * This class represents a sql IS NOT UNKNOWN predicate
 */
class IsNotUnknown extends IsUnknown
{
    /**
     * @see IsNull::__construct()
     */
    public function __construct($identifier)
    {
        parent::__construct($identifier);
        $this->not = true;
    }
}
