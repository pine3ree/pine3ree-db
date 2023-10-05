<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace pine3ree\Db\Sql\Predicate;

use pine3ree\Db\Sql\Alias;
use pine3ree\Db\Sql\Identifier;
use pine3ree\Db\Sql\Literal;
use pine3ree\Db\Sql\Predicate\Is;

/**
 * This class represents a sql IS FALSE predicate
 */
class IsFalse extends Is
{
    /**
     * @param string|Alias|Identifier|Literal $identifier
     */
    public function __construct($identifier)
    {
        parent::__construct($identifier, false);
    }
}
