<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
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
    public function __construct($identifier, array $limits)
    {
        parent::__construct($identifier, $limits);
        $this->not = true;
    }
}
