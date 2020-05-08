<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Predicate\Exists;
use P3\Db\Sql\Statement\Select;

/**
 * This class represents a sql NOT EXISTS condition
 */
class NotExists extends Exists
{
    /**
     * @see Exists::__construct()
     */
    public function __construct(Select $select)
    {
        parent::__construct($select);
        $this->not = true;
    }
}
