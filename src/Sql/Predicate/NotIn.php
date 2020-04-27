<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Predicate\In;

/**
 * Class NotIn
 */
class NotIn extends In
{

    public function __construct(string $identifier, array $values)
    {
        parent::__construct($identifier, $values);
        $this->not = true;
    }
}
