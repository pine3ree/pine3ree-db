<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql;
use P3\Db\Sql\Driver;
use P3\Db\Sql\DriverInterface;
use P3\Db\Sql\Params;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\Statement\Select;

/**
 * This class represents a sql EXISTS predicate
 *
 * @property-read Select $select The sql select statement this EXISTS predicate refers to
 */
class Exists extends Predicate
{
    /** @var Select */
    protected $select;

    /** @var bool */
    protected static $not = false;

    /**
     * @param Select $select
     */
    public function __construct(Select $select)
    {
        $this->select = $select->parentIsNot($this) ? clone $select : $select;
        $this->select->parent = $this;
    }

    public function getSQL(DriverInterface $driver = null, Params $params = null): string
    {
        if (isset($this->sql) && $driver === $this->driver && $params === null) {
            return $this->sql;
        }

        $this->driver = $driver; // set last used driver argument
        $this->params = null; // reset previously collected params, if any

        $driver = $driver ?? Driver::ansi();
        $params = $params ?? ($this->params = new Params());

        $operator = static::$not ? Sql::NOT_EXISTS : Sql::EXISTS;
        $select_sql = $this->select->getSQL($driver, $params);

        return $this->sql = "{$operator} ({$select_sql})";
    }

    public function __get(string $name)
    {
        if ('select' === $name) {
            return $this->select;
        }

        return parent::__get($name);
    }

    public function __clone()
    {
        parent::__clone();
        $this->select = clone $this->select;
        $this->select->parent = $this;
    }
}
