<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Query;

use PDO;
use P3\Db\Query;
use P3\Db\Query\ConditionsAware;

/**
 * Class Delete
 */
class Delete extends ConditionsAware
{
    public function __construct($table = null)
    {
        if (!empty($table)) {
            $this->from($table);
        }
    }

    public function from($table)
    {
        parent::setTable($table);
    }

    public function getSQL(): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        if (empty($this->table) || empty($this->columns || empty($this->values))) {
            return $this->sql = '';
        }

        $where_sql = $this->getWhereSQL();
        if ($this->isEmptyStatement($where_sql)) {
            return $this->sql = '';
        }

    }
}
