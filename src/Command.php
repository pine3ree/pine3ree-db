<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db;

use PDOStatement;
use pine3ree\Db\CommandInterface;
use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Statement as SqlStatement;

/**
 * A db-command sends a sql-statement to the composed database instance and
 * returns the result of its execution
 *
 * @property-read SqlStatement $sqlStatement
 * @property-read string $sql The last prepared sql statement stringor the current
 *      sql-statement instance compiled sql string
 */
abstract class Command implements CommandInterface
{
    /**
     * @var Db
     */
    protected $db;

    /**
     * @var SqlStatement
     */
    protected $sqlStatement;

    /**
     * @var string
     */
    protected $sql;

    /**
     * @var Params
     */
    protected $params;

    /**
     * @param Db $db The database abstraction layer
     * @param SqlStatement $sqlStatement The sql-statement object
     */
    public function __construct(Db $db, SqlStatement $sqlStatement)
    {
        $this->db = $db;
        $this->sqlStatement = $sqlStatement;
    }

    public function getSqlStatement(): SqlStatement
    {
        return $this->sqlStatement;
    }

    /**
     * Get the compiled sql string for the current state of the sql-statement
     * instance using the current database driver
     *
     * @return string
     */
    public function getSQL(): string
    {
        return $this->sqlStatement->getSQL($this->db->getDriver(true));
    }

    /**
     * @see Sql\Element::getParams()
     */
    public function getParams(): ?Params
    {
        return $this->sqlStatement->getParams();
    }

    /**
     * Prepare the sql-statement
     *
     * As a side effect the prepared sql string is assigned to the `sql` property
     *
     * @see Db::prepare()
     *
     * @param bool $bind_values
     * @return PDOStatement|false
     */
    protected function prepare(bool $bind_values = false)
    {
        return $this->db->prepare($this->sqlStatement, $bind_values);
    }

    /**
     * Magic getter proxy to the composed sql-statement object
     *
     * @param string $name
     * @return SqlStatement|mixed
     */
    public function __get(string $name)
    {
        if ('sqlStatement' === $name) {
            return $this->sqlStatement;
        }

        if ('sql' === $name) {
            return $this->getSQL();
        }

        return $this->sqlStatement->__get($name);
    }

    public function __clone()
    {
        $this->sqlStatement = clone $this->sqlStatement;
    }
}
