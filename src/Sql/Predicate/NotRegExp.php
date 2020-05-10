<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Predicate\RegExp;

/**
 * This class represents a sql "~*" or "!~*" condition
 */
class NotRegExp extends RegExp
{
    /**
     * @see RegExp::__construct()
     */
    public function __construct($identifier, string $value, bool $case_sensitive = false)
    {
        parent::__construct($identifier, $value, $case_sensitive);
        $this->not = true;
    }
}
