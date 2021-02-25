<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Predicate\Is;

/**
 * This class represents a sql IS FALSE predicate
 */
class IsFalse extends Is
{
    /**
     * @param string|Literal $identifier
     */
    public function __construct($identifier)
    {
        parent::__construct($identifier, false);
    }
}
