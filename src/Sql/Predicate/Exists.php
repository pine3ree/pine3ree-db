<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use InvalidArgumentException;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\Statement\Select;

use function get_class;
use function gettype;
use function implode;
use function is_array;
use function is_object;
use function sprintf;

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
     * @param string|Literal $identifier
     * @param array|Select $value_list
     */
    public function __construct(Select $select)
    {
        $this->select = $select;
    }

    /**
     * {@inheritDoc}
     *
     * If one of the values is NULL then add an IS NULL clause
     *
     * @return string
     */
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
