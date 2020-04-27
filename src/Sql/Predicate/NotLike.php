<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Predicate\Like;

/**
 * Class NotLike
 */
class NotLike extends Like
{
    /**
     * @see Like::__construct()
     */
    public function __construct(string $identifier, $value)
    {
        parent::__construct($identifier, $value);
        $this->not = true;
    }
}
