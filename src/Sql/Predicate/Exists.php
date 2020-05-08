<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Driver;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\Statement\Select;

/**
 * This class represents a sql EXISTS predicate
 */
class Exists extends Predicate
{
    /** @var Select */
    protected $select;

    /** @var bool */
    protected $not = false;

    /**
     * @param Select $select
     */
    public function __construct(Select $select)
    {
        $this->select = $select;
    }

    public function getSQL(Driver $driver = null): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        $driver = $driver ?? Driver::ansi();

        $operator = ($this->not ? "NOT " : "") . "EXISTS";

        $select_sql = $this->select->getSQL($driver);
        $this->importParams($this->select);

        return $this->sql = "{$operator} ({$select_sql})";
    }
}
