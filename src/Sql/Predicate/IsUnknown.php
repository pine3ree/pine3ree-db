<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql;
use P3\Db\Sql\Predicate\Is;

/**
 * This class represents a sql IS UNKNOWN predicate
 */
class IsUnknown extends Is
{
    /**
     * @param string|Literal $identifier
     */
    public function __construct($identifier)
    {
        parent::__construct($identifier, Sql::UNKNOWN);
    }
}
