<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Clause;

use P3\Db\Sql\Clause;

/**
 * Class Where
 */
class Where extends Clause
{
    public function getSQL(): string
    {
        $predicates_sql = parent::getSQL();

        return "WHERE {$predicates_sql}";
    }
}
