<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Alias;
use P3\Db\Sql\Identifier;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate\Is;

/**
 * This class represents a sql IS NULL predicate
 */
class IsNull extends Is
{
    /**
     * @param string|Alias|Identifier|Literal $identifier
     */
    public function __construct($identifier)
    {
        parent::__construct($identifier, null);
    }
}
